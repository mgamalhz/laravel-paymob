<?php

namespace Paymob\Laravel\DTO;

final readonly class CapturePaymentResponseDto
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
    ) {}
}
