<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyWelcomeVisit extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date', 'window_start_at' => \App\Casts\UtcDateTime::class, 'window_end_at' => \App\Casts\UtcDateTime::class,
            'confirmed_start_at' => \App\Casts\UtcDateTime::class, 'handled_at' => 'datetime', 'revision' => 'integer',
        ];
    }

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(FamilyOnboarding::class, 'family_onboarding_id');
    }
}
