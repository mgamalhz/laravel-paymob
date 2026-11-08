<?php

namespace Paymob\Laravel\Tests;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Paymob\Laravel\Events\PaymobWebhookReceived;
use Paymob\Laravel\Models\Payment;
use Paymob\Laravel\Models\PaymobWebhookEvent;

class PaymobWebhookTest extends TestCase
{
    private const HMAC_SECRET = 'test-hmac-secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('paymob.hmac_secret', self::HMAC_SECRET);
    }

    public function test_webhook_accepts_valid_hmac(): void
    {
        $payload = $this->signedPayload($this->payloadObject());

        $this->postJson('/', $payload)
            ->assertOk()
            ->assertJson(['message' => 'Webhook received.']);
    }

    public function test_webhook_does_not_record_same_transaction_twice(): void
    {
        $payload = $this->signedPayload($this->payloadObject());

        $this->postJson('/', $payload)
            ->assertOk()
            ->assertJson(['message' => 'Webhook received.']);

        $this->postJson('/', $payload)
            ->assertOk()
            ->assertJson(['message' => 'Webhook already processed.']);

        $this->assertSame(
            1,
            PaymobWebhookEvent::query()
                ->where('transaction_id', 987654321)
                ->count()
        );
    }

    public function test_webhook_rejects_missing_hmac(): void
    {
        $this->postJson('/', ['obj' => $this->payloadObject()])
            ->assertForbidden();
    }

    public function test_webhook_rejects_invalid_hmac(): void
    {
        $payload = $this->signedPayload($this->payloadObject());
        $payload['hmac'] = str_repeat('0', 128);

        $this->postJson('/', $payload)->assertForbidden();
    }

    public function test_modified_callback_has_no_side_effects(): void
    {
        Event::fake();
        Queue::fake();
        $payload = $this->signedPayload($this->payloadObject());
        $payload['obj']['amount_cents']++;
        $this->postJson('/', $payload)->assertForbidden();
        $this->assertSame(0, PaymobWebhookEvent::count());
        Event::assertNotDispatched(PaymobWebhookReceived::class);
        Queue::assertNothingPushed();
    }

    public function test_replay_emits_once_and_stores_no_raw_payload(): void
    {
        Event::fake();
        $payload = $this->signedPayload($this->payloadObject());
        $payload['obj']['billing_data'] = ['email' => 'private@example.test'];
        $this->postJson('/', $payload)->assertOk();
        $this->postJson('/', $payload)->assertOk();
        Event::assertDispatchedTimes(PaymobWebhookReceived::class, 1);
        $this->assertStringNotContainsString('private', PaymobWebhookEvent::firstOrFail()->toJson());
    }

    public function test_terminal_payment_and_amount_mismatch_cannot_change_state(): void
    {
        $payment = Payment::create([
            'paymob_reference' => '111222333', 'amount_cents' => 500, 'status' => 'processing',
        ]);
        $payload = $this->signedPayload($this->payloadObject());
        $this->postJson('/', $payload)->assertStatus(409);
        $this->assertSame(0, PaymobWebhookEvent::count());
        $payment->update(['status' => 'captured', 'amount_cents' => 10000]);
        $this->postJson('/', $payload)->assertOk();
        $this->assertSame('captured', $payment->fresh()->status);
        $this->assertSame(0, PaymobWebhookEvent::count());
    }

    public function test_matching_processing_payment_transitions_once_without_capture(): void
    {
        Queue::fake();
        $payment = Payment::create([
            'paymob_reference' => '111222333', 'amount_cents' => 10000, 'status' => 'processing',
        ]);
        $payload = $this->signedPayload($this->payloadObject());
        $this->postJson('/', $payload)->assertOk();
        $this->postJson('/', $payload)->assertOk();
        $this->assertSame('paid', $payment->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_query_signature_is_supported_and_query_fields_cannot_override_json(): void
    {
        $payload = $this->signedPayload($this->payloadObject());
        $signature = $payload['hmac'];
        unset($payload['hmac']);
        $this->postJson('/?hmac='.$signature.'&obj[success]=0', $payload)->assertOk();
        $this->assertSame('paid', Payment::firstOrFail()->status);
    }

    public function test_pending_or_authorized_callbacks_cannot_mark_paid(): void
    {
        foreach (['pending', 'is_auth', 'is_refunded', 'is_voided', 'error_occured'] as $flag) {
            $object = $this->payloadObject();
            $object[$flag] = true;
            $this->postJson('/', $this->signedPayload($object))->assertOk();
            $this->assertSame('processing', Payment::firstOrFail()->status);
            $this->assertSame(0, PaymobWebhookEvent::count());
        }
    }

    public function test_unknown_payment_does_not_emit_event(): void
    {
        Event::fake();
        $payload = $this->signedPayload($this->payloadObject());
        Payment::query()->delete();
        $this->postJson('/', $payload)->assertNotFound();
        Event::assertNotDispatched(PaymobWebhookReceived::class);
        $this->assertSame(0, PaymobWebhookEvent::count());
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

        return hash_hmac('sha512', $concatenated, config('paymob.hmac_secret'));
    }

    private function payloadObject(): array
    {
        Payment::firstOrCreate(
            ['paymob_reference' => '111222333'],
            ['amount_cents' => 10000, 'status' => 'processing'],
        );

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
