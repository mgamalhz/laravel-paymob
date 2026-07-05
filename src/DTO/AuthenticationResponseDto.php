<?php

namespace Paymob\Laravel\DTO;

final readonly class AuthenticationResponseDto
{
    public function __construct(
        public string $token,
    ) {
    }
}
