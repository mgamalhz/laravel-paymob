<?php

namespace Paymob\Laravel\DTO;

final readonly class PaymentMethodDto
{
    public function __construct(
        public int $integrationId,
        public string $name,
        public string $methodType,
        public string $currency,
    ) {
    }
}
