<?php

namespace Paymob\Laravel\DTO;

use Paymob\Laravel\Contracts\ObjectEntriesTransformerInterface;
use Paymob\Laravel\Traits\ObjectEntriesTransformerTrait;

final readonly class BillingDataDto implements ObjectEntriesTransformerInterface
{
    use ObjectEntriesTransformerTrait;

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
    ) {}

}
