<?php

namespace App\Support;

use App\Models\CareHourAccount;
use App\Models\CareRecipientProfile;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;

/** Family credit is available in the normal request flow, independently of a care profile. */
class CareHourRequestHandoff
{
    public static function url(CareHourAccount $account, User $actor): string
    {
        app(\App\Services\CareHours\CareHourIdentityService::class)->assertAccess($account, $actor);

        return route('family.requests.create');
    }

    public static function draftFor(int $accountId, User $actor, bool $lock = false): ?CareRecipientProfile
    {
        $family = app(FamilyAccountContext::class)->account($actor);
        if ($lock) {
            $family->newQuery()->whereKey($family->id)->lockForUpdate()->firstOrFail();
        }
        $account = CareHourAccount::query()->whereKey($accountId)->where('family_account_id', $family->id)
            ->where('active', true)->when($lock, fn ($query) => $query->lockForUpdate())->first();
        if (! $account?->care_recipient_profile_id) {
            return null;
        }

        return CareRecipientProfile::query()->whereKey($account->care_recipient_profile_id)
            ->where('family_account_id', $family->id)->where('status', CareRecipientProfile::STATUS_DRAFT)
            ->whereNull('latest_ready_version_id')->where('recipient_is_requester', true)
            ->where(fn ($query) => $query->where('created_by_user_id', $actor->id)
                ->orWhere(fn ($legacy) => $legacy->whereNull('created_by_user_id')->where('legacy_family_user_id', $actor->id)))
            ->when($lock, fn ($query) => $query->lockForUpdate())->first();
    }
}
