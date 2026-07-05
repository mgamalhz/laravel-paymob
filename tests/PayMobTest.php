<?php

namespace Paymob\Laravel\Tests;

use Illuminate\Support\Facades\Http;
use Paymob\Laravel\DTO\AuthenticationResponseDto;
use Paymob\Laravel\DTO\BillingDataDto;
use Paymob\Laravel\DTO\OrderItemDto;
use Paymob\Laravel\DTO\RegisterOrderData;
use Paymob\Laravel\PaymobClient;

class PayMobTest extends TestCase
{
    public function test_authenticate_function()
    {
        Http::fake([
            'https://accept.paymob.com/api/auth/tokens' => Http::response([
                'token' => 'AUTH_TOKEN_123',
            ], 201),
        ]);

        $client = $this->app->make(PaymobClient::class);
        $response = $client->authenticate();
        $this->assertInstanceOf(AuthenticationResponseDto::class, $response);
        $this->assertSame('AUTH_TOKEN_123', $response->token);
    }

    public function test_register_order_fail(): void
    {
        Http::fake([
            'https://accept.paymob.com/api/auth/tokens' => Http::response([
                'token' => 'AUTH_TOKEN_FROM_STEP_1',
            ], 201),
            'https://accept.paymob.com/api/ecommerce/orders' => Http::response([
                'id' => 1,
            ], 201),
        ]);

        $client = $this->app->make(PaymobClient::class);
        $response = $client->authenticate();

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

        try {
            $client->registerOrder($data);
        } catch (\Throwable) {
        }

        Http::assertSent(function ($request) use ($response) {
            return $request->url() === 'https://accept.paymob.com/api/ecommerce/orders'
                && $request->header('Authorization') === ["Bearer {$response->token}"];
        });
    }
}
