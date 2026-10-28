<?php

namespace App\Services\CareHours;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareBookingChangeRequest;
use App\Models\CareHourPaymentOperation;
use App\Models\User;
use App\Services\Payments\StripeClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Extra authorization stays isolated until both parties accept the proposed schedule. */
class CareHourScheduleChangeFundingService
{
    public function __construct(private readonly PrepaidBookingFundingService $funding, private readonly CareHourIdentityService $identities) {}

    public function quote(CareBookingChangeRequest $change, User $actor): array
    {
        $booking = $change->booking;
        $record = $this->funding->forBooking($booking) ?? throw new PaymentException('This visit does not use care hours.');
        $this->identities->assertAccess($record->account, $actor);
        if ($change->type !== CareBookingChangeRequest::TYPE_RESCHEDULE || $change->status !== CareBookingChangeRequest::STATUS_PENDING
            || $booking->status !== CareBooking::STATUS_SCHEDULED || ! $change->proposed_start_at || ! $change->proposed_end_at) {
            throw ValidationException::withMessages(['care_hours' => 'This schedule change is no longer available for payment.']);
        }
        $minutes = max(0, (int) $change->proposed_start_at->diffInMinutes($change->proposed_end_at, false));
        $reserved = (int) $record->reserved_minutes;
        $prepaid = min($minutes, $reserved + (int) $record->account->balance()['available_minutes']);
        $needed = max(0, $this->funding->amountForMinutes($booking, $minutes - $prepaid) - $this->funding->protectedCardCents($record));
        $protected = (int) $record->operations()->where('kind', 'shortfall')->where('metadata->schedule_change_id', $change->id)->get()->sum(function ($op) use ($change) {
            if (data_get($op->metadata, 'release_requested')) {
                return 0;
            }
            if ($change->proposed_start_at->toIso8601String() !== data_get($op->metadata, 'proposed_start_at')
                || $change->proposed_end_at->toIso8601String() !== data_get($op->metadata, 'proposed_end_at')) {
                return 0;
            }

            return $op->status === 'captured' ? max(0, $op->captured_cents - $op->refunded_cents)
                : ($op->status === 'authorized' && (! $op->authorization_expires_at || $op->authorization_expires_at->isFuture()) ? $op->amount_cents : 0);
        });

        return ['extra_cents' => max(0, $needed - $protected), 'planned_minutes' => $minutes, 'prepaid_minutes' => $prepaid,
            'ready' => $needed <= $protected, 'protected_extra_cents' => $protected];
    }

    public function createCheckout(CareBookingChangeRequest $change, User $actor, string $successUrl, string $cancelUrl): array
    {
        $quote = $this->quote($change, $actor);
        if ($quote['extra_cents'] <= 0) {
            throw new PaymentException('The proposed time already has enough coverage.');
        }
        $record = $this->funding->forBooking($change->booking);
        $operation = DB::transaction(function () use ($change, $record, $quote) {
            CareBookingChangeRequest::whereKey($change->id)->lockForUpdate()->firstOrFail();
            if ($change->fresh()->status !== CareBookingChangeRequest::STATUS_PENDING) {
                throw new PaymentException('The schedule change has already been resolved.');
            }
            $record->operations()->where('kind', 'shortfall')->where('metadata->schedule_change_id', $change->id)
                ->where('status', 'authorized')->where('authorization_expires_at', '<=', now())->update(['status' => 'expired']);
            $attempt = $record->operations()->where('kind', 'shortfall')->where('metadata->schedule_change_id', $change->id)->whereIn('status', ['failed', 'expired', 'cancelled'])->count();
            $proposal = substr(hash('sha256', $change->proposed_start_at->toIso8601String().'|'.$change->proposed_end_at->toIso8601String()), 0, 16);

            return CareHourPaymentOperation::firstOrCreate(['operation_key' => 'schedule-change:'.$change->id.':'.$proposal.':'.$quote['extra_cents'].':'.$quote['protected_extra_cents'].':'.$attempt], [
                'public_id' => (string) Str::uuid(), 'care_hour_booking_funding_id' => $record->id,
                'kind' => 'shortfall', 'status' => 'pending', 'amount_cents' => $quote['extra_cents'], 'currency' => $record->currency,
                'metadata' => ['schedule_change_id' => $change->id, 'proposed_start_at' => $change->proposed_start_at->toIso8601String(),
                    'proposed_end_at' => $change->proposed_end_at->toIso8601String(), 'funding_revision' => $record->revision],
            ]);
        });
        if ($operation->checkout_url && in_array($operation->status, ['pending', 'checkout'], true)) {
            return ['url' => $operation->checkout_url, 'id' => $operation->stripe_checkout_session_id, 'operation_id' => $operation->public_id];
        }
        if (! in_array($operation->status, ['pending', 'checkout'], true) || $operation->created_at->lt(now()->subHours(23))) {
            throw new PaymentException('Refresh payment coverage before trying another schedule-change payment.');
        }
        $result = app(CareHourPaymentGateway::class)->createShortfallCheckout($operation->public_id, $operation->amount_cents, $operation->currency, $successUrl, $cancelUrl, $actor->email);
        $operation->update(['status' => 'checkout', 'stripe_checkout_session_id' => $result['id'], 'checkout_url' => $result['url']]);

        return $result + ['operation_id' => $operation->public_id];
    }

