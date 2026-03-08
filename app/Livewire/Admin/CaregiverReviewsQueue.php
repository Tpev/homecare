<?php

namespace App\Livewire\Admin;

use App\Models\CaregiverProfile;
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
    ]);

    \App\Models\CaregiverModerationLog::create([
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

    \App\Models\CaregiverModerationLog::create([
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

    \App\Models\CaregiverModerationLog::create([
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

    \App\Models\CaregiverModerationLog::create([
        'caregiver_profile_id' => $profile->id,
        'actor_user_id' => auth()->id(),
        'action' => 'unsuspended',
        'note' => 'Profile re-activated by admin',
    ]);
}


    public function render()
    {
        $profiles = CaregiverProfile::query()
            ->with('user')
            ->where('status', 'under_review')
            ->latest('review_submitted_at')
            ->get();

        return view('livewire.admin.caregiver-reviews-queue', compact('profiles'));
    }
}
