<?php

namespace Paymob\Laravel\Tests;

use BadMethodCallException;
use Paymob\Laravel\Contracts\PaymobClientContract;
use Paymob\Laravel\DTO\AuthenticationResponseDto;
use Paymob\Laravel\DTO\CapturePaymentResponseDto;
use Paymob\Laravel\DTO\OrderResponseDto;
use Paymob\Laravel\DTO\PaymentKeyResponseDto;
use Paymob\Laravel\DTO\RegisterOrderData;
use Paymob\Laravel\DTO\RequestPaymentKeyData;

final class FakePaymobCaptureClient implements PaymobClientContract
{
    public int $captures = 0;

    public ?int $lastTransactionId = null;

    public ?int $lastAmountCents = null;

    public function authenticate(): AuthenticationResponseDto
    {
        throw new BadMethodCallException('Not used in this test.');
    }

    public function registerOrder(RegisterOrderData $data): OrderResponseDto
    {
        throw new BadMethodCallException('Not used in this test.');
    }

    public function requestPaymentKey(RequestPaymentKeyData $data): PaymentKeyResponseDto
    {
        throw new BadMethodCallException('Not used in this test.');
    }

    public function paymentRedirectUrl(string $paymentToken, ?int $iframeId = null): string
    {
        throw new BadMethodCallException('Not used in this test.');
    }

    public function capture(int $transactionId, int $amountCents): CapturePaymentResponseDto
    {
        $this->captures++;
        $this->lastTransactionId = $transactionId;
        $this->lastAmountCents = $amountCents;

        return new CapturePaymentResponseDto([
            'id' => $transactionId,
            'amount_cents' => $amountCents,
            'success' => true,
        ]);
    }
}
