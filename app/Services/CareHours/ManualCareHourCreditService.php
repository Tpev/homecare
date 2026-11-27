<?php

namespace App\Services\CareHours;

use App\Models\CareHourAccount;
use App\Models\CareHourLedgerEntry;
use App\Models\CareHourLot;
use App\Models\FamilyAccountActivityLog;
use App\Models\User;
use App\Services\CustomerCare\CustomerCareAccess;
use App\Support\MarketplacePricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ManualCareHourCreditService
{
    public function credit(User $actor, User $customer, array $input): CareHourAccount
    {
        CustomerCareAccess::authorize($actor);
        abort_unless($customer->role === 'family' && $customer->access_revoked_at === null, 422);
        $input['reference'] = trim((string) ($input['reference'] ?? ''));
        if (! str_starts_with($input['reference'], 'in_')) {
            $input['reference'] = mb_strtolower($input['reference']);
        }
        $input['reason'] = trim((string) ($input['reason'] ?? ''));
        $data = Validator::make($input, [
            'request_key' => ['required', 'uuid'],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:10000', 'multiple_of:0.25'],
            'amount' => ['required', 'numeric', 'min:0', 'max:1000000', 'decimal:0,2'],
            'reference' => ['required', 'string', 'max:120'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'processing_fee' => ['nullable', 'numeric', 'min:0', 'max:1000000', 'decimal:0,2'],
        ])->validate();
        $minutes = (int) round((float) $data['hours'] * 60);
        $amount = (int) round((float) $data['amount'] * 100);
        $fee = (int) round((float) ($data['processing_fee'] ?? 0) * 100);
        if ($fee > $amount) {
            throw ValidationException::withMessages(['processing_fee' => 'The processing fee cannot exceed the payment.']);
        }

        return DB::transaction(function () use ($actor, $customer, $input, $data, $minutes, $amount, $fee): CareHourAccount {
            // Wallet creation locks the family before any lot or ledger change.
            $account = app(CareHourIdentityService::class)->ensureFamilyWallet($customer, staffActor: $actor);
            $existing = DB::table('care_hour_manual_credits')->where('request_key', $data['request_key'])
                ->orWhere(fn ($q) => $q->where('family_account_id', $account->family_account_id)->where('reference', $data['reference']))
                ->when(str_starts_with($data['reference'], 'in_'), fn ($q) => $q
                    ->orWhere('external_payment_reference', mb_strtolower($data['reference']))
                    ->orWhereRaw('LOWER(reference) = ?', [mb_strtolower($data['reference'])]))->first();
            if ($existing) {
                if ((int) $existing->family_account_id !== (int) $account->family_account_id
                    || (int) $existing->minutes !== $minutes || (int) $existing->amount_cents !== $amount
                    || $existing->reference !== $data['reference'] || $existing->reason !== $data['reason']
                    || CareHourLot::findOrFail($existing->care_hour_lot_id)->processing_fee_cents !== $fee) {
                    throw ValidationException::withMessages(['reference' => 'This reference or submission was already used for a different credit. Review the recorded credit.']);
                }

                return $account;
            }
            if ($reason = app(CareHourEligibilityService::class)->reasonForManualCredit((int) $account->family_account_id)) {
                throw ValidationException::withMessages(['hours' => $reason]);
            }
            $agreement = app(MarketplacePricing::class)->activeCareAssistance((int) $account->family_account_id);
            if ($agreement) {
                Validator::make($input, [
                    'expected_agreement_id' => ['required', 'integer', 'in:'.$agreement->id],
                    'processing_fee' => ['required', 'numeric'],
                ])->validate();
                $expected = app(MarketplacePricing::class)->prorateHourlyCents(
                    $agreement->family_care_rate_cents + $agreement->family_processing_fee_rate_cents, $minutes);
                if ($amount !== $expected) {
                    throw ValidationException::withMessages(['amount' => 'Enter the full paid amount at the Care Assistance rate, before processing fees.']);
                }
            } elseif (! empty($input['expected_agreement_id'])) {
                throw ValidationException::withMessages(['hours' => 'Care Assistance changed. Reload the account before recording hours.']);
            }
            $lot = CareHourLot::create([
                'care_hour_account_id' => $account->id, 'care_hour_purchase_id' => null,
                'care_assistance_pricing_agreement_id' => $agreement?->id,
                'paid_minutes' => $minutes, 'bonus_minutes' => 0, 'total_minutes' => $minutes,
                'processing_fee_cents' => $fee, 'status' => 'available', 'issued_at' => now(),
            ]);
            $creditId = DB::table('care_hour_manual_credits')->insertGetId([
                'request_key' => $data['request_key'], 'family_account_id' => $account->family_account_id,
                'care_hour_lot_id' => $lot->id, 'actor_user_id' => $actor->id,
                'minutes' => $minutes, 'amount_cents' => $amount, 'reference' => $data['reference'],
                'external_payment_reference' => str_starts_with($data['reference'], 'in_') ? mb_strtolower($data['reference']) : null,
                'reason' => $data['reason'], 'created_at' => now(), 'updated_at' => now(),
            ]);
            CareHourLedgerEntry::create([
                'care_hour_account_id' => $account->id, 'care_hour_lot_id' => $lot->id,
                'actor_user_id' => $actor->id, 'type' => 'manual_credit', 'minutes' => $minutes,
                'available_delta' => $minutes, 'description' => 'Prepaid hours added by the care team',
                'idempotency_key' => 'manual-credit:'.$data['request_key'],
                'metadata' => ['manual_credit_id' => $creditId],
            ]);
            FamilyAccountActivityLog::create([
                'family_account_id' => $account->family_account_id, 'actor_user_id' => $actor->id,
                'subject_user_id' => $customer->id, 'action' => 'care_hours_manual_credit',
                'metadata' => ['manual_credit_id' => $creditId, 'minutes' => $minutes,
                    'amount_cents' => $amount, 'processing_fee_cents' => $fee,
                    'care_assistance_pricing_agreement_id' => $agreement?->id,
                    'reference' => $data['reference'], 'reason' => $data['reason']],
            ]);

            return $account;
        }, 3);
    }
}
