<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Hearing extends Model
{
    protected $table = 'hearings';
    public $timestamps = true;

    protected $fillable = [
        'case_id', 'title', 'court', 'city', 'circuit_number', 'scheduled_at',
        'status', 'defense_notes', 'requirements', 'assigned_lawyer_id'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function assignedLawyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_lawyer_id');
    }
}