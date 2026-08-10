<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $table = 'transactions';
    public $timestamps = true;

    protected $fillable = [
        'invoice_id', 'client_id', 'pay_tabs_tran_ref', 'pay_tabs_ref', 'amount', 'currency',
        'status', 'payment_method', 'redirect_url', 'callback_verified', 'failure_reason',
        'refund_amount', 'refunded_at'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'refunded_at' => 'datetime',
        'amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}