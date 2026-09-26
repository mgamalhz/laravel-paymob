<?php

namespace Paymob\Laravel\DTO;

use Paymob\Laravel\Contracts\ObjectEntriesTransformerInterface;
use Paymob\Laravel\Traits\ObjectEntriesTransformerTrait;

final class BillingDataDto implements ObjectEntriesTransformerInterface
{
    use ObjectEntriesTransformerTrait;
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly string $phoneNumber,
        public readonly string $street,
        public readonly string $building,
        public readonly string $city,
        public readonly string $country,
        public readonly ?string $state = null,
        public readonly ?string $apartment = null,
        public readonly ?string $floor = null,
        public readonly ?string $postalCode = null,
    ) {
    }



}
