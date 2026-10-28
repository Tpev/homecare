<?php

namespace App\Services\CareHours;

use App\Models\CareHourAccount;
use App\Models\CarePricingAgreement;
use Illuminate\Validation\ValidationException;

/** Check recipient-wide restrictions before accepting new prepaid money or a gift. */
class CareHourEligibilityService
{
    public function reasonForFamily(int $familyAccountId): ?string
    {
        // A caregiver-specific agreement still leaves other standard-priced care eligible.
        if (CarePricingAgreement::query()->where('family_account_id', $familyAccountId)
            ->where('active', true)->where('all_caregivers', true)->exists()) {
            return 'Care hours cannot be used with this family’s custom pricing. Contact support before buying or claiming hours.';
        }

        return null;
    }

    public function reasonFor(CareHourAccount $account): ?string
    {
        return $this->reasonForFamily((int) $account->family_account_id);
    }

    public function assertFamilyEligible(int $familyAccountId, string $field = 'purchase'): void
    {
        if ($reason = $this->reasonForFamily($familyAccountId)) {
            throw ValidationException::withMessages([$field => $reason]);
        }
    }
}
