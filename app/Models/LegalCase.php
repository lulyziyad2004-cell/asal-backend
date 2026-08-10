<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalCase extends Model
{
    protected $table = 'cases';
    public $timestamps = true;

    protected $fillable = [
        'case_number', 'title', 'description', 'client_id', 'lawyer_id', 'consultant_id',
        'court', 'city', 'circuit_number', 'poa_number', 'poa_expiry', 'status', 'category'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'poa_expiry' => 'datetime',
    ];

    // Relationships
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function lawyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lawyer_id');
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultant_id');
    }

    public function hearings(): HasMany
    {
        return $this->hasMany(Hearing::class, 'case_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'case_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'case_id');
    }
}