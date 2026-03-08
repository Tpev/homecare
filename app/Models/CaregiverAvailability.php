<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaregiverAvailability extends Model
{
    use HasFactory;

    protected $fillable = [
        'caregiver_profile_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    public function caregiverProfile(): BelongsTo
    {
        return $this->belongsTo(CaregiverProfile::class);
    }
}
