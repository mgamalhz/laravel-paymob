<?php

namespace Paymob\Laravel\DTO;

use Paymob\Laravel\Contracts\ObjectEntriesTransformerInterface;
use Paymob\Laravel\Traits\ObjectEntriesTransformerTrait;

final readonly class AuthenticationResponseDto implements ObjectEntriesTransformerInterface
{
    use ObjectEntriesTransformerTrait;
    public function __construct(
        public string $token,
    ) {
    }
}
