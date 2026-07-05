<?php

namespace Paymob\Laravel\DTO;

final  class PaymentKeyResponseDto
{
    public function __construct(
        public string $token,
    ) {
    }
}
