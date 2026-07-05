<?php

namespace Paymob\Laravel\DTO;

final readonly class RegisterOrderData
{
    /**
     * @param list<int> $paymentMethodIds
     * @param list<OrderItemDto> $items
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
    ) {
    }
}
