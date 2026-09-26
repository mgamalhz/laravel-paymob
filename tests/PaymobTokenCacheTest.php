<?php

namespace Paymob\Laravel\Tests;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Paymob\Laravel\DTO\AuthenticationResponseDto;
use Paymob\Laravel\DTO\BillingDataDto;
use Paymob\Laravel\DTO\RequestPaymentKeyData;
use Paymob\Laravel\Exceptions\PaymobAuthenticationException;
use Paymob\Laravel\Exceptions\PaymobDomainException;
use Paymob\Laravel\PaymobClient;
use RuntimeException;

class PaymobTokenCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set([
            'paymob.base_url' => 'https://accept.paymob.test',
            'paymob.api_key' => 'account-a-key',
            'paymob.token_cache.environment' => 'testing',
            'paymob.token_cache.ttl_seconds' => 60,
            'cache.default' => 'array',
        ]);

        Cache::clear();
        Http::preventStrayRequests();
    }

    public function test_cache_hit_reuses_token_without_authentication_call(): void
    {
        Http::fake([
            '*/api/auth/tokens' => Http::response(['token' => 'shared-token']),
        ]);

        $first = new PaymobClient(config('paymob'));
        $second = new PaymobClient(config('paymob'));

        $this->assertSame('shared-token', $first->authenticate()->token);
        $this->assertSame('shared-token', $second->authenticate()->token);
        Http::assertSentCount(1);
    }

    public function test_expired_token_is_refreshed(): void
    {
        $this->app['config']->set('paymob.token_cache.ttl_seconds', 1);
        Http::fakeSequence('*/api/auth/tokens')
            ->push(['token' => 'first-token'])
            ->push(['token' => 'second-token']);

        $client = new PaymobClient(config('paymob'));
        $this->assertSame('first-token', $client->authenticate()->token);

        sleep(2);

        $this->assertSame('second-token', $client->authenticate()->token);
        Http::assertSentCount(2);
    }

    public function test_worker_that_waited_for_lock_uses_token_refreshed_by_other_worker(): void
    {
        $repository = Mockery::mock(Repository::class);
        $lock = Mockery::mock(Lock::class);
        $refreshed = new AuthenticationResponseDto('other-worker-token');

        Cache::shouldReceive('store')->andReturn($repository);
        $repository->shouldReceive('get')->twice()->andReturn(null, $refreshed);
        $repository->shouldReceive('lock')->once()->andReturn($lock);
        $lock->shouldReceive('block')->once()->andReturnUsing(
            fn (int $seconds, callable $callback) => $callback(),
        );

        $client = new PaymobClient(config('paymob'));

        $this->assertSame('other-worker-token', $client->authenticate()->token);
        Http::assertNothingSent();
    }

    public function test_cache_outage_falls_back_to_an_uncached_authentication_call(): void
    {
        $repository = Mockery::mock(Repository::class);
        Cache::shouldReceive('store')->andReturn($repository);
        $repository->shouldReceive('get')->once()->andThrow(new RuntimeException('cache unavailable'));
        $repository->shouldReceive('lock')->once()->andThrow(new RuntimeException('cache unavailable'));
        $repository->shouldReceive('put')->once()->andThrow(new RuntimeException('cache unavailable'));

        Http::fake([
            '*/api/auth/tokens' => Http::response(['token' => 'uncached-token']),
        ]);

        $client = new PaymobClient(config('paymob'));

        $this->assertSame('uncached-token', $client->authenticate()->token);
        Http::assertSentCount(1);
    }

    public function test_authentication_error_does_not_expose_response_token(): void
    {
        Http::fake([
            '*/api/auth/tokens' => Http::response([
                'token' => 'must-never-appear',
                'message' => 'failure',
            ], 500),
        ]);

        $client = new PaymobClient(config('paymob'));

        try {
            $client->authenticate();
            $this->fail('Expected authentication to fail.');
        } catch (RuntimeException $exception) {
            $this->assertInstanceOf(PaymobAuthenticationException::class, $exception);
            $this->assertSame('Paymob authentication failed after 5 attempts with HTTP status 500.', $exception->getMessage());
            $this->assertSame(500, $exception->status());
            $this->assertStringNotContainsString('must-never-appear', $exception->getMessage());
        }
    }

    public function test_authentication_failure_refreshes_once_and_retries_request(): void
    {
        $authCalls = 0;
        $paymentCalls = 0;
        Http::fake(function (Request $request) use (&$authCalls, &$paymentCalls) {
            if (str_ends_with($request->url(), '/api/auth/tokens')) {
                $authCalls++;

                return Http::response([
                    'token' => $authCalls === 1 ? 'stale-token' : 'fresh-token',
                ]);
            }

            $paymentCalls++;

            return $paymentCalls === 1
                ? Http::response(['message' => 'invalid token'], 401)
                : Http::response(['token' => 'payment-token'], 200);
        });

        $client = new PaymobClient(config('paymob'));
        $client->authenticate();

        $response = $client->requestPaymentKey($this->paymentKeyData());

        $this->assertSame('payment-token', $response->token);
        $this->assertSame(2, $authCalls);
        $this->assertSame(2, $paymentCalls);
        Http::assertSentCount(4);
        Http::assertSent(fn (Request $request): bool =>
            $request->url() === 'https://accept.paymob.test/api/acceptance/payment_keys'
            && $request['auth_token'] === 'fresh-token'
        );
    }

    public function test_repeated_authentication_failure_is_not_retried_forever_or_leaked(): void
    {
        $authCalls = 0;
        $paymentCalls = 0;
        Http::fake(function (Request $request) use (&$authCalls, &$paymentCalls) {
            if (str_ends_with($request->url(), '/api/auth/tokens')) {
                $authCalls++;

                return Http::response([
                    'token' => $authCalls === 1 ? 'secret-stale-token' : 'secret-fresh-token',
                ]);
            }

            $paymentCalls++;

            return Http::response(['message' => 'invalid token'], 401);
        });

        $client = new PaymobClient(config('paymob'));
        $client->authenticate();

        try {
            $client->requestPaymentKey($this->paymentKeyData());
            $this->fail('Expected the request to fail.');
        } catch (RuntimeException $exception) {
            $this->assertInstanceOf(PaymobDomainException::class, $exception);
            $this->assertSame('Paymob request failed with HTTP status 401.', $exception->getMessage());
            $this->assertSame(401, $exception->status());
            $this->assertStringNotContainsString('secret-', $exception->getMessage());
        }

        $this->assertSame(2, $authCalls);
        $this->assertSame(2, $paymentCalls);
        Http::assertSentCount(4);
    }

    private function paymentKeyData(): RequestPaymentKeyData
    {
        return new RequestPaymentKeyData(
            amountCents: 1000,
            currency: 'EGP',
            orderId: 123,
            integrationId: 456,
            billingData: new BillingDataDto(
                firstName: 'Test',
                lastName: 'User',
                email: 'test@example.com',
                phoneNumber: '201000000000',
                street: 'Street',
                building: '1',
                city: 'Cairo',
                country: 'EG',
            ),
        );
    }
}
