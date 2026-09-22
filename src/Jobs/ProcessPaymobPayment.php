<?php

namespace Paymob\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Paymob\Laravel\Contracts\PaymobCapturable;
use Paymob\Laravel\Contracts\PaymobClientContract;
use Paymob\Laravel\DTO\CapturePaymentResponseDto;
use Paymob\Laravel\Models\Payment;
use Paymob\Laravel\Support\PaymobLogEvents;
use Paymob\Laravel\Support\PaymobLogger;
use Throwable;

class ProcessPaymobPayment implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 300, 900];

    public int $timeout = 120;

    public function __construct(
        public PaymobCapturable $order,
        public int $transactionId,
        public int $amountCents,
    ) {
    }

    /**
     * @return list<WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->overlapKey()))->releaseAfter(30),
        ];
    }

    public function handle(PaymobClientContract $paymob): void
    {
        if ($this->alreadyCaptured()) {
            return;
        }

        try {
            DB::transaction(function () use ($paymob): void {
                if ($this->hasCapturedPayment()) {
                    return;
                }

                $this->reservePayment();

                DB::afterCommit(function () use ($paymob): void {
                    $response = $paymob->capture($this->transactionId, $this->amountCents);

                    $this->markCaptured($response);
                    $this->markPaymentCaptured($response);
                });
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }
        }

        if ($this->hasCapturedPayment()) {
            Cache::put($this->captureCompletedKey(), true, now()->addDay());
        }
    }

    public function failed(Throwable $exception): void
    {
        PaymobLogger::error(PaymobLogEvents::FAILURE, [
            'operation' => 'capture_job',
            'order_id' => $this->orderId(),
            'transaction_id' => $this->mask((string) $this->transactionId),
            'amount_cents' => $this->amountCents,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }

    private function alreadyCaptured(): bool
    {
        if (Cache::get($this->captureCompletedKey()) === true) {
            return true;
        }

        if ($this->hasCapturedPayment()) {
            Cache::put($this->captureCompletedKey(), true, now()->addDay());

            return true;
        }

        return $this->order->isPaymobCaptured();
    }

    private function markCaptured(CapturePaymentResponseDto $response): void
    {
        $this->order->markPaymobCaptured($response);
    }

    private function hasCapturedPayment(): bool
    {
        return Payment::query()
            ->where('paymob_reference', $this->paymentReference())
            ->where('status', 'captured')
            ->exists();
    }

    private function reservePayment(): void
    {
        Payment::query()->create([
            'paymob_reference' => $this->paymentReference(),
            'transaction_id' => $this->transactionId,
            'order_type' => $this->order::class,
            'order_id' => $this->orderId(),
            'amount_cents' => $this->amountCents,
            'status' => 'processing',
        ]);
    }

    private function markPaymentCaptured(CapturePaymentResponseDto $response): void
    {
        $payment = Payment::query()
            ->where('paymob_reference', $this->paymentReference())
            ->firstOrFail();

        $payment->forceFill([
            'status' => 'captured',
            'response_payload' => $response->payload,
            'captured_at' => now(),
        ])->save();
    }

    private function paymentReference(): string
    {
        return (string) $this->transactionId;
    }

    private function orderId(): string
    {
        return (string) ($this->order->id ?? spl_object_id($this->order));
    }

    private function overlapKey(): string
    {
        return 'paymob-payment:' . $this->orderId();
    }

    private function captureCompletedKey(): string
    {
        return 'paymob-payment-captured:' . $this->orderId();
    }

    private function mask(string $value): string
    {
        if (strlen($value) <= 4) {
            return str_repeat('*', strlen($value));
        }

        return str_repeat('*', max(strlen($value) - 4, 0)) . substr($value, -4);
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        return in_array($sqlState, ['23000', '23505'], true)
            || in_array($driverCode, ['19', '1062', '1555'], true);
    }
}
