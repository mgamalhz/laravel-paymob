<?php

namespace Paymob\Laravel\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Paymob\Laravel\DTO\AuthenticationResponseDto;
use Paymob\Laravel\DTO\BillingDataDto;
use Paymob\Laravel\DTO\OrderItemDto;
use Paymob\Laravel\DTO\RegisterOrderData;
use Paymob\Laravel\DTO\RequestPaymentKeyData;
use Paymob\Laravel\PaymobClient;
use Paymob\Laravel\Support\PaymobLogEvents;

class PaymobLoggingTest extends TestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logPath = __DIR__ . '/logs/paymob-structured.log';
        @unlink($this->logPath);

        $this->app['config']->set('logging.channels.paymob-structured-test', [
            'driver' => 'single',
            'path' => $this->logPath,
            'level' => 'debug',
        ]);
        $this->app['config']->set('paymob.logging.channel', 'paymob-structured-test');

        Log::forgetChannel('paymob-structured-test');
    }

    public function test_outbound_logs_never_include_sensitive_values(): void
    {
        $baseUrl = config('paymob.base_url');

        $this->app['config']->set([
            'paymob.base_url' => $baseUrl,
            'paymob.api_key' => 'sk_test_sensitive_api_key',
            'paymob.logging.include_payloads' => true,
        ]);

        Cache::put('paymob_token', new AuthenticationResponseDto('auth_token_sensitive_value'));

        Http::fake([
            $baseUrl . '/api/acceptance/payment_keys' => Http::response([
                'token' => 'payment_key_sensitive_value',
            ], 200),
        ]);

        $client = $this->app->make(PaymobClient::class)
            ->withCorrelationId('corr-123');

        $client->requestPaymentKey(new RequestPaymentKeyData(
            amountCents: 1000,
            currency: 'EGP',
            orderId: 987654321,
            integrationId: 123456,
            billingData: new BillingDataDto(
                firstName: 'Sensitive',
                lastName: 'Customer',
                email: 'sensitive.customer@example.com',
                phoneNumber: '201000000000',
                street: 'Sensitive Street',
                building: '1',
                city: 'Cairo',
                country: 'EG'
            ),
        ));

        Http::assertSent(function (Request $request): bool {
            return $request->hasHeader('X-Correlation-ID', 'corr-123');
        });

        $logs = $this->logs();

        $this->assertStringContainsString(PaymobLogEvents::REQUEST, $logs);
        $this->assertStringContainsString(PaymobLogEvents::RESPONSE, $logs);
        $this->assertStringContainsString('corr-123', $logs);
        $this->assertStringNotContainsString('auth_token_sensitive_value', $logs);
        $this->assertStringNotContainsString('payment_key_sensitive_value', $logs);
        $this->assertStringNotContainsString('sensitive.customer@example.com', $logs);
        $this->assertStringNotContainsString('201000000000', $logs);
        $this->assertStringNotContainsString('Sensitive Street', $logs);
        $this->assertStringContainsString('[redacted]', $logs);
    }

    public function test_outbound_logs_do_not_include_payloads_by_default(): void
    {
        $baseUrl = config('paymob.base_url');

        $this->app['config']->set([
            'paymob.base_url' => $baseUrl,
            'paymob.api_key' => 'sk_test_sensitive_api_key',
            'paymob.logging.include_payloads' => false,
        ]);

        Cache::clear();

        Http::fake([
            $baseUrl . '/api/auth/tokens' => Http::response([
                'token' => 'auth_token_sensitive_value',
            ], 200),
            $baseUrl . '/api/ecommerce/orders' => Http::response([
                'id' => 987654321,
                'created_at' => '2026-05-24T14:32:11Z',
            ], 201),
        ]);

        $this->app->make(PaymobClient::class)->registerOrder(new RegisterOrderData(
            amount: 1000,
            currency: 'EGP',
            paymentMethodIds: [1],
            items: [
                new OrderItemDto(name: 'Test item', amount: 1000, quantity: 1),
            ],
            billingData: new BillingDataDto(
                firstName: 'Sensitive',
                lastName: 'Customer',
                email: 'sensitive.customer@example.com',
                phoneNumber: '201000000000',
                street: 'Sensitive Street',
                building: '1',
                city: 'Cairo',
                country: 'EG'
            ),
            specialReference: 'merchant-ref-123',
        ));

        $logs = $this->logs();

        $this->assertStringContainsString('merchant-ref-123', $logs);
        $this->assertStringNotContainsString('request_payload', $logs);
        $this->assertStringNotContainsString('auth_token_sensitive_value', $logs);
        $this->assertStringNotContainsString('sensitive.customer@example.com', $logs);
    }

    public function test_webhook_logs_never_include_sensitive_values(): void
    {
        $this->artisan('migrate')->run();

        $this->app['config']->set([
            'paymob.hmac_secret' => 'hmac_secret_sensitive_value',
            'paymob.logging.include_payloads' => true,
        ]);

        $payload = $this->signedPayload($this->payloadObject());

        $this->postJson('/', $payload, ['X-Correlation-ID' => 'webhook-corr-123'])
            ->assertOk();

        $logs = $this->logs();

        $this->assertStringContainsString(PaymobLogEvents::WEBHOOK_RECEIVED, $logs);
        $this->assertStringContainsString(PaymobLogEvents::WEBHOOK_ACCEPTED, $logs);
        $this->assertStringContainsString('webhook-corr-123', $logs);
        $this->assertStringNotContainsString($payload['hmac'], $logs);
        $this->assertStringNotContainsString('4111111111111111', $logs);
        $this->assertStringContainsString('[redacted]', $logs);
    }

    private function logs(): string
    {
        Log::channel('paymob-structured-test')->getLogger()->close();

        return file_exists($this->logPath) ? (string) file_get_contents($this->logPath) : '';
    }

    private function signedPayload(array $object): array
    {
        return [
            'type' => 'TRANSACTION',
            'obj' => $object,
            'hmac' => $this->hmacFor($object),
        ];
    }

    private function hmacFor(array $object): string
    {
        $concatenated = ''
            . $object['amount_cents']
            . $object['created_at']
            . $object['currency']
            . $this->bool($object['error_occured'])
            . $this->bool($object['has_parent_transaction'])
            . $object['id']
            . $object['integration_id']
            . $this->bool($object['is_3d_secure'])
            . $this->bool($object['is_auth'])
            . $this->bool($object['is_capture'])
            . $this->bool($object['is_refunded'])
            . $this->bool($object['is_standalone_payment'])
            . $this->bool($object['is_voided'])
            . $object['order']['id']
            . $object['owner']
            . $this->bool($object['pending'])
            . $object['source_data']['pan']
            . $object['source_data']['sub_type']
            . $object['source_data']['type']
            . $this->bool($object['success']);

        return hash_hmac('sha512', $concatenated, config('paymob.hmac_secret'));
    }

    private function payloadObject(): array
    {
        return [
            'amount_cents' => 10000,
            'created_at' => '2026-08-08T20:30:00.000000',
            'currency' => 'EGP',
            'error_occured' => false,
            'has_parent_transaction' => false,
            'id' => 987654321,
            'integration_id' => 123456,
            'is_3d_secure' => true,
            'is_auth' => false,
            'is_capture' => false,
            'is_refunded' => false,
            'is_standalone_payment' => true,
            'is_voided' => false,
            'order' => [
                'id' => 111222333,
            ],
            'owner' => 778899,
            'pending' => false,
            'source_data' => [
                'pan' => '4111111111111111',
                'sub_type' => 'MasterCard',
                'type' => 'card',
            ],
            'success' => true,
        ];
    }

    private function bool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
