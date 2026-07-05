<?php

namespace Paymob\Laravel;

use BadMethodCallException;
use Paymob\Laravel\Contracts\PaymobClientContract;
use Paymob\Laravel\DTO\AuthenticationResponseDto;
use Paymob\Laravel\DTO\IntentionResponseDto;
use Paymob\Laravel\DTO\PaymentKeyResponseDto;
use Paymob\Laravel\DTO\RegisterOrderData;
use Paymob\Laravel\DTO\RequestPaymentKeyData;

class PaymobClient implements PaymobClientContract
{
    public function __construct(protected array $config)
    {
    }

    public function authenticate(): AuthenticationResponseDto
    {
       return new AuthenticationResponseDto(token: 'AUTH_TOKEN_123');
    }

    public function registerOrder(RegisterOrderData $data): IntentionResponseDto
    {
        throw new BadMethodCallException('registerOrder() is not implemented yet.');
    }

    public function requestPaymentKey(RequestPaymentKeyData $data): PaymentKeyResponseDto
    {
        throw new BadMethodCallException('requestPaymentKey() is not implemented yet.');
    }

    public function getApiKey(): string
    {
        return (string) ($this->config['api_key'] ?? '');
    }

    public function baseUrl(): string
    {
        return (string) ($this->config['base_url'] ?? '');
    }

    public function timeout(): int
    {
        return (int) ($this->config['timeout'] ?? 30);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function configs(): array
    {
        return $this->config;
    }
}
