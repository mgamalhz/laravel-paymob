<?php

namespace Paymob\Laravel\Concerns;

use Paymob\Laravel\DTO\CapturePaymentResponseDto;

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

        if (method_exists($this, 'save')) {
            $this->save();
        }
    }
}
