<?php

namespace Paymob\Laravel\Tests;

use BadMethodCallException;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Paymob\Laravel\Contracts\PaymobCapturable;
use Paymob\Laravel\Contracts\PaymobClientContract;
use Paymob\Laravel\DTO\AuthenticationResponseDto;
use Paymob\Laravel\DTO\CapturePaymentResponseDto;
use Paymob\Laravel\DTO\OrderResponseDto;
use Paymob\Laravel\DTO\PaymentKeyResponseDto;
use Paymob\Laravel\DTO\RegisterOrderData;
use Paymob\Laravel\DTO\RequestPaymentKeyData;
use Paymob\Laravel\Jobs\ProcessPaymobPayment;

class ProcessPaymobPaymentTest extends TestCase
{
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
