<?php

namespace App\Services\FamilyAccounts;

use App\Models\FamilyAccount;
use App\Models\FamilyAccountActivityLog;
use App\Models\FamilyAccountMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FamilyAccountAccessService
{
    public function __construct(private readonly FamilyAccountContext $context) {}

    public function remove(User $owner, FamilyAccountMember $member): void
    {
        $ownerMembership = $this->context->membership($owner);
        if (! $ownerMembership->isOwner() || (int) $ownerMembership->family_account_id !== (int) $member->family_account_id) {
            throw ValidationException::withMessages(['member' => 'Only the account owner can remove access.']);
        }

        if ($member->isOwner() || (int) $member->user_id === (int) $owner->id) {
            throw ValidationException::withMessages(['member' => 'LoLo Support can help transfer account ownership.']);
        }

        DB::transaction(function () use ($owner, $member): void {
            $account = FamilyAccount::query()->whereKey($member->family_account_id)->lockForUpdate()->firstOrFail();
            if ($account->status !== FamilyAccount::STATUS_ACTIVE || (int) $account->owner_user_id !== (int) $owner->id) {
                throw ValidationException::withMessages(['member' => 'Only the current account owner can remove access.']);
            }

            $locked = FamilyAccountMember::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isActive()) {
                return;
            }

            $locked->forceFill([
                'status' => FamilyAccountMember::STATUS_REMOVED,
                'ended_at' => now(),
                'ended_by_user_id' => $owner->id,
            ])->save();

            FamilyAccountActivityLog::query()->create([
                'family_account_id' => $locked->family_account_id,
                'actor_user_id' => $owner->id,
                'action' => 'member_removed',
                'subject_user_id' => $locked->user_id,
            ]);
        });
    }

    public function leave(User $memberUser): void
    {
        $membership = $this->context->membership($memberUser);
        if ($membership->isOwner()) {
            throw ValidationException::withMessages(['member' => 'LoLo Support can help transfer account ownership.']);
        }

        DB::transaction(function () use ($memberUser, $membership): void {
            FamilyAccount::query()->whereKey($membership->family_account_id)->lockForUpdate()->firstOrFail();
            $locked = FamilyAccountMember::query()->whereKey($membership->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isActive()) {
                return;
            }
            if ($locked->isOwner()) {
                throw ValidationException::withMessages(['member' => 'LoLo Support can help transfer account ownership.']);
            }

            $locked->forceFill([
                'status' => FamilyAccountMember::STATUS_LEFT,
                'ended_at' => now(),
                'ended_by_user_id' => $memberUser->id,
            ])->save();

            FamilyAccountActivityLog::query()->create([
                'family_account_id' => $locked->family_account_id,
                'actor_user_id' => $memberUser->id,
                'action' => 'member_left',
                'subject_user_id' => $memberUser->id,
            ]);
        });
    }
}
