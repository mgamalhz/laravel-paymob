<?php

namespace Paymob\Laravel;

use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Paymob\Laravel\Contracts\PaymobClientContract;
use Paymob\Laravel\DTO\AuthenticationResponseDto;
use Paymob\Laravel\DTO\CapturePaymentResponseDto;
use Paymob\Laravel\DTO\OrderResponseDto;
use Paymob\Laravel\DTO\PaymentKeyResponseDto;
use Paymob\Laravel\DTO\RegisterOrderData;
use Paymob\Laravel\DTO\RequestPaymentKeyData;
use Paymob\Laravel\Exceptions\PaymobAuthenticationException;
use Paymob\Laravel\Exceptions\PaymobDomainException;
use Paymob\Laravel\Models\PaymobWebhookEvent;
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

    public function __construct(protected array $config) {}

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
            return $this->lock(
                $this->tokenCacheKey().':lock',
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
            throw new PaymobAuthenticationException(
                $this->requestFailureMessage('Paymob authentication', $exception),
                $exception->response->status(),
                $exception,
            );
        } catch (ConnectionException $exception) {
            throw new PaymobAuthenticationException(
                'Could not connect to Paymob authentication service after '.$this->retryAttempts().' attempts.',
                previous: $exception,
            );
        }

        $response = $response->json();
        $authDto = new AuthenticationResponseDto(
            $response['token'],
        );

        try {
            $this->cache()->put(
                $this->tokenCacheKey(),
                $authDto,
                now()->addSeconds(max(1, (int) $this->config('token_cache.ttl_seconds', 58 * 60))),
            );
        } catch (Throwable) {
            // Authentication succeeded, so callers should not fail because cache is unavailable.
        }

        return $authDto;
    }

    public function registerOrder(RegisterOrderData $data): OrderResponseDto
    {
        $retryOrderCreation = $data->specialReference !== null;

        $response = $this->authenticatedRequest(
            fn (string $token): Response => $this->http($retryOrderCreation)
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
            $this->retryAttempts($retryOrderCreation),
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
            .'/api/acceptance/iframes/'.$iframeId
            .'?payment_token='.urlencode($paymentToken);
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

    private function http(bool $allowRetry = true)
    {
        return Http::baseUrl($this->baseUrl())
            ->accept('application/json')
            ->asJson()
            ->timeout($this->timeout())
            ->connectTimeout($this->connectTimeout())
            ->retry(
                $this->retryAttempts($allowRetry),
                fn (int $attempt, Throwable $exception): int => $this->retryDelay($attempt, $exception),
                fn (Throwable $exception): bool => $this->shouldRetry($exception),
                false,
            );
    }

    private function retryAttempts(bool $allowRetry = true): int
    {
        if (! $allowRetry) {
            return 1;
        }

        return max(1, (int) $this->config('retry_limit', 5));
    }

    private function retryDelay(int $attempt, Throwable $exception): int
    {
        $delay = null;

        if ($exception instanceof RequestException) {
            $retryAfter = $exception->response->header('Retry-After');

            if (is_numeric($retryAfter)) {
                $delay = min(max(0, (int) $retryAfter) * 1000, $this->retryMaxDelay());
            }
        }

        $delay ??= min($this->exponentialBackoffDelay($attempt) + $this->retryJitter(), $this->retryMaxDelay());

        $this->logRetryAttempt($attempt, $delay, $exception);

        return $delay;
    }

    private function exponentialBackoffDelay(int $attempt): int
    {
        $attempt = max(1, $attempt);

        return $this->retryBaseDelay() * (2 ** ($attempt - 1));
    }

    private function retryBaseDelay(): int
    {
        return max(0, (int) $this->config('retry_base_delay_ms', 500));
    }

    private function retryMaxDelay(): int
    {
        return max($this->retryBaseDelay(), (int) $this->config('retry_max_delay_ms', 10000));
    }

    private function retryJitter(): int
    {
        $jitter = max(0, (int) $this->config('retry_jitter_ms', 250));

        return $jitter > 0 ? random_int(0, $jitter) : 0;
    }

    private function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if ($exception instanceof RequestException) {
            $status = $exception->response->status();

            return $status === 429 || $exception->response->serverError();
        }

        return false;
    }

    private function logRetryAttempt(int $attempt, int $delay, Throwable $exception): void
    {
        Log::warning('Retrying Paymob request.', [
            'attempt' => $attempt,
            'max_attempts' => $this->retryAttempts(),
            'delay_ms' => $delay,
            'status' => $exception instanceof RequestException ? $exception->response->status() : null,
            'endpoint' => $exception instanceof RequestException ? $this->responseEndpoint($exception->response) : null,
            'exception' => $exception::class,
        ]);
    }

    private function responseEndpoint(Response $response): ?string
    {
        $uri = $response->effectiveUri();

        if ($uri === null) {
            return null;
        }

        return parse_url((string) $uri, PHP_URL_PATH) ?: null;
    }

    private function cache(): CacheRepository
    {
        $store = $this->config('token_cache.store');

        $cache = Cache::store(is_string($store) && $store !== '' ? $store : null);

        if (! $cache instanceof CacheRepository) {
            throw new PaymobDomainException('Configured cache repository is not supported.');
        }

        return $cache;
    }

    private function lock(string $name, int $seconds): Lock
    {
        $store = $this->cache()->getStore();

        if (! $store instanceof LockProvider) {
            throw new PaymobDomainException('Configured cache store does not support locks.');
        }

        return $store->lock($name, $seconds);
    }

    private function tokenCacheKey(): string
    {
        $scope = implode('|', [
            strtolower(rtrim($this->baseUrl(), '/')),
            (string) $this->config('token_cache.environment', config('app.env', 'production')),
            hash('sha256', $this->getApiKey()),
        ]);

        return (string) $this->config('token_cache.prefix', 'paymob:auth-token')
            .':'.hash('sha256', $scope);
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

    private function authenticatedRequest(callable $request, ?int $attempts = null): Response
    {
        $attempts ??= $this->retryAttempts();

        try {
            $response = $request($this->authenticate()->token);

            if (in_array($response->status(), [401, 403], true)) {
                $this->forgetCachedAuthentication();
                $response = $request($this->requestAuthentication()->token);
            }

            $response->throw();

            return $response;
        } catch (RequestException $exception) {
            throw new PaymobDomainException(
                $this->requestFailureMessage('Paymob request', $exception, $attempts),
                $exception->response->status(),
                $exception,
            );
        } catch (ConnectionException $exception) {
            throw new PaymobDomainException(
                'Could not connect to Paymob after '.$attempts.' attempts.',
                previous: $exception,
            );
        }
    }

    private function requestFailureMessage(string $operation, RequestException $exception, ?int $attempts = null): string
    {
        $attempts ??= $this->retryAttempts();
        $status = $exception->response->status();

        if ($this->shouldRetry($exception)) {
            return $operation.' failed after '.$attempts.' attempts with HTTP status '.$status.'.';
        }

        return $operation.' failed with HTTP status '.$status.'.';
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
