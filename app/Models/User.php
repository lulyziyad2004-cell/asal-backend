<?php

namespace App\Models;

use App\Models\LegalCase as CaseModel;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'open_id', 'login_method', 'role', 'password_hash', 'specialty', 'case_number', 'city', 'avatar_key', 'status', 'last_signed_in'])]
#[Hidden(['password_hash'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'open_id',
        'login_method',
        'role',
        'password_hash',
        'specialty',
        'case_number',
        'city',
        'avatar_key',
        'status',
        'last_signed_in',
    ];

    public $timestamps = true;

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'last_signed_in' => 'datetime',
        ];
    }

    // ===== Relationships =====

    /**
     * Cases owned by this client
     */
    public function clientCases(): HasMany
    {
        return $this->hasMany(CaseModel::class, 'client_id');
    }

    /**
     * Cases assigned to this lawyer
     */
    public function lawyerCases(): HasMany
    {
        return $this->hasMany(CaseModel::class, 'lawyer_id');
    }

    /**
     * Cases assigned to this consultant
     */
    public function consultantCases(): HasMany
    {
        return $this->hasMany(CaseModel::class, 'consultant_id');
    }

    /**
     * Hearings assigned to this lawyer
     */
    public function assignedHearings(): HasMany
    {
        return $this->hasMany(Hearing::class, 'assigned_lawyer_id');
    }

    /**
     * Invoices issued to this client
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'client_id');
    }

    /**
     * Transactions for this user
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'client_id');
    }

    /**
     * Documents uploaded by this user
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'uploader_id');
    }

    /**
     * Notifications for this user
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'recipient_id');
    }

    /**
     * Messages sent by this user
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Messages received by this user
     */
    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'recipient_id');
    }

    /**
     * Subscription for this user
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'user_id');
    }

    /**
     * Audit logs for actions by this user
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    // ===== Helper Methods =====

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is lawyer
     */
    public function isLawyer(): bool
    {
        return $this->role === 'lawyer';
    }

    /**
     * Check if user is consultant
     */
    public function isConsultant(): bool
    {
        return $this->role === 'consultant';
    }

    /**
     * Check if user is client
     */
    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    /**
     * Check if user account is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
