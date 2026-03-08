<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareRequestInvitation extends Model
{
    use HasFactory;

    public const SLA_HOURS = 12;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'care_request_id',
        'family_user_id',
        'caregiver_user_id',
        'care_request_application_id',
        'status',
        'message',
        'expires_at',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function careRequest(): BelongsTo
    {
        return $this->belongsTo(CareRequest::class);
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(User::class, 'family_user_id');
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_user_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CareRequestApplication::class, 'care_request_application_id');
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->expires_at
            && $this->expires_at->isPast();
    }

    public function responseDueAt()
    {
        return $this->created_at?->copy()->addHours(self::SLA_HOURS);
    }

    public function isWithinSla(): bool
    {
        if (! $this->responded_at || ! $this->created_at) {
            return false;
        }

        return $this->responded_at->lte($this->responseDueAt());
    }
}
