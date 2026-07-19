<?php

namespace Paymob\Laravel;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Paymob\Laravel\Contracts\PaymobClientContract;
use Paymob\Laravel\DTO\AuthenticationResponseDto;
use Paymob\Laravel\DTO\OrderResponseDto;
use Paymob\Laravel\DTO\PaymentKeyResponseDto;
use Paymob\Laravel\DTO\RegisterOrderData;
use Paymob\Laravel\DTO\RequestPaymentKeyData;

class PaymobClient implements PaymobClientContract
{
    public function __construct(protected array $config)
    {
    }

    /**
     * @throws ConnectionException
     */
    public function authenticate(): AuthenticationResponseDto
    {
        $response = $this->http()
            ->post('/api/auth/tokens', [
                'api_key' => $this->getApiKey(),
            ]);

        $response->throw();

        $response = $response->json();
        $authDto = new AuthenticationResponseDto(
            $response['token'],
        );
        Cache::put('paymob_token', $authDto, now()->addMinutes(58));

        return $authDto;
    }

    public function registerOrder(RegisterOrderData $data): OrderResponseDto
    {
        $response = $this->http()
            ->post('/api/ecommerce/orders', array_merge([
                'auth_token' => $this->getToken(),
                'delivery_needed' => false,
                'amount_cents' => $data->amount,
                'currency' => $data->currency,
                'items' => array_map(
                    fn ($item) => $item->toArray(),
                    $data->items
                ),
            ], $data->specialReference !== null ? ['merchant_order_id' => $data->specialReference] : []));

        $response->throw();

        $response = $response->json();

        return new OrderResponseDto(
            id: $response['id'],
            createdAt: $response['created_at'] ?? null,
        );
    }

    public function requestPaymentKey(RequestPaymentKeyData $data): PaymentKeyResponseDto
    {
        $response = $this->http()
            ->post('/api/acceptance/payment_keys', array_merge(
                ['auth_token' => $this->getToken()],
                $data->toArray(),
            ));

        $response->throw();

        $response = $response->json();

        return new PaymentKeyResponseDto(
            token: $response['token'],
        );
    }

    public function getApiKey(): string
    {
        return (string) ($this->config['api_key'] ?? '');
    }

    public function getSecretKey(): string
    {
        return (string) ($this->config['secret_key'] ?? '');
    }

    public function baseUrl(): string
    {
        return (string) ($this->config['base_url'] ?? '');
    }

    public function timeout(): int
    {
        return (int) ($this->config['timeout'] ?? 30);
    }

    public function connectTimeout(): int
    {
        return (int) ($this->config['connect_timeout'] ?? 10);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function configs(): array
    {
        return $this->config;
    }



    private function http()
    {
        return Http::baseUrl($this->baseUrl())
            ->accept('application/json')
            ->asJson()
            ->timeout($this->timeout())
            ->connectTimeout($this->connectTimeout())
            ->retry(3 ,  100);
    }


    private function getToken(): string {
        return Cache::get("paymob_token")?->token ?? $this->authenticate()->token;
    }

}