    public function afterProviderConfirmation(CareHourPaymentOperation $operation): void
    {
        $change = CareBookingChangeRequest::find(data_get($operation->metadata, 'schedule_change_id'));
        $sameProposal = $change && $change->proposed_start_at?->toIso8601String() === data_get($operation->metadata, 'proposed_start_at')
            && $change->proposed_end_at?->toIso8601String() === data_get($operation->metadata, 'proposed_end_at');
        if (! $sameProposal || data_get($operation->metadata, 'release_requested') || ! in_array($change?->status, ['pending', 'accepted'], true)
            || ($change?->status === 'pending' && $operation->funding->booking->status !== 'scheduled')
            || ! in_array($operation->funding->booking->status, ['scheduled', 'in_progress', 'paused', 'completed', 'reviewed'], true)) {
            $this->releaseOperation($operation);

            return;
        }
        if ($change->status === 'accepted') {
            $settlement = \App\Models\CareHourSettlement::where('care_booking_id', $operation->funding->care_booking_id)->first();
            if ($settlement) {
                app(PrepaidBookingSettlementService::class)->reconcileSafely($settlement->id);
            } else {
                $this->funding->prepare($operation->funding->booking);
            }
        }
    }

    public function refreshCoverage(CareBookingChangeRequest $change, User $actor): void
    {
        $this->funding->refreshCoverage($change->booking, $actor);
    }

    public function cancelUnused(CareBookingChangeRequest $change): void
    {
        if (! $this->funding->available()) {
            return;
        }
        foreach (CareHourPaymentOperation::where('kind', 'shortfall')->where('metadata->schedule_change_id', $change->id)->get() as $operation) {
            $operation->update(['metadata' => array_merge($operation->metadata ?? [], ['release_requested' => true])]);
            if (DB::transactionLevel() > 0) {
                DB::afterCommit(fn () => $this->releaseOperation($operation->fresh()));
            } else {
                $this->releaseOperation($operation);
            }
        }
    }

    public function releaseOperation(CareHourPaymentOperation $operation): void
    {
        if (in_array($operation->status, ['cancelled', 'refunded', 'failed'], true)) {
            return;
        }
        $operation->update(['metadata' => array_merge($operation->metadata ?? [], ['release_requested' => true])]);
        try {
            if ($operation->status === 'captured') {
                app(PrepaidBookingSettlementService::class)->refundUnused($operation);
            } elseif ($operation->stripe_payment_intent_id) {
                app(StripeClient::class)->cancelPaymentIntent($operation->stripe_payment_intent_id);
                $operation->update(['status' => 'cancelled']);
            }
        } catch (\Throwable $error) {
            $operation->update(['last_error' => $error->getMessage()]);
            report($error);
        }
    }
}
