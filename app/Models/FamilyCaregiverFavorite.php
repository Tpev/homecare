<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyCaregiverFavorite extends Model
{
    use HasFactory;

    protected $fillable = [
        'family_user_id',
        'caregiver_user_id',
    ];

    public function family(): BelongsTo
    {
        return $this->belongsTo(User::class, 'family_user_id');
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_user_id');
    }
}
