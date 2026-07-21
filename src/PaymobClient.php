<?php

namespace Paymob\Laravel;

use BadMethodCallException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Paymob\Laravel\Contracts\PaymobClientContract;
use Paymob\Laravel\DTO\AuthenticationResponseDto;
use Paymob\Laravel\DTO\CapturePaymentResponseDto;
use Paymob\Laravel\DTO\IntentionResponseDto;
use Paymob\Laravel\DTO\PaymentKeyResponseDto;
use Paymob\Laravel\DTO\PaymentMethodDto;
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
        $response = $this->http()->post('/api/auth/tokens', [
            'api_key' => $this->getApiKey(),
        ]);

        $response->throw();

        $auth = new AuthenticationResponseDto(
            token: $response->json('token'),
        );

        Cache::put('paymob_token', $auth, now()->addMinutes(58));

        return $auth;
    }

    public function registerOrder(RegisterOrderData $data): DTO\OrderResponseDto
    {
        $response = $this->http()->post('/v1/intention/', [
            'amount' => $data->amount,
            'currency' => $data->currency,
            'payment_methods' => $data->paymentMethodIds,
            'items' => array_map(
                static fn ($item): array => [
                    'name' => $item->name,
                    'amount' => $item->amount,
                    'quantity' => $item->quantity,
                    'description' => $item->description,
                ],
                $data->items,
            ),
            'billing_data' => [
                'first_name' => $data->billingData->firstName,
                'last_name' => $data->billingData->lastName,
                'email' => $data->billingData->email,
                'phone_number' => $data->billingData->phoneNumber,
                'street' => $data->billingData->street,
                'building' => $data->billingData->building,
                'city' => $data->billingData->city,
                'country' => $data->billingData->country,
            ],
        ]);

        $response->throw();

        $payload = $response->json();

        return new IntentionResponseDto(
            id: $payload['id'],
            clientSecret: $payload['client_secret'],
            intentionOrderId: $payload['intention_order_id'],
            amount: $payload['amount'],
            currency: $payload['currency'],
            status: $payload['status'],
            paymentMethods: array_map(
                static fn (array $paymentMethod): PaymentMethodDto => new PaymentMethodDto(
                    integrationId: $paymentMethod['integration_id'],
                    name: $paymentMethod['name'],
                    methodType: $paymentMethod['method_type'],
                    currency: $paymentMethod['currency'],
                ),
                $payload['payment_methods'] ?? [],
            ),
            created: $payload['created'] ?? null,
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


    public function capture(int $transactionId, int $amountCents): CapturePaymentResponseDto
    {
        $response = $this->http()->post('/api/acceptance/capture?token=' . $this->getToken(), [
            'transaction_id' => $transactionId,
            'amount_cents' => $amountCents,
        ]);

        $response->throw();

        return new CapturePaymentResponseDto(
            payload: $response->json(),
        );
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseHostUrl())
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout())
            ->connectTimeout((int) ($this->config['connect_timeout'] ?? 10))
            ->retry(3, 100);
    }

    private function getToken(): string
    {
        return Cache::get('paymob_token')?->token ?? $this->authenticate()->token;
    }

    private function baseHostUrl(): string
    {
        $baseUrl = rtrim($this->baseUrl(), '/');

        if (str_ends_with($baseUrl, '/api')) {
            return substr($baseUrl, 0, -4);
        }

        return $baseUrl;
    }
}
