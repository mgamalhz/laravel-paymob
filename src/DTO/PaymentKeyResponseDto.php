<?php

namespace Paymob\Laravel\DTO;

use Paymob\Laravel\Contracts\ObjectEntriesTransformerInterface;
use Paymob\Laravel\Traits\ObjectEntriesTransformerTrait;

final class PaymentKeyResponseDto implements ObjectEntriesTransformerInterface
{
    use ObjectEntriesTransformerTrait;

    public function __construct(
        public string $token,
    ) {}
}
