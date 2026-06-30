<?php

namespace Paymob\Laravel\DTO;

final readonly class CustomerDto
{
    public function __construct(
        public ?string $id = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $email = null,
        public ?string $phoneNumber = null,
    ) {
    }
}
