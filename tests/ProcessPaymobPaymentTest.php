<?php

namespace Paymob\Laravel\Tests;

use BadMethodCallException;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Paymob\Laravel\Contracts\PaymobCapturable;
use Paymob\Laravel\Contracts\PaymobClientContract;
use Paymob\Laravel\DTO\AuthenticationResponseDto;
use Paymob\Laravel\DTO\CapturePaymentResponseDto;
use Paymob\Laravel\DTO\OrderResponseDto;
use Paymob\Laravel\DTO\PaymentKeyResponseDto;
use Paymob\Laravel\DTO\RegisterOrderData;
use Paymob\Laravel\DTO\RequestPaymentKeyData;
use Paymob\Laravel\Jobs\ProcessPaymobPayment;
use Paymob\Laravel\Jobs\StorePaymobReceipt;

class ProcessPaymobPaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();
        Queue::fake([StorePaymobReceipt::class]);
    }

    public function test_job_releases_when_overlap_lock_is_held(): void
    {
        $this->app['config']->set('cache.default', 'array');
        Cache::clear();

        $order = new FakePaymobOrder(id: 123);
        $client = new FakePaymobCaptureClient();

        $this->app->instance(PaymobClientContract::class, $client);

        $job = (new ProcessPaymobPayment($order, 987654, 1000))->withFakeQueueInteractions();
        $middleware = $job->middleware()[0];
        $lockKey = $middleware->getLockKey($job);
        $lock = Cache::lock($lockKey, 30);

        $this->assertTrue($lock->get());

        try {
            $ran = false;

            $middleware->handle($job, function (ProcessPaymobPayment $job) use ($client, &$ran): void {
                $ran = true;
                $job->handle($client);
            });

            $this->assertFalse($ran);
            $this->assertSame(0, $client->captures);
            $this->assertFalse(FakePaymobOrder::$capturedById[123]);
            $job->assertReleased(30);
        } finally {
            $lock->release();
        }
    }

    public function test_job_uses_without_overlapping_middleware(): void
    {
        $job = new ProcessPaymobPayment(new FakePaymobOrder(id: 123), 987654, 1000);

        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }

    public function test_job_returns_early_when_captured_payment_already_exists(): void
    {
        DB::table('payments')->insert([
            'paymob_reference' => '987654',
            'transaction_id' => 987654,
            'order_type' => FakePaymobOrder::class,
            'order_id' => '123',
            'amount_cents' => 1000,
            'status' => 'captured',
            'captured_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order = new FakePaymobOrder(id: 123);
        $client = new FakePaymobCaptureClient();

        (new ProcessPaymobPayment($order, 987654, 1000))->handle($client);

        $this->assertSame(0, $client->captures);
        $this->assertSame(1, DB::table('payments')->where('paymob_reference', '987654')->count());
    }

    public function test_replayed_transaction_is_not_captured_twice(): void
    {
        $order = new FakePaymobOrder(id: 123);
        $client = new FakePaymobCaptureClient();

        (new ProcessPaymobPayment($order, 987654, 1000))->handle($client);
        (new ProcessPaymobPayment($order, 987654, 1000))->handle($client);

        $this->assertSame(1, $client->captures);
        $this->assertSame(1, DB::table('payments')->where('paymob_reference', '987654')->count());
        $this->assertDatabaseHas('payments', [
            'paymob_reference' => '987654',
            'transaction_id' => 987654,
            'status' => 'captured',
        ]);
    }

    public function test_concurrent_captures_create_one_payment_and_one_charge(): void
    {
        $order = new FakePaymobOrder(id: 123);
        $client = new FakeConcurrentPaymobCaptureClient($order);

        (new ProcessPaymobPayment($order, 987654, 1000))->handle($client);

        $this->assertSame(1, $client->captures);
        $this->assertSame(1, $client->nestedAttempts);
        $this->assertSame(1, DB::table('payments')->where('paymob_reference', '987654')->count());
        $this->assertDatabaseHas('payments', [
            'paymob_reference' => '987654',
            'status' => 'captured',
            'amount_cents' => 1000,
        ]);
        $this->assertTrue(FakePaymobOrder::$capturedById[123]);
        Queue::assertPushed(StorePaymobReceipt::class);
    }
}

final class FakePaymobOrder implements PaymobCapturable
{
    /**
     * @var array<int, bool>
     */
    public static array $capturedById = [];

    public bool $captured = false;

    public ?string $payment_status = null;

    public ?string $captured_at = null;

    public function __construct(public int $id)
    {
        self::$capturedById[$this->id] = false;
    }

    public function isPaymobCaptured(): bool
    {
        return self::$capturedById[$this->id];
    }

    public function markPaymobCaptured(CapturePaymentResponseDto $response): void
    {
        $this->captured = true;
        $this->payment_status = 'captured';
        $this->captured_at = 'now';
        self::$capturedById[$this->id] = true;
    }
}

final class FakePaymobCaptureClient implements PaymobClientContract
{
    public int $captures = 0;

    public ?int $lastTransactionId = null;

    public ?int $lastAmountCents = null;

    public function authenticate(): AuthenticationResponseDto
    {
        throw new BadMethodCallException('Not used in this test.');
    }

    public function registerOrder(RegisterOrderData $data): OrderResponseDto
    {
        throw new BadMethodCallException('Not used in this test.');
    }

    public function requestPaymentKey(RequestPaymentKeyData $data): PaymentKeyResponseDto
    {
        throw new BadMethodCallException('Not used in this test.');
    }

    public function capture(int $transactionId, int $amountCents): CapturePaymentResponseDto
    {
        $this->captures++;
        $this->lastTransactionId = $transactionId;
        $this->lastAmountCents = $amountCents;

        return new CapturePaymentResponseDto([
            'id' => $transactionId,
            'amount_cents' => $amountCents,
            'success' => true,
        ]);
    }
}

final class FakeConcurrentPaymobCaptureClient implements PaymobClientContract
{
    public int $captures = 0;

    public int $nestedAttempts = 0;

    public function __construct(private FakePaymobOrder $order)
    {
    }

    public function authenticate(): AuthenticationResponseDto
    {
        throw new BadMethodCallException('Not used in this test.');
    }

    public function registerOrder(RegisterOrderData $data): OrderResponseDto
    {
        throw new BadMethodCallException('Not used in this test.');
    }

    public function requestPaymentKey(RequestPaymentKeyData $data): PaymentKeyResponseDto
    {
        throw new BadMethodCallException('Not used in this test.');
    }

    public function capture(int $transactionId, int $amountCents): CapturePaymentResponseDto
    {
        $this->captures++;

        if ($this->nestedAttempts === 0) {
            $this->nestedAttempts++;

            (new ProcessPaymobPayment($this->order, $transactionId, $amountCents))->handle($this);
        }

        return new CapturePaymentResponseDto([
            'id' => $transactionId,
            'amount_cents' => $amountCents,
            'success' => true,
        ]);
    }
}
