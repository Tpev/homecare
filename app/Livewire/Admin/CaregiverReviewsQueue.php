<?php

namespace App\Livewire\Admin;

use App\Models\CaregiverProfile;
use App\Models\CaregiverModerationLog;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CaregiverReviewsQueue extends Component
{
    public ?int $selectedId = null;
    public string $rejection_reason = '';

    public function selectProfile(int $id): void
    {
        $this->selectedId = $id;
    }

    public function approve(int $id): void
    {
        $profile = CaregiverProfile::findOrFail($id);

        $profile->update([
            'status' => 'active',
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
            'rejection_reason' => null,
            'identity_verified_at' => $profile->identity_verified_at ?: now(),
            'background_check_verified_at' => $profile->background_check_verified_at ?: now(),
        ]);

        CaregiverModerationLog::create([
            'caregiver_profile_id' => $profile->id,
            'actor_user_id' => auth()->id(),
            'action' => 'approved',
            'note' => 'Profile approved',
            'meta' => ['status' => 'active'],
        ]);
    }

    public function reject(int $id): void
    {
        $this->validate(['rejection_reason' => ['required', 'string', 'min:5']]);

        $profile = CaregiverProfile::findOrFail($id);

        $profile->update([
            'status' => 'draft',
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
            'rejection_reason' => $this->rejection_reason,
        ]);

        CaregiverModerationLog::create([
            'caregiver_profile_id' => $profile->id,
            'actor_user_id' => auth()->id(),
            'action' => 'rejected',
            'note' => $this->rejection_reason,
            'meta' => ['status' => 'draft'],
        ]);

        $this->rejection_reason = '';
    }

    public function suspend(int $id): void
    {
        $profile = CaregiverProfile::findOrFail($id);
        $profile->update(['status' => 'suspended']);

        CaregiverModerationLog::create([
            'caregiver_profile_id' => $profile->id,
            'actor_user_id' => auth()->id(),
            'action' => 'suspended',
            'note' => 'Profile suspended by admin',
        ]);
    }

    public function unsuspend(int $id): void
    {
        $profile = CaregiverProfile::findOrFail($id);
        $profile->update(['status' => 'active']);

        CaregiverModerationLog::create([
            'caregiver_profile_id' => $profile->id,
            'actor_user_id' => auth()->id(),
            'action' => 'unsuspended',
            'note' => 'Profile re-activated by admin',
        ]);
    }

    public function toggleIdentityVerification(int $id): void
    {
        $profile = CaregiverProfile::findOrFail($id);
        $enabled = ! $profile->identity_verified_at;

        $profile->update([
            'identity_verified_at' => $enabled ? now() : null,
        ]);

        CaregiverModerationLog::create([
            'caregiver_profile_id' => $profile->id,
            'actor_user_id' => auth()->id(),
            'action' => $enabled ? 'identity_verified' : 'identity_unverified',
            'note' => $enabled ? 'Identity verification enabled' : 'Identity verification removed',
        ]);
    }

    public function toggleBackgroundCheck(int $id): void
    {
        $profile = CaregiverProfile::findOrFail($id);
        $enabled = ! $profile->background_check_verified_at;

        $profile->update([
            'background_check_verified_at' => $enabled ? now() : null,
        ]);

        CaregiverModerationLog::create([
            'caregiver_profile_id' => $profile->id,
            'actor_user_id' => auth()->id(),
            'action' => $enabled ? 'background_verified' : 'background_unverified',
            'note' => $enabled ? 'Background check verification enabled' : 'Background check verification removed',
        ]);
    }

    public function toggleTopCaregiver(int $id): void
    {
        $profile = CaregiverProfile::findOrFail($id);
        $enabled = ! $profile->top_caregiver;

        $profile->update([
            'top_caregiver' => $enabled,
        ]);

        CaregiverModerationLog::create([
            'caregiver_profile_id' => $profile->id,
            'actor_user_id' => auth()->id(),
            'action' => $enabled ? 'top_caregiver_enabled' : 'top_caregiver_removed',
            'note' => $enabled ? 'Top Caregiver badge enabled' : 'Top Caregiver badge removed',
        ]);
    }


    public function render()
    {
        $profiles = CaregiverProfile::query()
            ->with('user')
            ->where('status', 'under_review')
            ->latest('review_submitted_at')
            ->get();

        $activeProfiles = CaregiverProfile::query()
            ->with('user')
            ->where('status', 'active')
            ->latest('updated_at')
            ->limit(30)
            ->get();

        return view('livewire.admin.caregiver-reviews-queue', compact('profiles', 'activeProfiles'));
    }
}
