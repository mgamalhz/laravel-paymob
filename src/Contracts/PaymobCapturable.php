<?php

namespace Paymob\Laravel\Contracts;

use Paymob\Laravel\DTO\CapturePaymentResponseDto;

interface PaymobCapturable
{
    public function isPaymobCaptured(): bool;

    public function markPaymobCaptured(CapturePaymentResponseDto $response): void;
}
