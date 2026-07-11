<?php

namespace Paymob\Laravel\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Paymob\Laravel\DTO\AuthenticationResponseDto;
use Paymob\Laravel\DTO\BillingDataDto;
use Paymob\Laravel\DTO\IntentionResponseDto;
use Paymob\Laravel\DTO\OrderItemDto;
use Paymob\Laravel\DTO\PaymentKeyResponseDto;
use Paymob\Laravel\DTO\RegisterOrderData;
use Paymob\Laravel\DTO\RequestPaymentKeyData;
use Paymob\Laravel\PaymobClient;

class PayMobTest extends TestCase
{
    public function test_authenticate_function(): void
    {
        $this->app['config']->set([
            'paymob.base_url' => 'https://accept.paymob.com',
            'paymob.api_key' => 'test-api-key',
            'cache.default' => 'array',
        ]);
        $baseUrl = config('paymob.base_url');
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

        $cachedDto = Cache::get('paymob_token');

        $this->assertInstanceOf(
            AuthenticationResponseDto::class,
            $cachedDto
        );

        $this->assertSame(
            'fake-paymob-token',
            $cachedDto->token
        );
    }

    public function test_register_order_fail(): void
    {
        $this->app['config']->set([
            'paymob.base_url' => 'https://accept.paymob.com',
            'paymob.api_key' => 'test-api-key',
            'paymob.secret_key' => 'test-secret-key',
            'cache.default' => 'array',
        ]);

        Http::fake([
            'https://accept.paymob.com/v1/intention/' => Http::response([
                'id' => '01HYZK7XW3J5P8M5R4Q3T9V0E1',
                'client_secret' => 'egy_csk_01HYZK7XW3J5P8M5R4Q3T9V0E1',
                'intention_order_id' => 987654321,
                'amount' => 1000,
                'currency' => 'EGP',
                'status' => 'intended',
                'payment_methods' => [],
                'created' => '2026-05-24T14:32:11Z',
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
                && $request->url() === 'https://accept.paymob.com/v1/intention/'
                && $request->hasHeader('Authorization', 'Token test-secret-key')
                && $request['amount'] === 1000
                && $request['currency'] === 'EGP';
        });

        $this->assertInstanceOf(IntentionResponseDto::class, $response);
        $this->assertSame('egy_csk_01HYZK7XW3J5P8M5R4Q3T9V0E1', $response->clientSecret);
    }

    public function test_request_payment_key_uses_cached_auth_token_and_returns_dto(): void
    {
        $this->app['config']->set([
            'paymob.base_url' => 'https://accept.paymob.com',
            'paymob.api_key' => 'test-api-key',
            'cache.default' => 'array',
        ]);

        Cache::put('paymob_token', new AuthenticationResponseDto('fake-paymob-token'));

        Http::fake([
            'https://accept.paymob.com/api/acceptance/payment_keys' => Http::response([
                'token' => 'fake-payment-key-token',
            ], 200),
        ]);

        $client = $this->app->make(PaymobClient::class);

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
                && $request->url() === 'https://accept.paymob.com/api/acceptance/payment_keys'
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
}
