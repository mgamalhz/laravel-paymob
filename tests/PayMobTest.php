<?php

namespace Paymob\Laravel\Tests;

use Illuminate\Support\Facades\Http;
use Paymob\Laravel\DTO\AuthenticationResponseDto;
use Paymob\Laravel\DTO\BillingDataDto;
use Paymob\Laravel\DTO\IntentionResponseDto;
use Paymob\Laravel\DTO\OrderItemDto;
use Paymob\Laravel\DTO\RegisterOrderData;
use Paymob\Laravel\PaymobClient;

class PayMobTest extends TestCase
{
    public function test_authenticate_function()
    {
        $this->app['config']->set('paymob.api_key', 'test-api-key');

        Http::fake([
            'https://accept.paymob.com/api/auth/tokens' => Http::response([
                'token' => 'AUTH_TOKEN_123',
            ], 201),
        ]);

        $client = $this->app->make(PaymobClient::class);
        $response = $client->authenticate();

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://accept.paymob.com/api/auth/tokens'
                && $request['api_key'] === 'test-api-key';
        });

        $this->assertInstanceOf(AuthenticationResponseDto::class, $response);
        $this->assertSame('AUTH_TOKEN_123', $response->token);
    }

    public function test_register_order_fail(): void
    {
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
                && $request['amount'] === 1000
                && $request['currency'] === 'EGP';
        });

        $this->assertInstanceOf(IntentionResponseDto::class, $response);
        $this->assertSame('egy_csk_01HYZK7XW3J5P8M5R4Q3T9V0E1', $response->clientSecret);
    }
}
