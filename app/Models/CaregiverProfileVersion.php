<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaregiverProfileVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'caregiver_profile_id',
        'user_id',
        'snapshot',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
        ];
    }

    public function caregiverProfile(): BelongsTo
    {
        return $this->belongsTo(CaregiverProfile::class);
    }
}
