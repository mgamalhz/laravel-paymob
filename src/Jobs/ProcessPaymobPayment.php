<?php

namespace Paymob\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Paymob\Laravel\Contracts\PaymobClientContract;
use Paymob\Laravel\DTO\CapturePaymentResponseDto;
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
        public object $order,
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
        Cache::put($this->captureCompletedKey(), true, now()->addDay());

        $response = $paymob->capture($this->transactionId, $this->amountCents);

        $this->markCaptured($response);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Paymob capture job failed.', [
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

        if (method_exists($this->order, 'isPaymobCaptured')) {
            return (bool) $this->order->isPaymobCaptured();
        }

        if (method_exists($this->order, 'isCaptured')) {
            return (bool) $this->order->isCaptured();
        }

        if (($this->order->paymob_captured ?? false) === true) {
            return true;
        }

        if (($this->order->captured ?? false) === true) {
            return true;
        }

        if (($this->order->payment_status ?? null) === 'captured') {
            return true;
        }

        return ($this->order->captured_at ?? null) !== null;
    }

    private function markCaptured(CapturePaymentResponseDto $response): void
    {
        if (method_exists($this->order, 'markPaymobCaptured')) {
            $this->order->markPaymobCaptured($response);

            return;
        }

        if (method_exists($this->order, 'markCaptured')) {
            $this->order->markCaptured($response);

            return;
        }

        if (property_exists($this->order, 'paymob_captured')) {
            $this->order->paymob_captured = true;
        }

        if (property_exists($this->order, 'captured')) {
            $this->order->captured = true;
        }

        if (property_exists($this->order, 'payment_status')) {
            $this->order->payment_status = 'captured';
        }

        if (property_exists($this->order, 'captured_at') && $this->order->captured_at === null) {
            $this->order->captured_at = now();
        }

        if (method_exists($this->order, 'save')) {
            $this->order->save();
        }
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
}
