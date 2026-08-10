<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $table = 'notifications';
    public $timestamps = false;

    protected $fillable = [
        'recipient_id', 'recipient_role', 'title', 'message', 'type', 'is_read'
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}