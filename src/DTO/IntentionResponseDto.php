<?php

namespace Paymob\Laravel\DTO;

use Paymob\Laravel\Contracts\ObjectEntriesTransformerInterface;
use Paymob\Laravel\Traits\ObjectEntriesTransformerTrait;

final class IntentionResponseDto   implements ObjectEntriesTransformerInterface
{
    use ObjectEntriesTransformerTrait;
    /**
     * @param list<PaymentMethodDto> $paymentMethods
     */
    public function __construct(
        public readonly string $id,
        public readonly string $clientSecret,
        public readonly int $intentionOrderId,
        public readonly int $amount,
        public readonly string $currency,
        public readonly string $status,
        public readonly array $paymentMethods,
        public readonly ?string $created = null,
    ) {
    }
}
