<?php

namespace App\Services\CareHours;

use App\Models\CareBooking;
use App\Models\CareHourAccount;
use App\Models\CareHourGift;
use App\Models\CareRecipient;
use App\Models\CareRecipientProfile;
use App\Models\FamilyAccount;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CareHourIdentityService
{
    public function __construct(private readonly FamilyAccountContext $families) {}

    public function accountsFor(User $user): Collection
    {
        $wallet = $this->walletFor($user);

        return new Collection($wallet ? [$wallet] : []);
    }

    public function walletFor(User $user): ?CareHourAccount
    {
        return $this->walletForFamily((int) $this->families->account($user)->id);
    }

    /** Read only: opening the dashboard never creates a wallet or enrollment. */
    public function walletForFamily(int $familyId): ?CareHourAccount
    {
        if ($familyId < 1) {
            return null;
        }
        if (Schema::hasTable('care_hour_family_wallets')) {
            $id = DB::table('care_hour_family_wallets')->where('family_account_id', $familyId)->value('care_hour_account_id');
            if ($id) {
                return CareHourAccount::query()->whereKey($id)->where('family_account_id', $familyId)
                    ->where('currency', config('care_hours.currency', 'usd'))->firstOrFail();
            }
        }

        // Reuse historical balances without rewriting their financial references.
        return CareHourAccount::query()->where('family_account_id', $familyId)
            ->where('currency', config('care_hours.currency', 'usd'))->orderByDesc('active')->orderBy('id')->first();
    }

    public function ensureFamilyWallet(User $actor, ?CareHourGift $paidGift = null): CareHourAccount
    {
        $this->assertPilotEnrollment($actor, $paidGift);
        $family = $this->families->account($actor);
        if (! Schema::hasTable('care_hour_family_wallets')) {
            throw ValidationException::withMessages(['care_hours' => 'Prepaid care is being updated. Please try again shortly.']);
        }

        return DB::transaction(function () use ($actor, $family, $paidGift): CareHourAccount {
            $lockedFamily = FamilyAccount::query()->whereKey($family->id)->lockForUpdate()->firstOrFail();
            if ($lockedFamily->status !== FamilyAccount::STATUS_ACTIVE) {
                throw new AuthorizationException('This family account is unavailable.');
            }
            $account = $this->walletForFamily((int) $family->id);
            if (! $account) {
                $this->assertEnrollmentOpen($actor, $paidGift);
                $account = CareHourAccount::create([
                    'family_account_id' => $family->id, 'display_name' => 'Family care balance',
                    'currency' => config('care_hours.currency', 'usd'),
                ]);
            }
            if (! $account->active) {
                throw ValidationException::withMessages(['care_hours' => 'This family care balance is unavailable. Please contact support.']);
            }
            DB::table('care_hour_family_wallets')->insertOrIgnore([
                'family_account_id' => $family->id, 'care_hour_account_id' => $account->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return $account;
        }, 3);
    }

    public function recipientsFor(User $user): Collection
    {
        $family = $this->families->account($user);

        return CareRecipient::query()->with('careRequest')->whereHas('careRequest', fn ($q) => $q
            ->where('family_account_id', $family->id)
            ->orWhere(fn ($legacy) => $legacy->whereNull('family_account_id')->where('family_user_id', $family->owner_user_id)))
            ->orderBy('full_name')->get();
    }

    public function profilesFor(User $user): Collection
    {
        return CareRecipientProfile::query()->where('family_account_id', $this->families->account($user)->id)
            ->where('status', '!=', CareRecipientProfile::STATUS_ARCHIVED)->orderBy('full_name')->get();
    }

    public function assertAccess(CareHourAccount $account, User $user): void
    {
        if (! $this->families->canAccessAccount($user, (int) $account->family_account_id)) {
            throw new AuthorizationException('You do not have access to these care hours.');
        }
    }

    public function isOwner(CareHourAccount $account, User $user): bool
    {
        return $this->families->canAccessAccount($user, (int) $account->family_account_id) && $this->families->isOwner($user);
    }

    /** Compatibility for old entry points; the supplied record grants no wallet ownership. */
    public function enroll(CareRecipient $recipient, User $actor, ?CareHourGift $paidGift = null): CareHourAccount
    {
        $this->assertPilotEnrollment($actor, $paidGift);
        $this->assertRecipientAccess($recipient, $actor);

        return $this->ensureFamilyWallet($actor, $paidGift);
    }

    public function enrollProfile(CareRecipientProfile $profile, User $actor, ?CareHourGift $paidGift = null): CareHourAccount
    {
        $this->assertPilotEnrollment($actor, $paidGift);
        if ((int) $profile->family_account_id !== (int) $this->families->account($actor)->id || $profile->isArchived()) {
            throw new AuthorizationException('Choose an existing care profile in your family.');
        }

        return $this->ensureFamilyWallet($actor, $paidGift);
    }

    /** Historical recipient links remain audit records; funding now follows family ownership. */
    public function linkRecipient(CareHourAccount $account, CareRecipient $recipient, User $actor): void
    {
        $this->assertAccess($account, $actor);
        $this->assertRecipientAccess($recipient, $actor);
        $requestFamilyId = (int) ($recipient->careRequest->family_account_id ?: $this->families->account($actor)->id);
        if ($requestFamilyId !== (int) $account->family_account_id) {
            throw new AuthorizationException('These hours belong to a different family account.');
        }
    }

    /** A paid gift can establish a wallet without inventing a care-recipient profile. */
    public function enrollSelfForGift(User $actor, CareHourGift $paidGift): CareHourAccount
    {
        if ($actor->role !== 'family' || ! $this->giftCanEnroll($paidGift)) {
            throw ValidationException::withMessages(['gift_code' => 'This gift is unavailable for a new family balance.']);
        }

        return $this->ensureFamilyWallet($actor, $paidGift);
    }

    public function forBooking(CareBooking $booking): ?CareHourAccount
    {
        $familyId = (int) $booking->family_account_id;
        if ($familyId < 1) {
            $request = $booking->careRequest;
            if (! $booking->family_user_id || ($request && (int) $request->family_user_id !== (int) $booking->family_user_id)) {
                return null;
            }
            $familyId = (int) FamilyAccount::query()->where('owner_user_id', $booking->family_user_id)->value('id');
            if ($request?->family_account_id && (int) $request->family_account_id !== $familyId) {
                return null;
            }
        }
        if ($familyId < 1) {
            return null;
        }
        if ($booking->exists && Schema::hasTable('care_hour_booking_fundings')) {
            $accountId = DB::table('care_hour_booking_fundings')->where('care_booking_id', $booking->id)
                ->whereNotNull('committed_at')->value('care_hour_account_id');
            if ($accountId) {
                return CareHourAccount::query()->whereKey($accountId)->where('family_account_id', $familyId)->first();
            }
        }

        return $this->walletForFamily($familyId);
    }

    private function assertRecipientAccess(CareRecipient $recipient, User $actor): void
    {
        if (! $recipient->careRequest || ! $this->families->canAccessRecord($actor, $recipient->careRequest)) {
            throw new AuthorizationException('Choose an existing care recipient in your family.');
        }
    }

    private function assertPilotEnrollment(User $actor, ?CareHourGift $paidGift): void
    {
        if (! app(CareHourAccess::class)->allows($actor) && ! ($paidGift && $this->giftCanEnroll($paidGift))) {
            throw ValidationException::withMessages(['care_hours' => 'New care-hours accounts are temporarily unavailable.']);
        }
    }

    private function assertEnrollmentOpen(User $actor, ?CareHourGift $paidGift = null): void
    {
        if ($paidGift && $this->giftCanEnroll($paidGift)) {
            return;
        }
        if (! app(CareHourAccess::class)->canEnroll($actor)) {
            throw ValidationException::withMessages(['care_hours' => 'New care-hours accounts are temporarily unavailable.']);
        }
    }

    private function giftCanEnroll(CareHourGift $gift): bool
    {
        return CareHourGift::query()->whereKey($gift->id)->whereNotNull('ready_at')->whereNull('claimed_at')
            ->whereHas('purchase', fn ($query) => $query->where('status', 'paid')->whereNotNull('paid_at')
                ->whereNotNull('stripe_payment_intent_id')->whereNotNull('stripe_charge_id')->whereNotNull('livemode')
                ->whereNull('care_hour_account_id')->whereNull('family_account_id')->whereNull('correction_state')->where('amount_reversed_cents', 0)
                ->where(fn ($dispute) => $dispute->whereNull('dispute_id')->orWhere('dispute_status', 'won')))->exists();
    }
}
