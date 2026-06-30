<?php

namespace Paymob\Laravel\DTO;

final readonly class OrderItemDto
{
    public function __construct(
        public string $name,
        public int $amount,
        public int $quantity = 1,
        public ?string $description = null,
    ) {
    }
}
