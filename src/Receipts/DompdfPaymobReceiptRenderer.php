<?php

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
            ->loadView('paymob::receipts.payment', [
                'payment' => $payment,
            ])
            ->output();
    }
}
