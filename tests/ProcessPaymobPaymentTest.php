<?php

namespace Paymob\Laravel\Tests;

use BadMethodCallException;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Paymob\Laravel\Contracts\PaymobClientContract;
use Paymob\Laravel\DTO\AuthenticationResponseDto;
use Paymob\Laravel\DTO\CapturePaymentResponseDto;
use Paymob\Laravel\DTO\IntentionResponseDto;
use Paymob\Laravel\DTO\PaymentKeyResponseDto;
use Paymob\Laravel\DTO\RegisterOrderData;
use Paymob\Laravel\DTO\RequestPaymentKeyData;
use Paymob\Laravel\Jobs\ProcessPaymobPayment;

class ProcessPaymobPaymentTest extends TestCase
{
    public function test_dispatching_same_job_twice_captures_once(): void
    {
        $this->app['config']->set('cache.default', 'array');
        Cache::clear();

        $order = new FakePaymobOrder(id: 123);
        $client = new FakePaymobCaptureClient();

        $this->app->instance(PaymobClientContract::class, $client);

        ProcessPaymobPayment::dispatchSync($order, 987654, 1000);
        ProcessPaymobPayment::dispatchSync($order, 987654, 1000);

        $this->assertSame(1, $client->captures);
        $this->assertTrue(FakePaymobOrder::$capturedById[123]);
        $this->assertSame(987654, $client->lastTransactionId);
        $this->assertSame(1000, $client->lastAmountCents);
    }

    public function test_job_uses_without_overlapping_middleware(): void
    {
        $job = new ProcessPaymobPayment(new FakePaymobOrder(id: 123), 987654, 1000);

        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }
}

final class FakePaymobOrder
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

    public function isCaptured(): bool
    {
        return self::$capturedById[$this->id];
    }

    public function markCaptured(CapturePaymentResponseDto $response): void
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

    public function registerOrder(RegisterOrderData $data): IntentionResponseDto
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
