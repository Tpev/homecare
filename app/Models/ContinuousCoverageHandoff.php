<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContinuousCoverageHandoff extends Model
{
    use HasFactory;

    protected $fillable = ['continuous_coverage_shift_id', 'caregiver_user_id', 'notes', 'recorded_at'];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(ContinuousCoverageShift::class, 'continuous_coverage_shift_id');
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_user_id');
    }
}
