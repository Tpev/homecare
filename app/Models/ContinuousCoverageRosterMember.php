<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContinuousCoverageRosterMember extends Model
{
    use HasFactory;

    public const STATUS_INVITED = 'invited';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_FAMILY_APPROVED = 'family_approved';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_REMOVED = 'removed';

    public const ROLE_PRIMARY = 'primary';

    public const ROLE_BACKUP = 'backup';

    protected $fillable = [
        'continuous_coverage_plan_id', 'caregiver_user_id', 'invited_by_user_id',
        'status', 'role', 'replacement_opt_in', 'eligible_days',
        'eligible_shift_types', 'family_approved_at', 'caregiver_accepted_at',
        'paused_at', 'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'replacement_opt_in' => 'boolean',
            'eligible_days' => 'array',
            'eligible_shift_types' => 'array',
            'family_approved_at' => 'datetime',
            'caregiver_accepted_at' => 'datetime',
            'paused_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ContinuousCoveragePlan::class, 'continuous_coverage_plan_id');
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_user_id');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(ContinuousCoverageShiftTemplate::class, 'roster_member_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(ContinuousCoverageShiftOffer::class, 'roster_member_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->family_approved_at !== null
            && $this->caregiver_accepted_at !== null;
    }
}
