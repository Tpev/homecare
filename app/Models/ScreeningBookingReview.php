<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreeningBookingReview extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function screeningCase(): BelongsTo
    {
        return $this->belongsTo(CaregiverScreeningCase::class, 'screening_case_id');
    }
}
