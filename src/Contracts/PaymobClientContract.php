<?php

namespace Paymob\Laravel\Contracts;

use Paymob\Laravel\DTO\AuthenticationResponseDto;
use Paymob\Laravel\DTO\OrderResponseDto;
use Paymob\Laravel\DTO\PaymentKeyResponseDto;
use Paymob\Laravel\DTO\RegisterOrderData;
use Paymob\Laravel\DTO\RequestPaymentKeyData;

interface PaymobClientContract
{
    public function authenticate(): AuthenticationResponseDto;

    public function registerOrder(RegisterOrderData $data): OrderResponseDto;

    public function requestPaymentKey(RequestPaymentKeyData $data): PaymentKeyResponseDto;
}
