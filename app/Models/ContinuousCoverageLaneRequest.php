<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContinuousCoverageLaneRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_NOT_SELECTED = 'not_selected';

    public const STATUS_UNAVAILABLE = 'unavailable';

    protected $fillable = [
        'continuous_coverage_plan_id', 'shift_template_id', 'roster_member_id',
        'caregiver_user_id', 'responded_by_user_id', 'batch_uuid', 'status',
        'requested_at', 'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'responded_at' => 'datetime',
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

    public function rosterMember(): BelongsTo
    {
        return $this->belongsTo(ContinuousCoverageRosterMember::class, 'roster_member_id');
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_user_id');
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by_user_id');
    }
}
