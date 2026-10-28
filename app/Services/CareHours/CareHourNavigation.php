<?php

namespace App\Services\CareHours;

use App\Models\CareHourAccount;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Support\Facades\Schema;

class CareHourNavigation
{
    public function visible(?User $user): bool
    {
        if (! $user || $user->role !== 'family') {
            return false;
        }
        if (app(CareHourAccess::class)->canDiscover($user)) {
            return true;
        }
        if (! Schema::hasTable('care_hour_accounts')) {
            return false;
        }
        // Reading existing navigation must never enroll a recipient or create a family.
        $membership = app(FamilyAccountContext::class)->membershipFor($user, false);

        if (! $membership) {
            return false;
        }
        $accounts = CareHourAccount::query()->where('family_account_id', $membership->family_account_id);
        if (! app(CareHourAccess::class)->allows($user)) {
            if (! Schema::hasTable('care_hour_lots')) {
                return false;
            }
            $accounts->where(fn ($existing) => $existing->whereHas('lots')
                ->when(Schema::hasTable('care_hour_purchases'), fn ($query) => $query->orWhereHas('purchases')));
        }

        return $accounts->exists();
    }
}
