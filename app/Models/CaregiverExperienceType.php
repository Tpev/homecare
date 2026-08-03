<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CaregiverExperienceType extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'label', 'description', 'sort_order', 'active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function caregiverProfiles(): BelongsToMany
    {
        return $this->belongsToMany(CaregiverProfile::class, 'caregiver_profile_experience')->withTimestamps();
    }
}
