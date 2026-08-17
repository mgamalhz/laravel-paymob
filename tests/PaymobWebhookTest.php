<?php

namespace Paymob\Laravel\Tests;

class PaymobWebhookTest extends TestCase
{
    private const HMAC_SECRET = 'test-hmac-secret';

    protected function defineEnvironment($app): void
    {
        $app['config']->set('paymob.hmac_secret', self::HMAC_SECRET);
    }

    public function test_webhook_accepts_valid_hmac(): void
    {
        $payload = $this->signedPayload($this->payloadObject());

        $this->postJson('/', $payload)
            ->assertOk()
            ->assertJson(['message' => 'Webhook received.']);
    }

    public function test_webhook_rejects_missing_hmac(): void
    {
        $this->postJson('/', ['obj' => $this->payloadObject()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('hmac');
    }

    public function test_webhook_rejects_invalid_hmac(): void
    {
        $payload = $this->signedPayload($this->payloadObject());
        $payload['hmac'] = str_repeat('0', 128);

        $this->postJson('/', $payload)->assertForbidden();
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
