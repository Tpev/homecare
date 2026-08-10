<?php

namespace App\Policies;

use App\Models\CareRecipientProfileVersion;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;

class CareRecipientProfileVersionPolicy
{
    public function __construct(private readonly FamilyAccountContext $familyAccounts) {}

    public function view(User $user, CareRecipientProfileVersion $version): bool
    {
        $version->loadMissing('profile');

        return $user->isAdministrator()
            || ($user->role === 'family' && $this->familyAccounts->canAccessRecord($user, $version->profile));
    }

    public function update(User $user, CareRecipientProfileVersion $version): bool
    {
        return false;
    }

    public function delete(User $user, CareRecipientProfileVersion $version): bool
    {
        return false;
    }
}
