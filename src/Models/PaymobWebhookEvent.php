<?php

namespace Paymob\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymobWebhookEvent extends Model
{
    protected $fillable = [
        'transaction_id',
        'payload',
    ];

    protected $casts = [
        'transaction_id' => 'integer',
        'payload' => 'array',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'transaction_id', 'transaction_id');
    }
}
