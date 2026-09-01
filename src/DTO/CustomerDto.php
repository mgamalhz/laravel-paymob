<?php

namespace Paymob\Laravel\DTO;

use Paymob\Laravel\Contracts\ObjectEntriesTransformerInterface;
use Paymob\Laravel\Traits\ObjectEntriesTransformerTrait;

final readonly class CustomerDto implements ObjectEntriesTransformerInterface
{
    use ObjectEntriesTransformerTrait;
    public function __construct(
        public ?string $id = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $email = null,
        public ?string $phoneNumber = null,
    ) {
    }
}
