<?php

namespace Paymob\Laravel\DTO;

final readonly class BillingDataDto
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phoneNumber,
        public string $street,
        public string $building,
        public string $city,
        public string $country,
        public ?string $state = null,
        public ?string $apartment = null,
        public ?string $floor = null,
        public ?string $postalCode = null,
    ) {
    }
}
