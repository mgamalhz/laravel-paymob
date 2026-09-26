<?php

namespace Paymob\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Paymob\Laravel\Contracts\PaymobReceiptRenderer;
use Paymob\Laravel\Models\Payment;

class StorePaymobReceipt implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public $afterCommit = true;

    public function __construct(public int $paymentId)
    {

    }

    public function handle(PaymobReceiptRenderer $renderer): void
    {
        DB::transaction(function () use ($renderer): void {
            $payment = Payment::query()
                ->whereKey($this->paymentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->receipt()->exists()) {
                return;
            }

            $disk = config('paymob.receipts.disk', 's3');
            $filename = "{$payment->transaction_id}.pdf";
            $key = $this->receiptKey($filename);
            $contents = $renderer->render($payment);

            Storage::disk($disk)->put($key, $contents);
            Storage::disk($disk)->setVisibility($key, 'private');

            $payment->receipt()->create([
                'disk' => $disk,
                'filename' => $filename,
                'key' => $key,
                'stored_at' => now(),
            ]);
        });
    }

    private function receiptKey(string $filename): string
    {
        $prefix = trim(config('paymob.receipts.prefix', 'paymob/receipts'), '/');

        return "{$prefix}/{$filename}";
    }
}
