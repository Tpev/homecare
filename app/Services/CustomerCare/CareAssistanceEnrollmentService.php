<?php

namespace App\Services\CustomerCare;

use App\Models\CareAssistanceApplication;
use App\Models\CarePricingAgreement;
use App\Models\FamilyAccount;
use App\Models\FamilyAccountActivityLog;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CareAssistanceEnrollmentService
{
    public function update(User $actor, User $customer, array $input): void
    {
        CustomerCareAccess::authorize($actor);
        abort_unless($customer->role === 'family' && $customer->access_revoked_at === null, 404);
        $input['reason'] = trim((string) ($input['reason'] ?? ''));
        $data = Validator::make($input, [
            'action' => ['required', 'in:activate,end'],
            'expected_agreement_id' => ['required', 'integer', 'min:0'],
            'application_id' => ['nullable', 'integer', 'min:1'],
            'approval_confirmed' => ['exclude_unless:action,activate', 'required', 'accepted'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ])->validate();
        $account = app(FamilyAccountContext::class)->account($customer);

        DB::transaction(function () use ($actor, $account, $data): void {
            FamilyAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $current = CarePricingAgreement::query()->where('family_account_id', $account->id)
                ->where('program', CarePricingAgreement::CARE_ASSISTANCE)->where('active', true)->lockForUpdate()->first();
            if ((int) ($current?->id ?? 0) !== (int) $data['expected_agreement_id']) {
                throw ValidationException::withMessages(['assistance' => 'Care Assistance changed in another session. Reload this account before saving.']);
            }
            if ($data['action'] === 'end') {
                if (! $current) {
                    return;
                }
                $current->update(['active' => false, 'all_caregivers' => null, 'ended_at' => now(), 'ended_by_user_id' => $actor->id]);
                $this->audit($account, $actor, 'care_assistance_ended', $current, $data['reason']);

                return;
            }
            if ($current) {
                return;
            }
            if (CarePricingAgreement::query()->where('family_account_id', $account->id)
                ->where(fn ($query) => $query->where('active', true)->orWhere('all_caregivers', true))->exists()) {
                throw ValidationException::withMessages(['assistance' => 'This account already has custom pricing. Ask an administrator to review it before activating Care Assistance.']);
            }
            $application = null;
            if (! empty($data['application_id'])) {
                $application = CareAssistanceApplication::query()->lockForUpdate()->find($data['application_id']);
                if (! $application || $application->status !== 'approved') {
                    throw ValidationException::withMessages(['application_id' => 'Choose an approved Care Assistance application.']);
                }
                if ($application->family_account_id && (int) $application->family_account_id !== (int) $account->id) {
                    throw ValidationException::withMessages(['application_id' => 'This application is already linked to another family account.']);
                }
                // Guest applications and a different applicant/payer are explicitly linked by staff.
                $application->forceFill(['family_account_id' => $account->id])->save();
            }
            $agreement = CarePricingAgreement::query()->create([
                'family_account_id' => $account->id, 'all_caregivers' => true, 'active' => true,
                'program' => CarePricingAgreement::CARE_ASSISTANCE,
                'care_assistance_application_id' => $application?->id,
                'created_by_user_id' => $actor->id, 'reason' => trim($data['reason']),
                'family_care_rate_cents' => 2300, 'family_processing_fee_rate_cents' => 0,
                'caregiver_gross_rate_cents' => 2000, 'caregiver_fee_policy' => 'successful_charge_balance_transaction',
            ]);
            $this->audit($account, $actor, 'care_assistance_activated', $agreement, $data['reason']);
        }, 3);
    }

    private function audit(FamilyAccount $account, User $actor, string $action, CarePricingAgreement $agreement, string $reason): void
    {
        FamilyAccountActivityLog::query()->create([
            'family_account_id' => $account->id, 'actor_user_id' => $actor->id,
            'action' => $action, 'subject_user_id' => $account->owner_user_id,
            'metadata' => ['pricing_agreement_id' => $agreement->id, 'application_id' => $agreement->care_assistance_application_id,
                'reason' => trim($reason), 'rates' => $agreement->snapshotAttributes(), 'money_moved' => false],
        ]);
    }
}
