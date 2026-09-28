<?php

namespace Paymob\Laravel\DTO;

use Paymob\Laravel\Contracts\ObjectEntriesTransformerInterface;
use Paymob\Laravel\Traits\ObjectEntriesTransformerTrait;

final class PaymentMethodDto implements ObjectEntriesTransformerInterface
{
    use ObjectEntriesTransformerTrait;
    public function __construct(
        public readonly int $integrationId,
        public readonly ?string $name,
        public readonly ?string $methodType,
        public readonly string $currency,
    ) {
    }
}
