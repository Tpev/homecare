<?php

namespace App\Services\FamilyAccounts;

use App\Exceptions\Payments\PaymentException;
use App\Models\FamilyAccount;
use App\Models\FamilyAccountActivityLog;
use App\Models\FamilyAccountMember;
use App\Models\User;
use App\Services\Payments\StripeClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FamilyAccountOwnershipService
{
    public function __construct(private readonly StripeClient $stripe) {}

    public function transfer(User $admin, FamilyAccount $account, FamilyAccountMember $destination, string $reason): void
    {
        if (! $admin->isAdministrator()) {
            throw ValidationException::withMessages(['transfer' => 'Only an administrator can transfer ownership.']);
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages(['transferReason' => 'Record a clear reason of at least 10 characters.']);
        }

        DB::transaction(function () use ($admin, $account, $destination, $reason): void {
            $lockedAccount = FamilyAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $memberships = FamilyAccountMember::query()
                ->where('family_account_id', $lockedAccount->id)
                ->where('status', FamilyAccountMember::STATUS_ACTIVE)
                ->lockForUpdate()
                ->get();
            $currentOwner = $memberships->first(fn (FamilyAccountMember $membership) => $membership->isOwner()
                && (int) $membership->user_id === (int) $lockedAccount->owner_user_id);
            $newOwner = $memberships->firstWhere('id', $destination->id);

            if ($lockedAccount->status !== FamilyAccount::STATUS_ACTIVE || ! $currentOwner || ! $newOwner
                || $newOwner->isOwner() || $newOwner->user?->role !== 'family') {
                throw ValidationException::withMessages(['transfer' => 'Choose an active family member on this account.']);
            }

            $oldOwnerId = (int) $currentOwner->user_id;
            $newOwnerId = (int) $newOwner->user_id;

            // Keep receipt and billing contact ownership aligned. This runs before
            // the local writes so an unavailable Stripe account leaves local state unchanged.
            try {
                $this->stripe->updateFamilyCustomerOwner($lockedAccount, $newOwner->user);
            } catch (PaymentException $exception) {
                throw ValidationException::withMessages(['transfer' => $exception->userMessage]);
            }

            $currentOwner->forceFill(['access_level' => FamilyAccountMember::ACCESS_MEMBER])->save();
            $newOwner->forceFill(['access_level' => FamilyAccountMember::ACCESS_OWNER])->save();
            $lockedAccount->forceFill(['owner_user_id' => $newOwnerId])->save();

            foreach (FamilyAccountBackfill::FAMILY_OWNED_TABLES as $table) {
                DB::table($table)
                    ->where('family_account_id', $lockedAccount->id)
                    ->update(['family_user_id' => $newOwnerId]);
            }

            User::query()->whereKey($newOwnerId)->update(['stripe_customer_id' => $lockedAccount->stripe_customer_id]);
            User::query()->whereKey($oldOwnerId)->update(['stripe_customer_id' => null]);

            FamilyAccountActivityLog::query()->create([
                'family_account_id' => $lockedAccount->id,
                'actor_user_id' => $admin->id,
                'action' => 'ownership_transferred',
                'subject_user_id' => $newOwnerId,
                'metadata' => [
                    'previous_owner_user_id' => $oldOwnerId,
                    'reason' => $reason,
                ],
            ]);
        });
    }
}
