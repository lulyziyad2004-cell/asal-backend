<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    protected $table = 'documents';
    public $timestamps = false;

    protected $fillable = [
        'title', 'category', 'case_id', 'hearing_id', 'uploader_id', 'uploader_role',
        'file_name', 'file_key', 'file_url', 'mime_type', 'size_bytes'
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }
}