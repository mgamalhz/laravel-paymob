<?php

namespace Paymob\Laravel\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Paymob\Laravel\DTO\AuthenticationResponseDto;
use Paymob\Laravel\DTO\BillingDataDto;
use Paymob\Laravel\DTO\OrderResponseDto;
use Paymob\Laravel\DTO\OrderItemDto;
use Paymob\Laravel\DTO\PaymentKeyResponseDto;
use Paymob\Laravel\DTO\RegisterOrderData;
use Paymob\Laravel\DTO\RequestPaymentKeyData;
use Paymob\Laravel\PaymobClient;

class PayMobTest extends TestCase
{
    public function test_authenticate_function(): void
    {
        $baseUrl = config('paymob.base_url');

        $this->app['config']->set([
            'paymob.base_url' => $baseUrl,
            'paymob.api_key' => 'test-api-key',
            'cache.default' => 'array',
        ]);

        $authUrl = $baseUrl . '/api/auth/tokens';
        $apiKey = config('paymob.api_key');
        Cache::clear();

        Http::preventStrayRequests();

        Http::fake([
            $authUrl => Http::response([
                'token' => 'fake-paymob-token',
            ], 200),
        ]);

        $client = app(PaymobClient::class);

        $response = $client->authenticate();
        Http::assertSent(function (Request $request) use ($authUrl, $apiKey): bool {
            return $request->method() === 'POST'
                && $request->url() === $authUrl
                && $request->data() === [
                    'api_key' => $apiKey,
                ];
        });

        Http::assertSentCount(1);

        $this->assertInstanceOf(
            AuthenticationResponseDto::class,
            $response
        );

        $this->assertSame(
            'fake-paymob-token',
            $response->token
        );

        $cachedDto = Cache::get($this->tokenCacheKey($client));

        $this->assertInstanceOf(
            AuthenticationResponseDto::class,
            $cachedDto
        );

        $this->assertSame(
            'fake-paymob-token',
            $cachedDto->token
        );
    }

    public function test_register_order_uses_classic_order_endpoint(): void
    {
        $baseUrl = config('paymob.base_url');

        $this->app['config']->set([
            'paymob.base_url' => $baseUrl,
            'paymob.api_key' => 'test-api-key',
            'cache.default' => 'array',
        ]);

        Cache::clear();

        Http::fake([
            $baseUrl . '/api/auth/tokens' => Http::response([
                'token' => 'fake-paymob-token',
            ], 200),
            $baseUrl . '/api/ecommerce/orders' => Http::response([
                'id' => 987654321,
                'created_at' => '2026-05-24T14:32:11Z',
            ], 201),
        ]);

        $client = $this->app->make(PaymobClient::class);

        $data = new RegisterOrderData(
            amount: 1000,
            currency: 'EGP',
            paymentMethodIds: [1],
            items: [
                new OrderItemDto(name: 'Test item', amount: 1000, quantity: 1),
            ],
            billingData: new BillingDataDto(
                firstName: 'Test',
                lastName: 'User',
                email: 'test@example.com',
                phoneNumber: '201000000000',
                street: 'Test Street',
                building: '1',
                city: 'Cairo',
                country: 'EG'
            ),
        );

        $response = $client->registerOrder($data);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === config('paymob.base_url') . '/api/auth/tokens'
                && $request['api_key'] === 'test-api-key';
        });

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === config('paymob.base_url') . '/api/ecommerce/orders'
                && $request['auth_token'] === 'fake-paymob-token'
                && $request['delivery_needed'] === false
                && $request['amount_cents'] === 1000
                && $request['currency'] === 'EGP';
        });

        Http::assertSentCount(2);

        $this->assertInstanceOf(OrderResponseDto::class, $response);
        $this->assertSame(987654321, $response->id);
    }

    public function test_request_payment_key_uses_cached_auth_token_and_returns_dto(): void
    {
        $baseUrl = config('paymob.base_url');

        $this->app['config']->set([
            'paymob.base_url' => $baseUrl,
            'paymob.api_key' => 'test-api-key',
            'cache.default' => 'array',
        ]);

        Http::fake([
            $baseUrl . '/api/auth/tokens' => Http::response([
                'token' => 'fake-paymob-token',
            ], 200),
            $baseUrl . '/api/acceptance/payment_keys' => Http::response([
                'token' => 'fake-payment-key-token',
            ], 200),
        ]);

        $client = $this->app->make(PaymobClient::class);

        $client->authenticate();
        Http::fake([
            $baseUrl . '/api/acceptance/payment_keys' => Http::response([
                'token' => 'fake-payment-key-token',
            ], 200),
        ]);

        $response = $client->requestPaymentKey(new RequestPaymentKeyData(
            amountCents: 1000,
            currency: 'EGP',
            orderId: 987654321,
            integrationId: 123456,
            billingData: new BillingDataDto(
                firstName: 'Test',
                lastName: 'User',
                email: 'test@example.com',
                phoneNumber: '201000000000',
                street: 'Test Street',
                building: '1',
                city: 'Cairo',
                country: 'EG'
            ),
        ));

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === config('paymob.base_url') . '/api/acceptance/payment_keys'
                && $request['auth_token'] === 'fake-paymob-token'
                && $request['amount_cents'] === 1000
                && $request['currency'] === 'EGP'
                && $request['order_id'] === 987654321
                && $request['integration_id'] === 123456
                && $request['billing_data']['first_name'] === 'Test'
                && $request['billing_data']['phone_number'] === '201000000000';
        });

        Http::assertSentCount(1);

        $this->assertInstanceOf(PaymentKeyResponseDto::class, $response);
        $this->assertSame('fake-payment-key-token', $response->token);
    }

    private function tokenCacheKey(PaymobClient $client): string
    {
        $method = new \ReflectionMethod($client, 'tokenCacheKey');

        return $method->invoke($client);
    }
}
