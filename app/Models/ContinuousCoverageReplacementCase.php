<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContinuousCoverageReplacementCase extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_AWAITING_FAMILY = 'awaiting_family_confirmation';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_UNRESOLVED = 'unresolved';

    protected $fillable = [
        'continuous_coverage_shift_id', 'original_caregiver_user_id',
        'winning_offer_id', 'status', 'reason', 'opened_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(ContinuousCoverageShift::class, 'continuous_coverage_shift_id');
    }

    public function originalCaregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_caregiver_user_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(ContinuousCoverageShiftOffer::class, 'replacement_case_id');
    }

    public function winningOffer(): BelongsTo
    {
        return $this->belongsTo(ContinuousCoverageShiftOffer::class, 'winning_offer_id');
    }
}
