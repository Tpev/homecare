<?php

namespace App\Services\CareHours;

use App\Models\CareHourAccount;
use App\Models\CareHourLedgerEntry;
use App\Models\CareHourLot;
use App\Models\FamilyAccountActivityLog;
use App\Models\User;
use App\Services\CustomerCare\CustomerCareAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ManualCareHourCreditService
{
    public function credit(User $actor, User $customer, array $input): CareHourAccount
    {
        CustomerCareAccess::authorize($actor);
        abort_unless($customer->role === 'family' && $customer->access_revoked_at === null, 422);
        $input['reference'] = mb_strtolower(trim((string) ($input['reference'] ?? '')));
        $input['reason'] = trim((string) ($input['reason'] ?? ''));
        $data = Validator::make($input, [
            'request_key' => ['required', 'uuid'],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:10000', 'multiple_of:0.25'],
            'amount' => ['required', 'numeric', 'min:0', 'max:1000000', 'decimal:0,2'],
            'reference' => ['required', 'string', 'max:120'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ])->validate();
        $minutes = (int) round((float) $data['hours'] * 60);
        $amount = (int) round((float) $data['amount'] * 100);

        return DB::transaction(function () use ($actor, $customer, $data, $minutes, $amount): CareHourAccount {
            // Wallet creation locks the family before any lot or ledger change.
            $account = app(CareHourIdentityService::class)->ensureFamilyWallet($customer, staffActor: $actor);
            $existing = DB::table('care_hour_manual_credits')->where('request_key', $data['request_key'])
                ->orWhere(fn ($q) => $q->where('family_account_id', $account->family_account_id)->where('reference', $data['reference']))->first();
            if ($existing) {
                if ((int) $existing->family_account_id !== (int) $account->family_account_id
                    || (int) $existing->minutes !== $minutes || (int) $existing->amount_cents !== $amount
                    || $existing->reference !== $data['reference'] || $existing->reason !== $data['reason']) {
                    throw ValidationException::withMessages(['reference' => 'This reference or submission was already used for a different credit. Review the recorded credit.']);
                }

                return $account;
            }
            $lot = CareHourLot::create([
                'care_hour_account_id' => $account->id, 'care_hour_purchase_id' => null,
                'paid_minutes' => $minutes, 'bonus_minutes' => 0, 'total_minutes' => $minutes,
                'processing_fee_cents' => 0, 'status' => 'available', 'issued_at' => now(),
            ]);
            $creditId = DB::table('care_hour_manual_credits')->insertGetId([
                'request_key' => $data['request_key'], 'family_account_id' => $account->family_account_id,
                'care_hour_lot_id' => $lot->id, 'actor_user_id' => $actor->id,
                'minutes' => $minutes, 'amount_cents' => $amount, 'reference' => $data['reference'],
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
                    'amount_cents' => $amount, 'reference' => $data['reference'], 'reason' => $data['reason']],
            ]);

            return $account;
        }, 3);
    }
}
