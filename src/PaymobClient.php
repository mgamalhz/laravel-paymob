<?php

namespace Paymob\Laravel;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Paymob\Laravel\Contracts\PaymobClientContract;
use Paymob\Laravel\DTO\AuthenticationResponseDto;
use Paymob\Laravel\DTO\CapturePaymentResponseDto;
use Paymob\Laravel\DTO\OrderResponseDto;
use Paymob\Laravel\DTO\PaymentKeyResponseDto;
use Paymob\Laravel\DTO\RegisterOrderData;
use Paymob\Laravel\DTO\RequestPaymentKeyData;
use Paymob\Laravel\Models\PaymobWebhookEvent;

class PaymobClient implements PaymobClientContract
{
    private const HMAC_FIELDS = [
        'amount_cents',
        'created_at',
        'currency',
        'error_occured',
        'has_parent_transaction',
        'id',
        'integration_id',
        'is_3d_secure',
        'is_auth',
        'is_capture',
        'is_refunded',
        'is_standalone_payment',
        'is_voided',
        'order.id',
        'owner',
        'pending',
        'source_data.pan',
        'source_data.sub_type',
        'source_data.type',
        'success',
    ];

    public function __construct(protected array $config)
    {
    }

    public static function checkHmac(mixed $input, ?string $hmacSecret = null, ?string $incomingHmac = null): bool
    {
        if (! is_array($input)) {
            return false;
        }

        $incomingHmac ??= data_get($input, 'hmac');
        $hmacSecret ??= (string) config('paymob.hmac_secret', '');

        if (! is_string($incomingHmac) || $incomingHmac === '' || $hmacSecret === '') {
            return false;
        }

        $expectedHmac = hash_hmac('sha512', self::buildHmacString($input), $hmacSecret);

        return hash_equals($expectedHmac, $incomingHmac);
    }

    public static function recordWebhookEvent(array $payload, ?array $originalPayload = null): bool
    {
        $transactionId = (int) data_get($payload, 'obj.id');

        if (PaymobWebhookEvent::query()->where('transaction_id', $transactionId)->exists()) {
            return false;
        }

        PaymobWebhookEvent::query()->create([
            'transaction_id' => $transactionId,
            'payload' => $originalPayload ?? $payload,
        ]);

        return true;
    }

    private static function buildHmacString(array $input): string
    {
        $payload = data_get($input, 'obj', $input);

        return implode('', array_map(
            fn (string $field): string => self::normalizeHmacValue(data_get($payload, $field)),
            self::HMAC_FIELDS,
        ));
    }

    private static function normalizeHmacValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
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

    public function capture(int $transactionId, int $amountCents): CapturePaymentResponseDto
    {
        $response = $this->http()
            ->post('/api/acceptance/capture?token=' . rawurlencode($this->getToken()), [
                'transaction_id' => $transactionId,
                'amount_cents' => $amountCents,
            ]);

        $response->throw();

        return new CapturePaymentResponseDto(
            payload: $response->json(),
        );
    }
}
