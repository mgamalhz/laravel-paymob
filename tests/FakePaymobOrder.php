<?php

namespace Paymob\Laravel\Tests;

use Paymob\Laravel\Contracts\PaymobCapturable;
use Paymob\Laravel\DTO\CapturePaymentResponseDto;

final class FakePaymobOrder implements PaymobCapturable
{
    /**
     * @var array<int, bool>
     */
    public static array $capturedById = [];

    public bool $captured = false;

    public ?string $payment_status = null;

    public ?string $captured_at = null;

    public function __construct(public int $id)
    {
        self::$capturedById[$this->id] = false;
    }

    public function isPaymobCaptured(): bool
    {
        return self::$capturedById[$this->id];
    }

    public function markPaymobCaptured(CapturePaymentResponseDto $response): void
    {
        $this->captured = true;
        $this->payment_status = 'captured';
        $this->captured_at = 'now';
        self::$capturedById[$this->id] = true;
    }
}
