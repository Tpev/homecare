<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CareBooking extends Model
{
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'care_request_id',
        'care_request_application_id',
        'family_user_id',
        'caregiver_user_id',
        'status',
        'scheduled_start_at',
        'scheduled_end_at',
        'started_at',
        'completed_at',
        'reviewed_at',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancellation_reason',
        'last_rescheduled_at',
        'last_reschedule_reason',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_rescheduled_at' => 'datetime',
        ];
    }

    public function careRequest(): BelongsTo
    {
        return $this->belongsTo(CareRequest::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CareRequestApplication::class, 'care_request_application_id');
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(User::class, 'family_user_id');
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(CareBookingChangeRequest::class)->latest();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(CareReview::class);
    }
}
