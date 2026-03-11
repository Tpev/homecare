<?php

namespace App\Livewire\Caregiver;

use App\Models\CaregiverProfile;
use Livewire\Component;

class ProfileCompletenessCard extends Component
{
    public CaregiverProfile $profile;

    public function mount(CaregiverProfile $profile): void
    {
        $this->profile = $profile;
    }

    public function render()
    {
        $checks = [
            'Bio added' => filled($this->profile->bio),
            'Platform rate assigned' => (float) $this->profile->resolvePlatformHourlyRate() > 0,
            'Experience set' => ! is_null($this->profile->years_experience),
            'ZIP set' => filled($this->profile->service_area_zip),
            'Skills selected' => $this->profile->skills()->exists(),
            'Languages selected' => $this->profile->languages()->exists(),
            'Availability added' => $this->profile->availabilities()->exists(),
            'Photo uploaded' => filled($this->profile->profile_photo_path),
            'Insurance set' => $this->profile->insuranceIsComplete(),
            'Identity verified' => $this->profile->hasIdentityVerifiedBadge(),
        ];

        $percent = (int) round((collect($checks)->filter()->count() / count($checks)) * 100);

        return view('livewire.caregiver.profile-completeness-card', [
            'checks' => $checks,
            'percent' => $percent,
        ]);
    }
}
