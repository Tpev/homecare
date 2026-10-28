<?php

namespace App\Services\CareHours;

use App\Models\CareBooking;
use App\Models\CareHourAccount;
use App\Models\CareHourGift;
use App\Models\CareHourRecipientLink;
use App\Models\CareRecipient;
use App\Models\CareRecipientProfile;
use App\Models\User;
use App\Services\CareRecipientProfiles\CareRecipientProfileService;
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
        return CareHourAccount::query()->where('family_account_id', $this->families->account($user)->id)->orderBy('display_name')->get();
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

    public function enroll(CareRecipient $recipient, User $actor, ?CareHourGift $paidGift = null): CareHourAccount
    {
        $this->assertPilotEnrollment($actor, $paidGift);
        $family = $this->families->account($actor);
        $this->assertRecipientAccess($recipient, $actor);

        return DB::transaction(function () use ($recipient, $actor, $family, $paidGift): CareHourAccount {
            // A family lock serializes enrollment, including recipients without optional profiles.
            $family->newQuery()->whereKey($family->id)->lockForUpdate()->firstOrFail();
            $linked = CareHourRecipientLink::query()->where('care_recipient_id', $recipient->id)->first();
            if ($linked) {
                return $linked->account;
            }
            if ($recipient->care_recipient_profile_id) {
                $account = $this->enrollProfile($recipient->careRecipientProfile, $actor, $paidGift);
            } else {
                $this->assertEnrollmentOpen($actor, $paidGift);
                $account = CareHourAccount::create([
                    'family_account_id' => $family->id,
                    'display_name' => trim((string) $recipient->full_name) ?: 'Care recipient',
                    'currency' => config('care_hours.currency', 'usd'),
                ]);
            }
            $this->linkRecipient($account, $recipient, $actor);

            return $account;
        }, 3);
    }

    public function enrollProfile(CareRecipientProfile $profile, User $actor, ?CareHourGift $paidGift = null): CareHourAccount
    {
        $this->assertPilotEnrollment($actor, $paidGift);
        $family = $this->families->account($actor);
        if ((int) $profile->family_account_id !== (int) $family->id || $profile->status === CareRecipientProfile::STATUS_ARCHIVED) {
            throw new AuthorizationException('Choose an existing care recipient in your family.');
        }

        return DB::transaction(function () use ($profile, $actor, $family, $paidGift): CareHourAccount {
            $family->newQuery()->whereKey($family->id)->lockForUpdate()->firstOrFail();
            $existing = CareHourAccount::query()->where('care_recipient_profile_id', $profile->id)->first();
            if ($existing) {
                return $existing;
            }
            $linkedAccounts = CareHourAccount::query()->where('family_account_id', $family->id)
                ->whereHas('recipientLinks.recipient', fn ($query) => $query->where('care_recipient_profile_id', $profile->id))->get();
            if ($linkedAccounts->count() > 1) {
                throw ValidationException::withMessages(['care_hours' => 'This recipient needs an account review. Please contact support.']);
            }
            if ($linkedAccounts->count() === 1) {
                $linked = $linkedAccounts->first();
                if ($linked->care_recipient_profile_id && (int) $linked->care_recipient_profile_id !== (int) $profile->id) {
                    throw ValidationException::withMessages(['care_hours' => 'This recipient is already linked to a different care profile.']);
                }
                $linked->update(['care_recipient_profile_id' => $profile->id]);

                return $linked;
            }
            $this->assertEnrollmentOpen($actor, $paidGift);

            return CareHourAccount::create([
                'family_account_id' => $family->id, 'care_recipient_profile_id' => $profile->id,
                'display_name' => trim((string) $profile->preferred_name) ?: $profile->full_name,
                'currency' => config('care_hours.currency', 'usd'),
            ]);
        }, 3);
    }

    public function linkRecipient(CareHourAccount $account, CareRecipient $recipient, User $actor): void
    {
        $this->assertAccess($account, $actor);
        $this->assertRecipientAccess($recipient, $actor);
        if (! $account->active) {
            throw ValidationException::withMessages(['care_hours' => 'This care recipient is unavailable.']);
        }
        $request = $recipient->careRequest;
        $familyId = $request->family_account_id ?: $this->families->account($actor)->id;
        if ((int) $account->family_account_id !== (int) $familyId
            || ($account->care_recipient_profile_id && $recipient->care_recipient_profile_id
                && (int) $account->care_recipient_profile_id !== (int) $recipient->care_recipient_profile_id)) {
            throw ValidationException::withMessages(['care_hours' => 'These hours belong to a different care recipient.']);
        }

        DB::transaction(function () use ($account, $recipient, $actor): void {
            $account->familyAccount()->lockForUpdate()->firstOrFail();
            $account = CareHourAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $recipient = $recipient->newQuery()->whereKey($recipient->id)->lockForUpdate()->firstOrFail();
            if ($recipient->care_recipient_profile_id) {
                CareRecipientProfile::query()->whereKey($recipient->care_recipient_profile_id)->lockForUpdate()->firstOrFail();
                $profileAccount = CareHourAccount::query()->where('care_recipient_profile_id', $recipient->care_recipient_profile_id)->first();
                if (($profileAccount && $profileAccount->id !== $account->id)
                    || ($account->care_recipient_profile_id && (int) $account->care_recipient_profile_id !== (int) $recipient->care_recipient_profile_id)) {
                    throw ValidationException::withMessages(['care_hours' => 'This care profile already belongs to a different care-hours account.']);
                }
                if (! $account->care_recipient_profile_id) {
                    $account->update(['care_recipient_profile_id' => $recipient->care_recipient_profile_id]);
                }
            }
            $existing = CareHourRecipientLink::query()->where('care_recipient_id', $recipient->id)->first();
            if ($existing && (int) $existing->care_hour_account_id !== (int) $account->id) {
                throw ValidationException::withMessages(['care_hours' => 'This recipient already has a care-hours account.']);
            }
            CareHourRecipientLink::firstOrCreate(['care_recipient_id' => $recipient->id], [
                'care_hour_account_id' => $account->id, 'linked_by_user_id' => $actor->id,
            ]);
        }, 3);
    }

    /** Only the validated paid-gift flow may introduce a minimal private self identity. */
    public function enrollSelfForGift(User $actor, CareHourGift $paidGift): CareHourAccount
    {
        if ($actor->role !== 'family' || ! $this->giftCanEnroll($paidGift)) {
            throw ValidationException::withMessages(['gift_code' => 'This gift is unavailable for a new recipient.']);
        }
        $family = $this->families->account($actor);

        return DB::transaction(function () use ($actor, $family, $paidGift): CareHourAccount {
            $family->newQuery()->whereKey($family->id)->lockForUpdate()->firstOrFail();
            $profiles = CareRecipientProfile::query()->where('family_account_id', $family->id)
                ->where('recipient_is_requester', true)->where(fn ($query) => $query->where('created_by_user_id', $actor->id)
                ->orWhere(fn ($legacy) => $legacy->whereNull('created_by_user_id')->where('legacy_family_user_id', $actor->id)))->get();
            $recipients = CareRecipient::query()->with('careRequest')
                ->where(fn ($query) => $query->where('recipient_is_requester', true)->orWhere('relationship_to_family', 'self'))
                ->whereHas('careRequest', fn ($query) => $query
                    ->where(fn ($scope) => $scope->where('family_account_id', $family->id)
                        ->orWhere(fn ($legacy) => $legacy->whereNull('family_account_id')->where('family_user_id', $family->owner_user_id)))
                    ->where(fn ($identity) => $identity->where('created_by_user_id', $actor->id)
                        ->orWhere(fn ($legacy) => $legacy->whereNull('created_by_user_id')->where('family_user_id', $actor->id))))->get();
            $profileIds = $profiles->pluck('id')->merge($recipients->pluck('care_recipient_profile_id')->filter())->unique()->values();
            $accountIds = CareHourRecipientLink::query()->whereIn('care_recipient_id', $recipients->modelKeys())->pluck('care_hour_account_id')
                ->merge(CareHourAccount::query()->whereIn('care_recipient_profile_id', $profileIds)->pluck('id'))->unique()->values();
            if ($profileIds->count() > 1 || $accountIds->count() > 1) {
                $this->ambiguousSelf();
            }
            $existing = $accountIds->isNotEmpty() ? CareHourAccount::query()->whereKey($accountIds->first())->lockForUpdate()->firstOrFail() : null;
            if ($existing && ((int) $existing->family_account_id !== (int) $family->id || ! $existing->active || $existing->currency !== 'usd')) {
                $this->ambiguousSelf();
            }
            if ($existing?->care_recipient_profile_id) {
                $profileIds = $profileIds->push($existing->care_recipient_profile_id)->unique()->values();
            }
            if ($profileIds->count() > 1) {
                $this->ambiguousSelf();
            }
            $profile = $profileIds->isNotEmpty() ? CareRecipientProfile::query()->whereKey($profileIds->first())->lockForUpdate()->firstOrFail() : null;
            if ($profile && ((int) $profile->family_account_id !== (int) $family->id || $profile->isArchived()
                || ! $profile->recipient_is_requester
                || ($profile->created_by_user_id !== null ? (int) $profile->created_by_user_id !== (int) $actor->id
                    : (int) $profile->legacy_family_user_id !== (int) $actor->id))) {
                $this->ambiguousSelf();
            }
            if (! $profile) {
                $name = trim((string) $actor->name);
                if ($name === '') {
                    throw ValidationException::withMessages(['recipient' => 'Add your name to your account before claiming these hours for yourself.']);
                }
                $profile = app(CareRecipientProfileService::class)->saveDraft($actor, null, [
                    'full_name' => $name, 'recipient_is_requester' => true,
                ]);
            }
            if ($existing && ! $existing->care_recipient_profile_id) {
                $existing->update(['care_recipient_profile_id' => $profile->id]);
            }
            $account = $existing ?: $this->enrollProfile($profile, $actor, $paidGift);
            foreach ($recipients as $recipient) {
                $this->linkRecipient($account, $recipient, $actor);
            }

            return $account;
        }, 3);
    }

    public function forBooking(CareBooking $booking): ?CareHourAccount
    {
        $recipient = $booking->careRequest?->recipient;
        if ($recipient instanceof CareRecipient) {
            $link = CareHourRecipientLink::query()->where('care_recipient_id', $recipient->id)->first();
            if ($link) {
                return $link->account;
            }
            if ($recipient->care_recipient_profile_id) {
                return CareHourAccount::query()->where('care_recipient_profile_id', $recipient->care_recipient_profile_id)
                    ->where('family_account_id', $booking->family_account_id)->first();
            }
        }
        if ($booking->care_plan_id && Schema::hasTable('care_hour_plan_preferences')) {
            $accountId = DB::table('care_hour_plan_preferences')->where('care_plan_id', $booking->care_plan_id)->value('care_hour_account_id');
            if ($accountId) {
                return CareHourAccount::query()->whereKey($accountId)->where('family_account_id', $booking->family_account_id)->first();
            }
        }

        return null;
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
        // An existing paid entitlement can enroll an existing identity even when discovery is paused.
        // No caller-controlled boolean or global configuration override can grant this exception.
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

    private function ambiguousSelf(): never
    {
        throw ValidationException::withMessages(['recipient' => 'Your existing recipient records need review. Choose your existing recipient or contact support before claiming for yourself.']);
    }
}
