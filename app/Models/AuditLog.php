<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $table = 'audit_logs';
    public $timestamps = false;

    protected $fillable = [
        'actor_id', 'actor_role', 'action', 'target_type', 'target_id', 'details', 'ip_address'
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}