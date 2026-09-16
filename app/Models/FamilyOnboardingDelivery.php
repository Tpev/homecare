<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyOnboardingDelivery extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['next_attempt_at' => 'datetime', 'claimed_at' => 'datetime', 'queued_at' => 'datetime', 'accepted_at' => 'datetime', 'attempts' => 'integer'];
    }

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(FamilyOnboarding::class, 'family_onboarding_id');
    }
}
