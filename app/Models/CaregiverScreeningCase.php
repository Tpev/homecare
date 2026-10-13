<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaregiverScreeningCase extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['identity_snapshot' => 'array', 'source_versions' => 'array', 'due_at' => 'datetime', 'searched_at' => 'datetime', 'claimed_until' => 'datetime', 'completed_at' => 'datetime', 'lock_version' => 'integer'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CaregiverProfile::class, 'caregiver_profile_id');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(CaregiverScreeningCandidate::class, 'screening_case_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ScreeningAuditEvent::class, 'screening_case_id')->orderBy('id');
    }

    public function bookingReviews(): HasMany
    {
        return $this->hasMany(ScreeningBookingReview::class, 'screening_case_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function claimant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }
}
