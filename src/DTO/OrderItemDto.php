<?php

namespace Paymob\Laravel\DTO;

use Paymob\Laravel\Contracts\ObjectEntriesTransformerInterface;
use Paymob\Laravel\Traits\ObjectEntriesTransformerTrait;

final class OrderItemDto implements ObjectEntriesTransformerInterface
{
    use ObjectEntriesTransformerTrait {
        toArray as transformerToArray;
    }

    public function __construct(
        public readonly string $name,
        public readonly int $amount,
        public readonly int $quantity = 1,
        public readonly ?string $description = null,
    ) {
    }

    public function toArray(): array
    {
        $data = $this->transformerToArray();

        $data['amount_cents'] = $data['amount'];
        unset($data['amount']);

        return $data;
    }
}
