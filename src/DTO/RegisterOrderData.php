<?php

namespace Paymob\Laravel\DTO;

use Paymob\Laravel\Contracts\ObjectEntriesTransformerInterface;
use Paymob\Laravel\Traits\ObjectEntriesTransformerTrait;

final class RegisterOrderData implements ObjectEntriesTransformerInterface
{
    use ObjectEntriesTransformerTrait {
        toArray as transformerToArray;
    }

    /**
     * @param list<int> $paymentMethodIds
     * @param list<OrderItemDto> $items
     */
    public function __construct(
        public readonly int $amount,
        public readonly string $currency,
        public readonly array $paymentMethodIds,
        public readonly array $items,
        public readonly BillingDataDto $billingData,
        public readonly ?CustomerDto $customer = null,
        public readonly ?string $specialReference = null,
        public readonly ?string $notificationUrl = null,
        public readonly ?string $redirectionUrl = null,
    ) {
    }



    public function toArray(): array
    {
        $data = $this->transformerToArray();

        $data['payment_methods'] = $data['payment_method_ids'];
        unset($data['payment_method_ids']);

        return $data;
    }
}
