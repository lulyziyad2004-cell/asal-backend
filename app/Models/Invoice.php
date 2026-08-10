<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $table = 'invoices';
    public $timestamps = true;

    protected $fillable = [
        'invoice_number', 'client_id', 'case_id', 'title', 'amount', 'currency', 'due_date', 'status'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'due_date' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'invoice_id');
    }
}