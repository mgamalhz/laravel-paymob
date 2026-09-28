<?php

namespace Paymob\Laravel\DTO;

use Paymob\Laravel\Contracts\ObjectEntriesTransformerInterface;
use Paymob\Laravel\Traits\ObjectEntriesTransformerTrait;

final class RequestPaymentKeyData implements ObjectEntriesTransformerInterface
{
    use ObjectEntriesTransformerTrait;

    public function __construct(
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly int $orderId,
        public readonly int $integrationId,
        public readonly BillingDataDto $billingData,
    ) {
    }
}
