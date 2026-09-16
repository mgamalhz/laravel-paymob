<?php

namespace Paymob\Laravel\DTO;

use Paymob\Laravel\Contracts\ObjectEntriesTransformerInterface;
use Paymob\Laravel\Traits\ObjectEntriesTransformerTrait;

final readonly class RequestPaymentKeyData implements ObjectEntriesTransformerInterface
{
    use ObjectEntriesTransformerTrait;

    public function __construct(
        public int $amountCents,
        public string $currency,
        public int $orderId,
        public int $integrationId,
        public BillingDataDto $billingData,
    ) {}
}
