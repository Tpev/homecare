<?php

namespace App\Services\CareHours;

use App\Models\CareBooking;
use App\Models\CareHourAccount;
use App\Models\CareHourRequestPreference;
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

    public function accountForFamily(User $actor): ?CareHourAccount
    {
        if (! $this->available()) {
            return null;
        }
        $familyId = (int) app(FamilyAccountContext::class)->account($actor)->id;

        return $this->usable(app(CareHourIdentityService::class)->walletFor($actor), $familyId, $actor);
    }

    public function accountForProfile(?int $profileId, User $actor): ?CareHourAccount
    {
        $familyId = (int) app(FamilyAccountContext::class)->account($actor)->id;
        if (! $profileId || ! CareRecipientProfile::query()->whereKey($profileId)->where('family_account_id', $familyId)
            ->where('status', '!=', CareRecipientProfile::STATUS_ARCHIVED)->exists()) {
            return null;
        }

        return $this->accountForFamily($actor);
    }

    public function accountForSelf(User $actor): ?CareHourAccount
    {
        return $this->accountForFamily($actor);
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
        if (! $account || ($preference && ! in_array((int) $preference->care_hour_account_id, $account->familySourceIds(), true))) {
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
            $account = $accountId !== null ? $this->accountForRequest($request, $actor) : null;
            if ($accountId !== null && (! $account || ! in_array($accountId, $account->familySourceIds(), true))) {
                throw ValidationException::withMessages(['careHourAccountId' => 'Choose your family account’s prepaid balance.']);
            }
            CareHourRequestPreference::updateOrCreate(['care_request_id' => $request->id], [
                'care_hour_account_id' => $account?->id, 'enabled' => $accountId !== null, 'selected_by_user_id' => $actor->id,
            ]);
        }, 3);
    }

    private function usable(?CareHourAccount $account, int $familyId, User $actor): ?CareHourAccount
    {
        return $account && (int) $account->family_account_id === $familyId && $account->active && $account->currency === 'usd'
            && app(CareHourAccess::class)->canUseAccount($account, $actor)
            && $account->familyAccount?->status === FamilyAccount::STATUS_ACTIVE
            && app(CareHourEligibilityService::class)->reasonFor($account) === null ? $account : null;
    }
}
