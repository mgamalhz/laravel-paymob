<?php

namespace Paymob\Laravel\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Paymob\Laravel\Contracts\PaymobReceiptRenderer;
use Paymob\Laravel\Jobs\StorePaymobReceipt;
use Paymob\Laravel\Models\Payment;

class StorePaymobReceiptTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();
        Storage::fake('s3');
        $this->app['config']->set('paymob.receipts.disk', 's3');
        $this->app['config']->set('paymob.receipts.prefix', 'paymob/receipts');
    }

    public function test_it_stores_pdf_receipt_and_records_attachment_row(): void
    {
        $payment = Payment::query()->create([
            'paymob_reference' => '987654',
            'transaction_id' => 987654,
            'order_type' => 'Order',
            'order_id' => '123',
            'amount_cents' => 1000,
            'status' => 'captured',
            'response_payload' => ['success' => true],
            'captured_at' => now(),
        ]);

        (new StorePaymobReceipt($payment->id))->handle($this->app->make(PaymobReceiptRenderer::class));

        Storage::disk('s3')->assertExists('paymob/receipts/987654.pdf');

        $this->assertStringStartsWith(
            '%PDF',
            Storage::disk('s3')->get('paymob/receipts/987654.pdf')
        );

        $this->assertDatabaseHas('payment_receipts', [
            'payment_id' => $payment->id,
            'filename' => '987654.pdf',
            'disk' => 's3',
            'key' => 'paymob/receipts/987654.pdf',
        ]);
    }

    public function test_it_does_not_regenerate_existing_active_receipt(): void
    {
        $payment = Payment::query()->create([
            'paymob_reference' => '987654',
            'transaction_id' => 987654,
            'order_type' => 'Order',
            'order_id' => '123',
            'amount_cents' => 1000,
            'status' => 'captured',
            'captured_at' => now(),
        ]);

        (new StorePaymobReceipt($payment->id))->handle($this->app->make(PaymobReceiptRenderer::class));
        (new StorePaymobReceipt($payment->id))->handle($this->app->make(PaymobReceiptRenderer::class));

        $this->assertSame(['paymob/receipts/987654.pdf'], Storage::disk('s3')->allFiles('paymob/receipts'));
        $this->assertSame(1, DB::table('payment_receipts')->where('payment_id', $payment->id)->count());
    }
}
