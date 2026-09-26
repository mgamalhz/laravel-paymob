<?php

namespace Paymob\Laravel\Traits;

use Illuminate\Support\Str;
use Paymob\Laravel\Contracts\ObjectEntriesTransformerInterface;

trait ObjectEntriesTransformerTrait
{
    public function toArray(): array
    {
        $vars = get_object_vars($this);
        $data = [];

        foreach ($vars as $key => $value) {
            $value = $this->transformValue($value);

            if ($value === null) {
                continue;
            }

            $data[Str::snake($key)] = $value;
        }

        return $data;
    }

    private function transformValue(mixed $value): mixed
    {
        if ($value instanceof ObjectEntriesTransformerInterface) {
            return $value->toArray();
        }

        if (is_array($value)) {
            return array_map(
                function (mixed $item) {
                    return $this->transformValue($item);
                },
                $value
            );
        }

        return $value;
    }
}
