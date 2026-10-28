<?php

namespace App\Services\CareHours;

use App\Models\CareBooking;
use App\Models\CareHourAccount;
use App\Models\CareHourRequestPreference;
use App\Models\CareRecipient;
use App\Models\CareRecipientProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\FamilyAccount;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Support\MarketplacePricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** A request preference never reserves hours or prepares a payment. */
class CareHourRequestFundingService
{
    public function available(): bool
    {
        return Schema::hasTable('care_hour_request_preferences')
            && app(PrepaidBookingFundingService::class)->available();
    }

    public function accountForRequest(CareRequest $request, User $actor, ?CareRequestApplication $application = null): ?CareHourAccount
    {
        if (! $this->available() || ! app(FamilyAccountContext::class)->canAccessRecord($actor, $request)
            || $request->care_arrangement_id || $request->pricing_agreement_id
            || ($application && ((int) $application->care_request_id !== (int) $request->id
                || ($request->request_type === CareRequest::TYPE_RECURRING && $application->selected_days !== null)))) {
            return null;
        }
        $familyId = (int) ($request->family_account_id ?: app(FamilyAccountContext::class)->account($actor)->id);
        if ($application && app(MarketplacePricing::class)->quoteForPair($familyId, (int) $application->caregiver_user_id, 60)['pricing_agreement_id']) {
            return null;
        }
        $booking = new CareBooking(['family_account_id' => $familyId]);
        $booking->setRelation('careRequest', $request);

        return $this->usable(app(CareHourIdentityService::class)->forBooking($booking), $familyId, $actor);
    }

    public function accountForProfile(?int $profileId, User $actor): ?CareHourAccount
    {
        if (! $profileId || ! $this->available()) {
            return null;
        }
        $familyId = (int) app(FamilyAccountContext::class)->account($actor)->id;
        $account = CareHourAccount::query()->where('family_account_id', $familyId)
            ->where('care_recipient_profile_id', $profileId)
            ->whereHas('profile', fn ($query) => $query->where('status', '!=', CareRecipientProfile::STATUS_ARCHIVED))->first();

        return $this->usable($account, $familyId, $actor);
    }

    public function accountForSelf(User $actor): ?CareHourAccount
    {
        if (! $this->available()) {
            return null;
        }
        $familyId = (int) app(FamilyAccountContext::class)->account($actor)->id;
        // Match the authenticated person's explicit self records, never their name
        // or another family member's recipient profile.
        $profiles = CareRecipientProfile::query()->where('family_account_id', $familyId)
            ->where('status', '!=', CareRecipientProfile::STATUS_ARCHIVED)->where('recipient_is_requester', true)
            ->where(fn ($query) => $query->where('created_by_user_id', $actor->id)
                ->orWhere(fn ($legacy) => $legacy->whereNull('created_by_user_id')->where('legacy_family_user_id', $actor->id)))->pluck('id');
        $recipients = CareRecipient::query()->where('recipient_is_requester', true)
            ->whereHas('careRequest', fn ($query) => $query->forFamilyAccount(app(FamilyAccountContext::class)->account($actor))
                ->where(fn ($owner) => $owner->where('created_by_user_id', $actor->id)
                    ->orWhere(fn ($legacy) => $legacy->whereNull('created_by_user_id')->where('family_user_id', $actor->id))))->get();
        $profileIds = $profiles->merge($recipients->pluck('care_recipient_profile_id')->filter())->unique();
        if ($profileIds->count() > 1) {
            return null;
        }
        $accounts = CareHourAccount::query()->where('family_account_id', $familyId)
            ->where(fn ($query) => $query->whereIn('care_recipient_profile_id', $profileIds)
                ->orWhereHas('recipientLinks', fn ($links) => $links->whereIn('care_recipient_id', $recipients->modelKeys())))->get();

        if ($accounts->count() !== 1) {
            return null;
        }
        $account = $accounts->first();
        if ($account->care_recipient_profile_id) {
            $profile = $account->profile;
            if (! $profile?->recipient_is_requester
                || (int) ($profile->created_by_user_id ?? $profile->legacy_family_user_id) !== (int) $actor->id) {
                return null;
            }
        }

        return $this->usable($account, $familyId, $actor);
    }

    public function preferenceFor(CareRequest $request): ?CareHourRequestPreference
    {
        return $this->available() ? CareHourRequestPreference::where('care_request_id', $request->id)->first() : null;
    }

    public function defaultAccountId(CareRequest $request, User $actor, ?CareRequestApplication $application = null): ?int
    {
        $preference = $this->preferenceFor($request);
        if ($preference && ! $preference->enabled) {
            return null;
        }
        $account = $this->accountForRequest($request, $actor, $application);
        if (! $account || ($preference && (int) $preference->care_hour_account_id !== (int) $account->id)) {
            return null;
        }

        return $account->balance()['available_minutes'] > 0 ? $account->id : null;
    }

    public function remember(CareRequest $request, User $actor, ?int $accountId): void
    {
        abort_unless(app(FamilyAccountContext::class)->canAccessRecord($actor, $request), 403);
        if (! $this->available()) {
            if ($accountId !== null) {
                throw ValidationException::withMessages(['careHourAccountId' => 'Care hours are not available for this request.']);
            }

            return;
        }
        DB::transaction(function () use ($request, $actor, $accountId): void {
            FamilyAccount::query()->whereKey($request->family_account_id ?: app(FamilyAccountContext::class)->account($actor)->id)->lockForUpdate()->firstOrFail();
            if ($accountId !== null && (int) ($this->accountForRequest($request, $actor)?->id ?? 0) !== $accountId) {
                throw ValidationException::withMessages(['careHourAccountId' => 'Choose the care hours belonging to this recipient.']);
            }
            CareHourRequestPreference::updateOrCreate(['care_request_id' => $request->id], [
                'care_hour_account_id' => $accountId, 'enabled' => $accountId !== null, 'selected_by_user_id' => $actor->id,
            ]);
        }, 3);
    }

    private function usable(?CareHourAccount $account, int $familyId, User $actor): ?CareHourAccount
    {
        return $account && (int) $account->family_account_id === $familyId && $account->active && $account->currency === 'usd'
            && app(CareHourAccess::class)->canUseAccount($account, $actor)
            && $account->familyAccount?->status === FamilyAccount::STATUS_ACTIVE
            && (! $account->care_recipient_profile_id || $account->profile?->status !== CareRecipientProfile::STATUS_ARCHIVED)
            && app(CareHourEligibilityService::class)->reasonFor($account) === null ? $account : null;
    }
}
