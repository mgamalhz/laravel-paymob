<?php

namespace Paymob\Laravel;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Paymob\Laravel\Contracts\PaymobClientContract;
use Paymob\Laravel\DTO\AuthenticationResponseDto;
use Paymob\Laravel\DTO\CapturePaymentResponseDto;
use Paymob\Laravel\DTO\OrderResponseDto;
use Paymob\Laravel\DTO\PaymentKeyResponseDto;
use Paymob\Laravel\DTO\RegisterOrderData;
use Paymob\Laravel\DTO\RequestPaymentKeyData;
use Paymob\Laravel\Models\PaymobWebhookEvent;
use RuntimeException;
use Throwable;

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
        if ($cached = $this->cachedAuthentication()) {
            return $cached;
        }

        $refreshStarted = false;

        try {
            return $this->cache()->lock(
                $this->tokenCacheKey() . ':lock',
                max(1, (int) $this->config('token_cache.lock_seconds', 10)),
            )->block(
                max(1, (int) $this->config('token_cache.lock_wait_seconds', 5)),
                function () use (&$refreshStarted): AuthenticationResponseDto {
                    if ($cached = $this->cachedAuthentication()) {
                        return $cached;
                    }

                    $refreshStarted = true;

                    return $this->requestAuthentication();
                },
            );
        } catch (Throwable $exception) {
            if ($refreshStarted) {
                throw $exception;
            }

            // A cache outage must not make Paymob unavailable. Lock timeouts are
            // also safe to recover from by making one uncached authentication call.
            return $this->requestAuthentication();
        }
    }

    private function requestAuthentication(): AuthenticationResponseDto
    {
        try {
            $response = $this->http()
                ->post('/api/auth/tokens', [
                    'api_key' => $this->getApiKey(),
                ]);

            $response->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException(
                'Paymob authentication failed with HTTP status ' . $exception->response->status() . '.',
            );
        } catch (ConnectionException) {
            throw new RuntimeException('Could not connect to Paymob authentication service.');
        }

        $response = $response->json();
        $authDto = new AuthenticationResponseDto(
            $response['token'],
        );
        Cache::put('paymob_token', $authDto, now()->addMinutes(58));

        return $authDto;
    }

    public function registerOrder(RegisterOrderData $data): OrderResponseDto
    {
        $response = $this->authenticatedRequest(
            fn (string $token): Response => $this->http()
            ->post('/api/ecommerce/orders', array_merge([
                'auth_token' => $token,
                'delivery_needed' => false,
                'amount_cents' => $data->amount,
                'currency' => $data->currency,
                'items' => array_map(
                    fn ($item) => $item->toArray(),
                    $data->items
                ),
            ], $data->specialReference !== null ? ['merchant_order_id' => $data->specialReference] : [])),
        );

        $response->throw();

        $response = $response->json();

        return new OrderResponseDto(
            id: $response['id'],
            createdAt: $response['created_at'] ?? null,
        );
    }

    public function requestPaymentKey(RequestPaymentKeyData $data): PaymentKeyResponseDto
    {
        $response = $this->authenticatedRequest(
            fn (string $token): Response => $this->http()
            ->post('/api/acceptance/payment_keys', array_merge(
                ['auth_token' => $token],
                $data->toArray(),
            )),
        );

        $response->throw();

        $response = $response->json();

        return new PaymentKeyResponseDto(
            token: $response['token'],
        );
    }

    public function paymentRedirectUrl(string $paymentToken, ?int $iframeId = null): string
    {
        $iframeId ??= $this->iframeId();

        if ($iframeId <= 0) {
            throw new InvalidArgumentException('Paymob iframe id is not configured.');
        }

        return rtrim($this->baseUrl(), '/')
            . '/api/acceptance/iframes/' . $iframeId
            . '?payment_token=' . urlencode($paymentToken);
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

    public function iframeId(): int
    {
        return (int) ($this->config['iframe_id'] ?? 0);
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
        return data_get($this->config, $key, $default);
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
            ->retry(
                3,
                100,
                fn (Throwable $exception): bool => $exception instanceof ConnectionException,
                false,
            );
    }

    private function cache(): CacheRepository
    {
        $store = $this->config('token_cache.store');

        return Cache::store(is_string($store) && $store !== '' ? $store : null);
    }

    private function getToken(): string {
        $cachedToken = Cache::get('paymob_token');

        if (is_string($cachedToken) && $cachedToken !== '') {
            return $cachedToken;
        }

        if ($cachedToken instanceof AuthenticationResponseDto) {
            return $cachedToken->token;
        }

        if ($cachedToken !== null) {
            Cache::forget('paymob_token');
        }

        return $this->authenticate()->token;
    }

    private function maskSensitivePayload(array $payload): array
    {
        foreach (['auth_token', 'token', 'api_key'] as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '[masked]';
            }
        }

        return $payload;
    }
    private function tokenCacheKey(): string
    {
        $scope = implode('|', [
            strtolower(rtrim($this->baseUrl(), '/')),
            (string) $this->config('token_cache.environment', config('app.env', 'production')),
            hash('sha256', $this->getApiKey()),
        ]);

        return (string) $this->config('token_cache.prefix', 'paymob:auth-token')
            . ':' . hash('sha256', $scope);
    }

    private function cachedAuthentication(): ?AuthenticationResponseDto
    {
        try {
            $cached = $this->cache()->get($this->tokenCacheKey());

            return $cached instanceof AuthenticationResponseDto ? $cached : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function forgetCachedAuthentication(): void
    {
        try {
            $this->cache()->forget($this->tokenCacheKey());
        } catch (Throwable) {
            // Authentication can still be refreshed without cache access.
        }
    }

    private function authenticatedRequest(callable $request): Response
    {
        try {
            $response = $request($this->authenticate()->token);

            if (in_array($response->status(), [401, 403], true)) {
                $this->forgetCachedAuthentication();
                $response = $request($this->requestAuthentication()->token);
            }

            $response->throw();

            return $response;
        } catch (RequestException $exception) {
            throw new RuntimeException(
                'Paymob request failed with HTTP status ' . $exception->response->status() . '.',
            );
        } catch (ConnectionException) {
            throw new RuntimeException('Could not connect to Paymob.');
        }
    }

    public function capture(int $transactionId, int $amountCents): CapturePaymentResponseDto
    {
        $response = $this->authenticatedRequest(
            fn (string $token): Response => $this->http()
            ->withQueryParameters(['token' => $token])
            ->post('/api/acceptance/capture', [
                'transaction_id' => $transactionId,
                'amount_cents' => $amountCents,
            ]));

        $response->throw();

        return new CapturePaymentResponseDto(
            payload: $response->json(),
        );
    }
}
