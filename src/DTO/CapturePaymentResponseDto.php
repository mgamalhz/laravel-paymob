<?php

namespace Paymob\Laravel\DTO;

final class CapturePaymentResponseDto
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly array $payload,
    ) {
    }
}
