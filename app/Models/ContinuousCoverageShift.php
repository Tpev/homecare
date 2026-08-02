<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContinuousCoverageShift extends Model
{
    use HasFactory;

    public const STATUS_UNCOVERED = 'uncovered';

    public const STATUS_OFFER_PENDING = 'offer_pending';

    public const STATUS_AWAITING_FAMILY = 'awaiting_family_confirmation';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REPLACEMENT_NEEDED = 'replacement_needed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_PAYMENT_ATTENTION = 'payment_attention';

    protected $fillable = [
        'continuous_coverage_plan_id', 'shift_template_id',
        'assigned_caregiver_user_id', 'care_booking_id', 'released_by_user_id',
        'occurrence_key', 'status', 'scheduled_start_at', 'scheduled_end_at',
        'scheduled_minutes', 'caregiver_accepted_at', 'family_confirmed_at',
        'confirmed_at', 'released_at', 'release_reason', 'cancelled_at',
        'completed_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'scheduled_minutes' => 'integer',
            'caregiver_accepted_at' => 'datetime',
            'family_confirmed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'released_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ContinuousCoveragePlan::class, 'continuous_coverage_plan_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ContinuousCoverageShiftTemplate::class, 'shift_template_id');
    }

    public function assignedCaregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_caregiver_user_id');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_user_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(CareBooking::class, 'care_booking_id');
    }

    public function replacementCase(): HasOne
    {
        return $this->hasOne(ContinuousCoverageReplacementCase::class, 'continuous_coverage_shift_id')->latestOfMany();
    }

    public function replacementCases(): HasMany
    {
        return $this->hasMany(ContinuousCoverageReplacementCase::class, 'continuous_coverage_shift_id')
            ->orderBy('opened_at');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(ContinuousCoverageShiftOffer::class, 'continuous_coverage_shift_id');
    }

    public function handoffs(): HasMany
    {
        return $this->hasMany(ContinuousCoverageHandoff::class, 'continuous_coverage_shift_id')->latest('recorded_at');
    }
}
