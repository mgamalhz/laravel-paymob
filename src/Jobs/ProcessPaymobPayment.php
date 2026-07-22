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
use Paymob\Laravel\Contracts\PaymobCapturable;
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

        $response = $paymob->capture($this->transactionId, $this->amountCents);

        $this->markCaptured($response);

        Cache::put($this->captureCompletedKey(), true, now()->addDay());
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

        return $this->order->isPaymobCaptured();
    }

    private function markCaptured(CapturePaymentResponseDto $response): void
    {
        $this->order->markPaymobCaptured($response);
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
