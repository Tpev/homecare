<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContinuousCoverageShiftTemplate extends Model
{
    use HasFactory;

    public const STATUS_UNCOVERED = 'uncovered';

    public const STATUS_OFFERED = 'offered';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'continuous_coverage_plan_id', 'roster_member_id', 'day_of_week',
        'starts_at', 'ends_at', 'spans_next_day', 'duration_minutes',
        'schedule_version', 'status', 'effective_from', 'effective_until',
        'effective_start_at',
        'offered_at', 'offer_expires_at', 'accepted_at', 'declined_at',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'spans_next_day' => 'boolean',
            'duration_minutes' => 'integer',
            'schedule_version' => 'integer',
            'effective_from' => 'date',
            'effective_start_at' => 'datetime',
            'effective_until' => 'date',
            'offered_at' => 'datetime',
            'offer_expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ContinuousCoveragePlan::class, 'continuous_coverage_plan_id');
    }

    public function rosterMember(): BelongsTo
    {
        return $this->belongsTo(ContinuousCoverageRosterMember::class, 'roster_member_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(ContinuousCoverageShift::class, 'shift_template_id');
    }

    public function laneRequests(): HasMany
    {
        return $this->hasMany(ContinuousCoverageLaneRequest::class, 'shift_template_id');
    }
}
