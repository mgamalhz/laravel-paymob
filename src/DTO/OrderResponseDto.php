<?php

namespace Paymob\Laravel\DTO;

use Paymob\Laravel\Contracts\ObjectEntriesTransformerInterface;
use Paymob\Laravel\Traits\ObjectEntriesTransformerTrait;

final readonly class OrderResponseDto implements ObjectEntriesTransformerInterface
{
    use ObjectEntriesTransformerTrait;

    public function __construct(
        public int $id,
        public ?string $createdAt = null,
    ) {}
}
