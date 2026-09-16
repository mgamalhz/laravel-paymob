<?php

namespace Paymob\Laravel\DTO;

use Paymob\Laravel\Contracts\ObjectEntriesTransformerInterface;
use Paymob\Laravel\Traits\ObjectEntriesTransformerTrait;

final readonly class RegisterOrderData implements ObjectEntriesTransformerInterface
{
    use ObjectEntriesTransformerTrait {
        toArray as transformerToArray;
    }

    /**
     * @param  list<int>  $paymentMethodIds
     * @param  list<OrderItemDto>  $items
     */
    public function __construct(
        public int $amount,
        public string $currency,
        public array $paymentMethodIds,
        public array $items,
        public BillingDataDto $billingData,
        public ?CustomerDto $customer = null,
        public ?string $specialReference = null,
        public ?string $notificationUrl = null,
        public ?string $redirectionUrl = null,
    ) {}

    public function toArray(): array
    {
        $data = $this->transformerToArray();

        $data['payment_methods'] = $data['payment_method_ids'];
        unset($data['payment_method_ids']);

        return $data;
    }
}
