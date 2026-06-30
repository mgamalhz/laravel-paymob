<?php

namespace Paymob\Laravel\DTO;

final readonly class RequestPaymentKeyData
{
    public function __construct(
        public string $clientSecret,
        public ?int $integrationId = null,
        public ?BillingDataDto $billingData = null,
        public ?CustomerDto $customer = null,
        public ?string $redirectionUrl = null,
        public ?string $notificationUrl = null,
    ) {
    }
}
