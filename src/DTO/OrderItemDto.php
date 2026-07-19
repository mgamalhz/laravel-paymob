<?php

namespace Paymob\Laravel\DTO;

use Paymob\Laravel\Contracts\ObjectEntriesTransformerInterface;
use Paymob\Laravel\Traits\ObjectEntriesTransformerTrait;

final readonly class OrderItemDto implements ObjectEntriesTransformerInterface
{
    use ObjectEntriesTransformerTrait;
    public function __construct(
        public string $name,
        public int $amount,
        public int $quantity = 1,
        public ?string $description = null,
    ) {
    }
}
