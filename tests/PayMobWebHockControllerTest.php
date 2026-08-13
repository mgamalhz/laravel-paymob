<?php

namespace Paymob\Laravel\Tests;

use DateTimeImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Paymob\Laravel\DTO\PaymobWebhookPayload;
use Paymob\Laravel\Events\PaymobWebhookReceived;
use Paymob\Laravel\PayMobWebHockController;

class PayMobWebHockControllerTest extends TestCase
{
    private const HMAC_SECRET = 'test-hmac-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/paymob/webhook', PayMobWebHockController::class);
        $this->app['config']->set('paymob.hmac_secret', self::HMAC_SECRET);
    }

    public function test_verified_webhook_dispatches_event_with_typed_payload_once(): void
    {
        Event::fake();

        $response = $this->postJson('/paymob/webhook', $this->signedPayload($this->payloadObject()));

        $response->assertOk();

        Event::assertDispatched(PaymobWebhookReceived::class, function (PaymobWebhookReceived $event): bool {
            return $event->payload instanceof PaymobWebhookPayload
                && $event->payload->transactionId === '987654321'
                && $event->payload->orderId === '111222333'
                && $event->payload->amountCents === 10000
                && $event->payload->status === 'paid'
                && $event->payload->verifiedAt instanceof DateTimeImmutable;
        });

        Event::assertDispatchedTimes(PaymobWebhookReceived::class, 1);
    }

    public function test_unverified_webhook_dispatches_no_event(): void
    {
        Event::fake();

        $payload = $this->signedPayload($this->payloadObject());
        $payload['obj']['amount_cents'] = 20000;

        $response = $this->postJson('/paymob/webhook', $payload);

        $response->assertForbidden();
        Event::assertNotDispatched(PaymobWebhookReceived::class);
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

        return hash_hmac('sha512', $concatenated, self::HMAC_SECRET);
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
                'pan' => '2346',
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
