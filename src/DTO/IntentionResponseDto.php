<?php

namespace Paymob\Laravel\DTO;

final readonly class IntentionResponseDto
{
    /**
     * @param list<PaymentMethodDto> $paymentMethods
     */
    public function __construct(
        public string $id,
        public string $clientSecret,
        public int $intentionOrderId,
        public int $amount,
        public string $currency,
        public string $status,
        public array $paymentMethods,
        public ?string $created = null,
    ) {
    }
}
