<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Language extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function caregiverProfiles(): BelongsToMany
    {
        return $this->belongsToMany(CaregiverProfile::class, 'caregiver_language')->withTimestamps();
    }
}
