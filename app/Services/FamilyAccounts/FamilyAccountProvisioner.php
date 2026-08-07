<?php

namespace App\Services\FamilyAccounts;

use App\Models\FamilyAccount;
use App\Models\FamilyAccountActivityLog;
use App\Models\FamilyAccountMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FamilyAccountProvisioner
{
    public function provisionOwner(User $user, string $activity = 'account_created'): FamilyAccount
    {
        if ($user->role !== 'family' || $user->isAdministrator()) {
            throw ValidationException::withMessages([
                'user' => 'Only family users can own a family care account.',
            ]);
        }

        return DB::transaction(function () use ($user, $activity): FamilyAccount {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $conflictingMembership = FamilyAccountMember::query()
                ->where('user_id', $lockedUser->id)
                ->where('status', FamilyAccountMember::STATUS_ACTIVE)
                ->where('access_level', '!=', FamilyAccountMember::ACCESS_OWNER)
                ->exists();

            if ($conflictingMembership) {
                throw ValidationException::withMessages([
                    'user' => 'This user already belongs to another family account.',
                ]);
            }

            $account = FamilyAccount::query()
                ->where('owner_user_id', $lockedUser->id)
                ->where('status', FamilyAccount::STATUS_ACTIVE)
                ->first();

            if (! $account) {
                $account = FamilyAccount::query()->create([
                    'owner_user_id' => $lockedUser->id,
                    'stripe_customer_id' => $lockedUser->stripe_customer_id,
                    'status' => FamilyAccount::STATUS_ACTIVE,
                ]);
            }

            if (! $account->stripe_customer_id && $lockedUser->stripe_customer_id) {
                $account->forceFill(['stripe_customer_id' => $lockedUser->stripe_customer_id])->save();
            }

            FamilyAccountMember::query()->updateOrCreate(
                [
                    'family_account_id' => $account->id,
                    'user_id' => $lockedUser->id,
                ],
                [
                    'access_level' => FamilyAccountMember::ACCESS_OWNER,
                    'status' => FamilyAccountMember::STATUS_ACTIVE,
                    'joined_at' => $account->created_at ?? now(),
                    'ended_at' => null,
                    'ended_by_user_id' => null,
                ]
            );

            FamilyAccountActivityLog::query()->firstOrCreate(
                [
                    'family_account_id' => $account->id,
                    'actor_user_id' => null,
                    'action' => $activity,
                    'subject_user_id' => $lockedUser->id,
                ],
                ['metadata' => ['source' => $activity]]
            );

            return $account->fresh(['owner', 'activeMemberships.user']);
        });
    }
}
