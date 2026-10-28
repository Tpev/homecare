<?php

namespace App\Services\CareHours;

use App\Models\CareHourAccount;
use App\Models\User;

/** Narrows new exposure without revoking paid entitlements or payment recovery. */
class CareHourAccess
{
    public function pilotOnly(): bool
    {
        return config('care_hours.pilot_only', true) !== false;
    }

    public function allows(?User $user): bool
    {
        $emails = config('care_hours.pilot_emails');
        if (! is_array($emails) || ! array_is_list($emails)) {
            return false;
        }
        foreach ($emails as $email) {
            if (! is_string($email) || strlen(trim($email)) > 254 || ! filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
                return false;
            }
        }
        if (! $this->pilotOnly()) {
            return true;
        }

        return $user?->role === 'family' && in_array(strtolower(trim($user->email)),
            array_map(fn ($email) => strtolower(trim($email)), $emails), true);
    }

    public function canDiscover(?User $user): bool
    {
        return $user?->role === 'family' && config('care_hours.enabled', false) && $this->allows($user);
    }

    public function canEnroll(?User $user): bool
    {
        return $this->canDiscover($user) && config('care_hours.enrollment_enabled', false);
    }

    public function canBuy(?User $user): bool
    {
        return config('care_hours.enabled', false) && config('care_hours.sales_enabled', false) && $this->allows($user);
    }

    public function canBuyGift(?User $user): bool
    {
        return $this->canBuy($user) && config('care_hours.gifts_enabled', false);
    }

    public function canContribute(?User $user): bool
    {
        return $this->canBuy($user) && config('care_hours.contributions_enabled', false);
    }

    public function canUseAccount(CareHourAccount $account, ?User $user): bool
    {
        // Existing issued hours remain usable if the pilot list or sales switches change.
        // Ownership is checked by each caller's normal family authorization.
        return $this->allows($user) || $account->lots()->exists();
    }
}
