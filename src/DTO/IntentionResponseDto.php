<?php

namespace Paymob\Laravel\DTO;

use Paymob\Laravel\Contracts\ObjectEntriesTransformerInterface;
use Paymob\Laravel\Traits\ObjectEntriesTransformerTrait;

final readonly class IntentionResponseDto implements ObjectEntriesTransformerInterface
{
    use ObjectEntriesTransformerTrait;

    /**
     * @param  list<PaymentMethodDto>  $paymentMethods
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
    ) {}
}
