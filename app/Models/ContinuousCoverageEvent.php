<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContinuousCoverageEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'continuous_coverage_plan_id', 'continuous_coverage_shift_id',
        'actor_user_id', 'event_type', 'payload', 'happened_at',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array', 'happened_at' => 'datetime'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ContinuousCoveragePlan::class, 'continuous_coverage_plan_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(ContinuousCoverageShift::class, 'continuous_coverage_shift_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
