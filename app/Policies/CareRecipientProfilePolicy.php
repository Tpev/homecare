<?php

namespace App\Policies;

use App\Models\CareRecipientProfile;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;

class CareRecipientProfilePolicy
{
    public function __construct(private readonly FamilyAccountContext $familyAccounts) {}

    public function viewAny(User $user): bool
    {
        return $user->role === 'family' && $this->familyAccounts->membershipFor($user, false)?->isActive();
    }

    public function view(User $user, CareRecipientProfile $profile): bool
    {
        return $user->isAdministrator()
            || ($user->role === 'family' && $this->familyAccounts->canAccessRecord($user, $profile));
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, CareRecipientProfile $profile): bool
    {
        return $user->role === 'family' && $this->familyAccounts->canAccessRecord($user, $profile);
    }

    public function archive(User $user, CareRecipientProfile $profile): bool
    {
        return $this->update($user, $profile);
    }

    public function restore(User $user, CareRecipientProfile $profile): bool
    {
        return $this->update($user, $profile);
    }
}
