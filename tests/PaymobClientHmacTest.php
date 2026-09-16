<?php

namespace Paymob\Laravel\Tests;

use Paymob\Laravel\PaymobClient;
use PHPUnit\Framework\TestCase;

class PaymobClientHmacTest extends TestCase
{
    private const HMAC_SECRET = 'test-hmac-secret';

    public function test_check_hmac_accepts_verified_transaction_callback(): void
    {
        $payload = $this->signedPayload($this->payloadObject());

        $this->assertTrue(PaymobClient::checkHmac($payload, self::HMAC_SECRET));
    }

    public function test_check_hmac_rejects_tampered_transaction_callback(): void
    {
        $payload = $this->signedPayload($this->payloadObject());
        $payload['obj']['success'] = false;

        $this->assertFalse(PaymobClient::checkHmac($payload, self::HMAC_SECRET));
    }

    public function test_check_hmac_rejects_missing_signature(): void
    {
        $payload = ['obj' => $this->payloadObject()];

        $this->assertFalse(PaymobClient::checkHmac($payload, self::HMAC_SECRET));
    }

    public function test_check_hmac_can_use_explicit_secret_and_signature(): void
    {
        $object = $this->payloadObject();
        $hmac = $this->hmacFor($object);

        $this->assertTrue(PaymobClient::checkHmac(
            ['obj' => $object],
            self::HMAC_SECRET,
            $hmac,
        ));
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
            .$object['amount_cents']
            .$object['created_at']
            .$object['currency']
            .$this->bool($object['error_occured'])
            .$this->bool($object['has_parent_transaction'])
            .$object['id']
            .$object['integration_id']
            .$this->bool($object['is_3d_secure'])
            .$this->bool($object['is_auth'])
            .$this->bool($object['is_capture'])
            .$this->bool($object['is_refunded'])
            .$this->bool($object['is_standalone_payment'])
            .$this->bool($object['is_voided'])
            .$object['order']['id']
            .$object['owner']
            .$this->bool($object['pending'])
            .$object['source_data']['pan']
            .$object['source_data']['sub_type']
            .$object['source_data']['type']
            .$this->bool($object['success']);

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
