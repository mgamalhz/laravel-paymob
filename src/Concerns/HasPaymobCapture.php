<?php

namespace Paymob\Laravel\Concerns;

use Illuminate\Database\Eloquent\Model;
use Paymob\Laravel\DTO\CapturePaymentResponseDto;

/**
 * @property bool|null $paymob_captured
 * @property bool|null $captured
 * @property string|null $payment_status
 * @property mixed $captured_at
 *
 * @phpstan-require-extends Model
 */
trait HasPaymobCapture
{
    public function isPaymobCaptured(): bool
    {
        if (($this->paymob_captured ?? false) === true) {
            return true;
        }

        if (($this->captured ?? false) === true) {
            return true;
        }

        if (($this->payment_status ?? null) === 'captured') {
            return true;
        }

        return ($this->captured_at ?? null) !== null;
    }

    public function markPaymobCaptured(CapturePaymentResponseDto $response): void
    {
        $this->paymob_captured = true;
        $this->payment_status = 'captured';
        $this->captured_at ??= now();

        $this->save();
    }
}
