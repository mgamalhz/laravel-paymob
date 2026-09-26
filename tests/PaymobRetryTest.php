<?php

namespace Paymob\Laravel\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Paymob\Laravel\DTO\BillingDataDto;
use Paymob\Laravel\DTO\OrderItemDto;
use Paymob\Laravel\DTO\RegisterOrderData;
use Paymob\Laravel\DTO\RequestPaymentKeyData;
use Paymob\Laravel\Exceptions\PaymobDomainException;
use Paymob\Laravel\PaymobClient;
use ReflectionMethod;
use RuntimeException;

class PaymobRetryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set([
            'paymob.base_url' => 'https://accept.paymob.test',
            'paymob.api_key' => 'test-api-key',
            'paymob.retry_limit' => 3,
            'cache.default' => 'array',
        ]);

        Cache::clear();
        Http::preventStrayRequests();
    }

    public function test_transient_server_error_retries_and_succeeds_without_logging_sensitive_payload(): void
    {
        $authCalls = 0;
        $paymentCalls = 0;

        Http::fake(function (Request $request) use (&$authCalls, &$paymentCalls) {
            if (str_ends_with($request->url(), '/api/auth/tokens')) {
                $authCalls++;

                return Http::response(['token' => 'secret-auth-token']);
            }

            $paymentCalls++;

            return $paymentCalls === 1
                ? Http::response(['message' => 'temporary failure'], 500)
                : Http::response(['token' => 'payment-key-token']);
        });

        Log::shouldReceive('warning')->once()->with(
            'Retrying Paymob request.',
            Mockery::on(fn (array $context): bool => $context['attempt'] === 1
                && $context['max_attempts'] === 3
                && $context['delay_ms'] === 0
                && $context['status'] === 500
                && $context['endpoint'] === '/api/acceptance/payment_keys'
                && ! array_key_exists('api_key', $context)
                && ! array_key_exists('auth_token', $context)
                && ! array_key_exists('token', $context)
            ),
        );

        $response = (new PaymobClient(config('paymob')))->requestPaymentKey($this->paymentKeyData());

        $this->assertSame('payment-key-token', $response->token);
        $this->assertSame(1, $authCalls);
        $this->assertSame(2, $paymentCalls);
    }

    public function test_rate_limit_response_is_retried(): void
    {
        $paymentCalls = 0;

        Http::fake(function (Request $request) use (&$paymentCalls) {
            if (str_ends_with($request->url(), '/api/auth/tokens')) {
                return Http::response(['token' => 'auth-token']);
            }

            $paymentCalls++;

            return $paymentCalls === 1
                ? Http::response(['message' => 'too many requests'], 429, ['Retry-After' => '0'])
                : Http::response(['token' => 'payment-key-token']);
        });

        $response = (new PaymobClient(config('paymob')))->requestPaymentKey($this->paymentKeyData());

        $this->assertSame('payment-key-token', $response->token);
        $this->assertSame(2, $paymentCalls);
    }

    public function test_retry_after_header_controls_delay_when_present(): void
    {
        $this->app['config']->set('paymob.retry_max_delay_ms', 10000);

        $client = new PaymobClient(config('paymob'));
        $method = new ReflectionMethod($client, 'retryDelay');
        $exception = Http::failedRequest(['message' => 'rate limited'], 429, ['Retry-After' => '3']);

        $this->assertSame(3000, $method->invoke($client, 1, $exception));
    }

    public function test_exhausted_transient_error_throws_clear_package_exception(): void
    {
        $this->app['config']->set('paymob.retry_limit', 2);

        $paymentCalls = 0;

        Http::fake(function (Request $request) use (&$paymentCalls) {
            if (str_ends_with($request->url(), '/api/auth/tokens')) {
                return Http::response(['token' => 'auth-token']);
            }

            $paymentCalls++;

            return Http::response(['message' => 'temporary failure'], 500);
        });

        try {
            (new PaymobClient(config('paymob')))->requestPaymentKey($this->paymentKeyData());
            $this->fail('Expected the request to fail.');
        } catch (RuntimeException $exception) {
            $this->assertInstanceOf(PaymobDomainException::class, $exception);
            $this->assertSame('Paymob request failed after 2 attempts with HTTP status 500.', $exception->getMessage());
            $this->assertSame(500, $exception->status());
        }

        $this->assertSame(2, $paymentCalls);
    }

    public function test_validation_error_is_not_retried(): void
    {
        $paymentCalls = 0;

        Http::fake(function (Request $request) use (&$paymentCalls) {
            if (str_ends_with($request->url(), '/api/auth/tokens')) {
                return Http::response(['token' => 'auth-token']);
            }

            $paymentCalls++;

            return Http::response(['message' => 'validation failed'], 422);
        });

        Log::shouldReceive('warning')->never();

        try {
            (new PaymobClient(config('paymob')))->requestPaymentKey($this->paymentKeyData());
            $this->fail('Expected the request to fail.');
        } catch (RuntimeException $exception) {
            $this->assertInstanceOf(PaymobDomainException::class, $exception);
            $this->assertSame('Paymob request failed with HTTP status 422.', $exception->getMessage());
            $this->assertSame(422, $exception->status());
        }

        $this->assertSame(1, $paymentCalls);
    }

    public function test_order_creation_without_idempotency_reference_is_not_retried(): void
    {
        $orderCalls = 0;

        Http::fake(function (Request $request) use (&$orderCalls) {
            if (str_ends_with($request->url(), '/api/auth/tokens')) {
                return Http::response(['token' => 'auth-token']);
            }

            $orderCalls++;

            return $orderCalls === 1
                ? Http::response(['message' => 'temporary failure'], 500)
                : Http::response(['id' => 123, 'created_at' => '2026-09-25T00:00:00Z'], 201);
        });

        try {
            (new PaymobClient(config('paymob')))->registerOrder($this->orderData());
            $this->fail('Expected the request to fail.');
        } catch (RuntimeException $exception) {
            $this->assertInstanceOf(PaymobDomainException::class, $exception);
            $this->assertSame('Paymob request failed after 1 attempts with HTTP status 500.', $exception->getMessage());
        }

        $this->assertSame(1, $orderCalls);
    }

    public function test_order_creation_with_idempotency_reference_can_be_retried(): void
    {
        $orderCalls = 0;

        Http::fake(function (Request $request) use (&$orderCalls) {
            if (str_ends_with($request->url(), '/api/auth/tokens')) {
                return Http::response(['token' => 'auth-token']);
            }

            $orderCalls++;

            return $orderCalls === 1
                ? Http::response(['message' => 'temporary failure'], 500)
                : Http::response(['id' => 123, 'created_at' => '2026-09-25T00:00:00Z'], 201);
        });

        $response = (new PaymobClient(config('paymob')))->registerOrder($this->orderData('order-123'));

        $this->assertSame(123, $response->id);
        $this->assertSame(2, $orderCalls);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://accept.paymob.test/api/ecommerce/orders'
            && $request['merchant_order_id'] === 'order-123'
        );
    }

    private function paymentKeyData(): RequestPaymentKeyData
    {
        return new RequestPaymentKeyData(
            amountCents: 1000,
            currency: 'EGP',
            orderId: 123,
            integrationId: 456,
            billingData: $this->billingData(),
        );
    }

    private function orderData(?string $specialReference = null): RegisterOrderData
    {
        return new RegisterOrderData(
            amount: 1000,
            currency: 'EGP',
            paymentMethodIds: [1],
            items: [
                new OrderItemDto(name: 'Test item', amount: 1000),
            ],
            billingData: $this->billingData(),
            specialReference: $specialReference,
        );
    }

    private function billingData(): BillingDataDto
    {
        return new BillingDataDto(
            firstName: 'Test',
            lastName: 'User',
            email: 'test@example.com',
            phoneNumber: '201000000000',
            street: 'Street',
            building: '1',
            city: 'Cairo',
            country: 'EG',
        );
    }
}
