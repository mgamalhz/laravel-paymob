<?php

namespace Paymob\Laravel\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'paymob_reference',
        'transaction_id',
        'order_type',
        'order_id',
        'amount_cents',
        'status',
        'response_payload',
        'captured_at',
        'disk',
        'filename',
        'key',
        'stored_at',
    ];

    protected $casts = [
        'transaction_id' => 'integer',
        'amount_cents' => 'integer',
        'response_payload' => 'array',
        'captured_at' => 'datetime',
    ];

    public function receipt()
    {
        return $this->hasOne(PaymentReceipt::class);
    }

}
