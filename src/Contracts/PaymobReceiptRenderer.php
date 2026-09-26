<?php

namespace Paymob\Laravel\Contracts;

use Paymob\Laravel\Models\Payment;

interface PaymobReceiptRenderer
{
    public function render(Payment $payment): string;
}
