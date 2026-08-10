<?php

namespace App\Livewire\Family;

use App\Models\CareRecipientProfile;
use App\Services\CareRecipientProfiles\CareRecipientProfileService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CareProfiles extends Component
{
    public ?int $archivingProfileId = null;

    public function archive(CareRecipientProfileService $profiles): void
    {
        $profile = $this->ownedProfile((int) $this->archivingProfileId);
        $this->authorize('archive', $profile);
        $profiles->archive(auth()->user(), $profile);
        $this->archivingProfileId = null;
        session()->flash('status', $profile->displayName().'\'s care profile was archived.');
    }

    public function restore(int $profileId, CareRecipientProfileService $profiles): void
    {
        $profile = $this->ownedProfile($profileId);
        $this->authorize('restore', $profile);
        $profiles->restore(auth()->user(), $profile);
        session()->flash('status', $profile->displayName().'\'s care profile was restored.');
    }

    public function makeDefault(int $profileId, CareRecipientProfileService $profiles): void
    {
        $profile = $this->ownedProfile($profileId);
        $this->authorize('update', $profile);
        $profiles->makeDefault(auth()->user(), $profile);
        session()->flash('status', $profile->displayName().' will be suggested first for new care.');
    }

    public function render(FamilyAccountContext $context)
    {
        $this->authorize('viewAny', CareRecipientProfile::class);
        $account = $context->account(auth()->user());
        $profiles = CareRecipientProfile::query()
            ->forFamilyAccount($account)
            ->with('updatedBy:id,name')
            ->orderByRaw("CASE WHEN status = 'archived' THEN 1 ELSE 0 END")
            ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$account->default_care_recipient_profile_id ?: 0])
            ->orderBy('preferred_name')
            ->get();

        return view('livewire.family.care-profiles', [
            'profiles' => $profiles,
            'activeProfiles' => $profiles->where('status', '!=', CareRecipientProfile::STATUS_ARCHIVED),
            'archivedProfiles' => $profiles->where('status', CareRecipientProfile::STATUS_ARCHIVED),
            'defaultProfileId' => (int) $account->default_care_recipient_profile_id,
        ]);
    }

    private function ownedProfile(int $profileId): CareRecipientProfile
    {
        $account = app(FamilyAccountContext::class)->account(auth()->user());

        return CareRecipientProfile::query()->forFamilyAccount($account)->findOrFail($profileId);
    }
}
