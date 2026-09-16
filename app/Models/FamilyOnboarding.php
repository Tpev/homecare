<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FamilyOnboarding extends Model
{
    // Access is always scoped explicitly by FamilyOnboardingService or admin authorization.
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'draft' => 'array', 'request_context' => 'array', 'submitted_snapshot' => 'array',
            'revision' => 'integer', 'current_step' => 'integer', 'started_at' => 'datetime',
            'completed_at' => 'datetime', 'request_handoff_completed_at' => 'datetime',
        ];
    }

    public function familyAccount(): BelongsTo
    {
        return $this->belongsTo(FamilyAccount::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    public function welcomeVisit(): HasOne
    {
        return $this->hasOne(FamilyWelcomeVisit::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(FamilyOnboardingDelivery::class);
    }

    public function submissionDetails(): array
    {
        $s = $this->submitted_snapshot ?? [];
        $details = [
            'Family account' => '#'.$this->family_account_id,
            'Name' => $s['name'] ?? '', 'Email' => $s['email'] ?? '', 'Phone' => $s['phone'] ?? '',
            'Source' => $this->source, 'Submitted (UTC)' => $s['submitted_at'] ?? '',
            'Who receives care' => ($s['care_for'] ?? '') === 'me' ? 'Me' : 'A family member',
            'Recipient name' => $s['recipient_name'] ?? '', 'Relationship' => $s['relationship'] ?? '',
            'Street address' => $s['address_line1'] ?? '', 'Apartment / suite' => $s['address_line2'] ?? '',
            'City' => $s['city'] ?? '', 'State' => $s['state'] ?? '', 'ZIP code' => $s['zip'] ?? '',
            'What should a caregiver know?' => $s['care_notes'] ?? '',
            'What helps care go well?' => $s['care_preferences'] ?? '',
            'Free welcome visit' => ($s['welcome_visit'] ?? '') === 'yes' ? 'Requested — 1 hour, free' : 'Declined',
        ];
        if (($s['welcome_visit'] ?? '') === 'yes') {
            $details += [
                'Preferred date' => $s['visit_date'], 'Preferred time' => $s['visit_range_label'],
                'Timezone' => $s['timezone'], 'Status at submission' => 'Awaiting confirmation by text',
            ];
        }

        return collect($details)->map(fn ($value, $label) => ['label' => $label, 'value' => $value ?: 'Not provided'])->values()->all();
    }
}
