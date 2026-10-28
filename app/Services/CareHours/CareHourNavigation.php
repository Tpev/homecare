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
        if (config('care_hours.enabled')) {
            return true;
        }
        if (! Schema::hasTable('care_hour_accounts')) {
            return false;
        }
        // Reading existing navigation must never enroll a recipient or create a family.
        $membership = app(FamilyAccountContext::class)->membershipFor($user, false);

        return $membership && CareHourAccount::query()->where('family_account_id', $membership->family_account_id)->exists();
    }
}
