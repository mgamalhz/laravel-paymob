<?php

namespace Paymob\Laravel\DTO;

use Paymob\Laravel\Contracts\ObjectEntriesTransformerInterface;
use Paymob\Laravel\Traits\ObjectEntriesTransformerTrait;

final class CustomerDto implements ObjectEntriesTransformerInterface
{
    use ObjectEntriesTransformerTrait;
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $email = null,
        public readonly ?string $phoneNumber = null,
    ) {
    }
}
