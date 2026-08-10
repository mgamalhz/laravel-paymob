# Store Payment Receipts To S3

Use this as a manual implementation checklist. The goal is: after a Paymob webhook is verified, queue receipt generation, write one private object through Laravel Storage, and save the receipt disk/key in a separate receipt attachment row.

## 1. Add Receipt Config

Edit `config/paymob.php` and add a `receipts` section.

```php
'receipts' => [
    'disk' => env('PAYMOB_RECEIPTS_DISK', 's3'),
    'prefix' => env('PAYMOB_RECEIPTS_PREFIX', 'paymob/receipts'),
],
```

Rules:

- Do not add bucket names, AWS keys, regions, clients, or SDK config here.
- The host app owns the disk in `config/filesystems.php`.
- The package only calls `Storage::disk(config('paymob.receipts.disk'))`.

## 2. Add Payment Receipts Table

Create a new migration instead of editing old published migrations.

Keep receipt attachments separate from the payment record so deleting or regenerating an attachment does not delete the payment itself.

```php
Schema::create('payment_receipts', function (Blueprint $table): void {
    $table->id();
    $table->foreignIdFor(Payment::class)->constrained()->cascadeOnDelete();
    $table->string('disk');
    $table->string('filename');
    $table->string('key');
    $table->timestamp('stored_at')->nullable();
    $table->softDeletes();
    $table->timestamps();

    $table->index('payment_id');
    $table->index(['disk', 'key']);
});
```

Then add `src/Models/PaymentReceipt.php` and update `Payment`:

```php
public function receipt()
{
    return $this->hasOne(PaymentReceipt::class);
}
```

## 3. Create A Receipt Renderer

Keep rendering separate from storage. Add a small service, for example:

`src/Receipts/PaymobReceiptRenderer.php`

Install DomPDF:

```bash
composer require barryvdh/laravel-dompdf
```

Example shape:

```php
namespace Paymob\Laravel\Receipts;

use Barryvdh\DomPDF\PDF;
use Paymob\Laravel\Contracts\PaymobReceiptRenderer;
use Paymob\Laravel\Models\Payment;

class DompdfPaymobReceiptRenderer implements PaymobReceiptRenderer
{
    public function __construct(private PDF $pdf)
    {
    }

    public function render(Payment $payment): string
    {
        return $this->pdf
            ->loadView('paymob::receipts.payment', ['payment' => $payment])
            ->output();
    }
}
```

Keep DomPDF inside the renderer. The storage job should depend on `PaymobReceiptRenderer` and only receive bytes.

## 4. Create The Queued Storage Job

Add:

`src/Jobs/StorePaymobReceipt.php`

The job should accept a payment id, reload the payment, compute the deterministic key, and write privately.

Important behavior:

- Job implements `ShouldQueue`.
- Key format is exactly `paymob/receipts/{transaction_id}.pdf` unless config prefix changes it.
- If the payment already has an active receipt row, return early.
- Use Laravel Storage only.
- Store with private visibility.
- Save `disk`, `filename`, `key`, and `stored_at` in `payment_receipts` after the write.

Core write:

```php
$disk = config('paymob.receipts.disk', 's3');
$prefix = trim(config('paymob.receipts.prefix', 'paymob/receipts'), '/');
$key = "{$prefix}/{$payment->transaction_id}.pdf";

Storage::disk($disk)->put($key, $contents);
Storage::disk($disk)->setVisibility($key, 'private');
```

For stronger duplicate protection, wrap the reload/update in a database transaction and lock the payment row:

```php
$payment = Payment::query()->whereKey($this->paymentId)->lockForUpdate()->firstOrFail();

if ($payment->receipt()->exists()) {
    return;
}
```

## 5. Dispatch Only After Verified Processing Succeeds

Do not dispatch receipt storage before HMAC verification.

In this package, the durable captured payment row is finalized inside `src/Jobs/ProcessPaymobPayment.php` in `markPaymentCaptured()`. After the payment is marked `captured`, dispatch `StorePaymobReceipt`.

Suggested location:

```php
private function markPaymentCaptured(CapturePaymentResponseDto $response): void
{
    $payment = Payment::query()
        ->where('paymob_reference', $this->paymentReference())
        ->firstOrFail();

    $payment->forceFill([
        'status' => 'captured',
        'response_payload' => $response->payload,
        'captured_at' => now(),
    ])->save();

    StorePaymobReceipt::dispatch($payment->id);
}
```

If the webhook controller later dispatches payment processing directly after `PaymobClient::checkHmac(...)`, keep the ordering:

1. Verify HMAC.
2. Persist or find the payment by transaction id.
3. Dispatch queued work.
4. Return Paymob's `200` response without waiting for S3.

## 6. Make Reprocessing Idempotent

Use the transaction id as the receipt identity:

```php
private function receiptKey(Payment $payment): string
{
    $prefix = trim(config('paymob.receipts.prefix', 'paymob/receipts'), '/');

    return "{$prefix}/{$payment->transaction_id}.pdf";
}
```

The same transaction should always map to the same object key.

Idempotency rules:

- Same `transaction_id` means same `receipt_key`.
- Reprocessing must not create `paymob/receipts/{transaction_id}-1.pdf`.
- A retry may overwrite the same key only if the payment has no active receipt row yet.
- Once an active receipt row exists, return early.

## 7. Test With Storage Fake

Add tests to `tests/ProcessPaymobPaymentTest.php` or a new `tests/StorePaymobReceiptTest.php`.

Test setup:

```php
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Paymob\Laravel\Jobs\StorePaymobReceipt;

Storage::fake('s3');
$this->app['config']->set('paymob.receipts.disk', 's3');
$this->app['config']->set('paymob.receipts.prefix', 'paymob/receipts');
```

For the full flow, run the capture job, then run the receipt job if you fake the queue.

Assertions:

```php
Storage::disk('s3')->assertExists('paymob/receipts/987654.pdf');

$this->assertDatabaseHas('payment_receipts', [
    'filename' => '987654.pdf',
    'disk' => 's3',
    'key' => 'paymob/receipts/987654.pdf',
]);
```

For reprocessing:

```php
(new StorePaymobReceipt($payment->id))->handle($renderer);
(new StorePaymobReceipt($payment->id))->handle($renderer);

$files = Storage::disk('s3')->allFiles('paymob/receipts');

$this->assertSame(['paymob/receipts/987654.pdf'], $files);
```

Also assert the renderer only ran once if you inject a fake renderer with a call counter.

## 8. Host App Usage

The package should not create public URLs. The host app can use the saved receipt disk/key later:

```php
$receipt = $payment->receipt;

$url = Storage::disk($receipt->disk)
    ->temporaryUrl($receipt->key, now()->addMinutes(15));
```

That keeps credentials, buckets, URL style, CDN decisions, and expiration policy in the host application.

## Done Criteria

- `paymob.receipts.disk` defaults to `s3`.
- Receipt writes use `Storage::disk(...)`, not an S3 SDK client.
- Receipt object key is deterministic from `transaction_id`.
- Receipt object visibility is private.
- Receipt storage happens in a queued job.
- `payment_receipts` records `disk`, `filename`, `key`, and `stored_at`.
- Duplicate verified processing results in one payment row and one receipt key.
- Tests use `Storage::fake('s3')` and prove PDF generation plus idempotency.
