<?php

namespace App\Services\FamilyAccounts;

use App\Models\FamilyAccount;
use App\Models\FamilyAccountMember;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class FamilyAccountContext
{
    public function __construct(private readonly FamilyAccountProvisioner $provisioner) {}

    public function membershipFor(User $user, bool $provisionExistingOwner = true): ?FamilyAccountMember
    {
        if ($user->isAdministrator()) {
            return null;
        }

        $membership = FamilyAccountMember::query()
            ->with('familyAccount.owner')
            ->where('user_id', $user->id)
            ->where('status', FamilyAccountMember::STATUS_ACTIVE)
            ->whereHas('familyAccount', fn ($query) => $query->where('status', FamilyAccount::STATUS_ACTIVE))
            ->first();

        if ($membership || ! $provisionExistingOwner || $user->role !== 'family') {
            return $membership;
        }

        // Never recreate access for a user who deliberately left or was removed.
        if (FamilyAccountMember::query()->where('user_id', $user->id)->exists()) {
            return null;
        }

        $account = $this->provisioner->provisionOwner($user, 'account_created_compatibility');

        return $account->activeMemberships()->with('familyAccount.owner')->where('user_id', $user->id)->first();
    }

    /**
     * @throws AuthorizationException
     */
    public function membership(User $user): FamilyAccountMember
    {
        $membership = $this->membershipFor($user);

        if (! $membership) {
            throw new AuthorizationException('Your access to this family account has ended.');
        }

        return $membership;
    }

    public function account(User $user): FamilyAccount
    {
        return $this->membership($user)->familyAccount;
    }

    public function owner(User $user): User
    {
        return $this->account($user)->owner;
    }

    public function isOwner(User $user): bool
    {
        return $this->membershipFor($user)?->isOwner() === true;
    }

    public function canAccessAccount(User $user, int|FamilyAccount $account): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        $accountId = $account instanceof FamilyAccount ? $account->id : $account;

        if ((int) $accountId < 1) {
            return false;
        }

        return (int) ($this->membershipFor($user, false)?->family_account_id ?? 0) === (int) $accountId;
    }

    public function canAccessRecord(User $user, Model $record): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        $accountId = (int) ($record->getAttribute('family_account_id') ?? 0);

        if ($accountId > 0) {
            return $this->canAccessAccount($user, $accountId);
        }

        $membership = $this->membershipFor($user, false);

        return $membership?->isOwner() === true
            && (int) ($record->getAttribute('family_user_id') ?? 0) === (int) $user->id;
    }

    /** @return array{family_account_id:int,family_user_id:int} */
    public function ownershipAttributes(User $actor): array
    {
        $account = $this->account($actor);

        return [
            'family_account_id' => (int) $account->id,
            'family_user_id' => (int) $account->owner_user_id,
        ];
    }
}
