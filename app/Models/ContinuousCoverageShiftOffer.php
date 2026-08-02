<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContinuousCoverageShiftOffer extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_NOT_SELECTED = 'not_selected';

    protected $fillable = [
        'replacement_case_id', 'continuous_coverage_shift_id',
        'roster_member_id', 'caregiver_user_id', 'status', 'expires_at',
        'responded_at',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'responded_at' => 'datetime'];
    }

    public function replacementCase(): BelongsTo
    {
        return $this->belongsTo(ContinuousCoverageReplacementCase::class, 'replacement_case_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(ContinuousCoverageShift::class, 'continuous_coverage_shift_id');
    }

    public function rosterMember(): BelongsTo
    {
        return $this->belongsTo(ContinuousCoverageRosterMember::class, 'roster_member_id');
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_user_id');
    }
}
