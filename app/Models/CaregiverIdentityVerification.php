<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaregiverIdentityVerification extends Model
{
    use HasFactory;

    public const STATUS_NOT_STARTED = 'not_started';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_ABANDONED = 'abandoned';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'caregiver_profile_id',
        'user_id',
        'didit_session_id',
        'status',
        'verification_url',
        'vendor_data',
        'session_payload',
        'decision_payload',
        'webhook_payload',
        'started_at',
        'completed_at',
        'approved_at',
        'declined_at',
        'last_webhook_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'session_payload' => 'array',
            'decision_payload' => 'array',
            'webhook_payload' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'approved_at' => 'datetime',
            'declined_at' => 'datetime',
            'last_webhook_at' => 'datetime',
        ];
    }

    public static function normalizeDiditStatus(?string $status): string
    {
        return match (strtolower(trim((string) $status))) {
            'approved' => self::STATUS_APPROVED,
            'declined' => self::STATUS_DECLINED,
            'in review' => self::STATUS_IN_REVIEW,
            'in progress' => self::STATUS_IN_PROGRESS,
            'abandoned' => self::STATUS_ABANDONED,
            'expired' => self::STATUS_EXPIRED,
            'not started' => self::STATUS_NOT_STARTED,
            default => self::STATUS_ERROR,
        };
    }

    public function caregiverProfile(): BelongsTo
    {
        return $this->belongsTo(CaregiverProfile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

