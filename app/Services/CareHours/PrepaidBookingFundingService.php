<?php

namespace App\Services\CareHours;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareHourAccount;
use App\Models\CareHourBookingFunding;
use App\Models\CareHourPaymentOperation;
use App\Models\CareHourPlanPreference;
use App\Models\CareHourSettlement;
use App\Models\CarePlan;
use App\Models\FamilyAccount;
use App\Models\User;
use App\Services\Booking\BookingTrustService;
use App\Services\Payments\StripeClient;
use App\Support\MarketplacePricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** The absence of a committed sidecar always means the existing card path. */
class PrepaidBookingFundingService
{
    public function __construct(
        private readonly CareHourLedgerService $ledger,
        private readonly CareHourIdentityService $identities,
        private readonly MarketplacePricing $pricing,
    ) {}

    public function available(): bool
    {
        return Schema::hasTable('care_hour_booking_fundings');
    }

    public function forBooking(CareBooking $booking): ?CareHourBookingFunding
    {
        return $this->available()
            ? CareHourBookingFunding::where('care_booking_id', $booking->id)->whereNotNull('committed_at')->first()
            : null;
    }

    public function isPrepaid(CareBooking $booking): bool
    {
        return $this->forBooking($booking) !== null;
    }

    public function selectForBooking(CareBooking $booking, CareHourAccount $account, User $actor): CareHourBookingFunding
    {
        $this->identities->assertAccess($account, $actor);

        return DB::transaction(function () use ($booking, $account, $actor) {
            $locked = CareBooking::lockForUpdate()->findOrFail($booking->id);
            $existing = $this->forBooking($locked);
            if ($existing) {
                if ((int) $existing->care_hour_account_id !== (int) $account->id) {
                    throw ValidationException::withMessages(['careHourAccountId' => 'This visit already has committed prepaid funding.']);
                }

                return $existing;
            }
            if (! app(CareHourAccess::class)->canUseAccount($account, $actor)) {
                throw ValidationException::withMessages(['careHourAccountId' => 'Care hours are not available for this account.']);
            }
            $this->assertCanSelect($locked, $account);
            $funding = CareHourBookingFunding::create([
                'care_booking_id' => $locked->id, 'care_hour_account_id' => $account->id,
                'selected_by_user_id' => $actor->id, 'committed_at' => now(), 'status' => 'selected',
                'planned_minutes' => $this->plannedMinutes($locked), 'currency' => 'usd',
            ]);
            // Snapshot only this explicitly selected new visit; never rewrite a card booking.
            if (! $locked->pricing_snapshotted_at) {
                $locked->forceFill($this->pricing->currentSnapshotAttributes($locked))->saveQuietly();
            }
            app(BookingTrustService::class)->recordEvent($locked, $actor->id, 'family', 'care_hours_selected', ['care_hour_account_id' => $account->id]);

            return $funding;
        }, 3);
    }

    public function selectForPlan(CarePlan $plan, CareHourAccount $account, User $actor): CareHourPlanPreference
    {
        $this->identities->assertAccess($account, $actor);
        if (! app(CareHourAccess::class)->canUseAccount($account, $actor)) {
            throw ValidationException::withMessages(['careHourAccountId' => 'Care hours are not available for this account.']);
        }
        if ((int) $plan->family_account_id !== (int) $account->family_account_id || $plan->care_arrangement_id
            || ! $account->active || $account->currency !== 'usd') {
            throw ValidationException::withMessages(['careHourAccountId' => 'Care hours are not available for this care arrangement.']);
        }

        return DB::transaction(function () use ($plan, $account, $actor) {
            CarePlan::lockForUpdate()->findOrFail($plan->id);
            $preference = CareHourPlanPreference::updateOrCreate(['care_plan_id' => $plan->id], [
                'care_hour_account_id' => $account->id, 'selected_by_user_id' => $actor->id,
                'enabled' => true, 'selected_at' => now(),
            ]);
            $this->clearUnpreparedPlanSelections($plan, $account->id);
            foreach ($plan->generatedBookings()->where('status', CareBooking::STATUS_SCHEDULED)
                ->where('scheduled_start_at', '>', now())->whereDoesntHave('payment')->orderBy('id')->get() as $booking) {
                $this->inheritPlanSelection($booking);
            }

            return $preference;
        }, 3);
    }

    public function disablePlan(CarePlan $plan, User $actor): void
    {
        $preference = CareHourPlanPreference::where('care_plan_id', $plan->id)->firstOrFail();
        $this->identities->assertAccess($preference->account, $actor);
        DB::transaction(function () use ($plan, $preference) {
            CarePlan::lockForUpdate()->findOrFail($plan->id);
            $preference->update(['enabled' => false]);
            $this->clearUnpreparedPlanSelections($plan);
        });
        // Visits with a reservation or any payment preparation retain their mode and protection.
    }

    private function clearUnpreparedPlanSelections(CarePlan $plan, ?int $keepAccount = null): void
    {
        $rows = CareHourBookingFunding::whereHas('booking', fn ($q) => $q->where('care_plan_id', $plan->id)
            ->where('status', CareBooking::STATUS_SCHEDULED)->where('scheduled_start_at', '>', now())->whereDoesntHave('payment'))
            ->where('status', 'selected')->where('reserved_minutes', 0)->whereDoesntHave('operations')
            ->when($keepAccount, fn ($q) => $q->where('care_hour_account_id', '!=', $keepAccount))->lockForUpdate()->get();
        foreach ($rows as $row) {
            $row->delete();
        }
    }

    /** Called inside occurrence creation, before the booking is visible to payment workers. */
    public function inheritPlanSelection(CareBooking $booking): void
    {
        if (! $booking->care_plan_id || $booking->status !== CareBooking::STATUS_SCHEDULED || $booking->started_at || ! $this->available() || $this->isPrepaid($booking)) {
            return;
        }
        $preference = CareHourPlanPreference::where('care_plan_id', $booking->care_plan_id)->where('enabled', true)->first();
        if (! $preference) {
            return;
        }
        DB::transaction(function () use ($booking, $preference) {
            $locked = CareBooking::lockForUpdate()->findOrFail($booking->id);
            if ($locked->payment()->exists() || $this->isPrepaid($locked)) {
                return;
            }
            $actor = $preference->account->familyAccount->owner;
            $this->selectForBooking($locked, $preference->account, $actor);
        }, 3);
    }

    public function prepare(CareBooking $booking): CareHourBookingFunding
    {
        $result = DB::transaction(function () use ($booking) {
            $booking = CareBooking::lockForUpdate()->findOrFail($booking->id);
            $funding = $this->forBooking($booking) ?? throw new PaymentException('Care-hours funding has not been selected for this visit.');
            if (! in_array($booking->status, [CareBooking::STATUS_SCHEDULED, CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED], true)) {
                return $funding;
            }
            $planned = $this->plannedMinutes($booking);
            // A funded split remains committed even when another gift arrives later.
            $reserved = $funding->status === 'ready' && $planned === (int) $funding->planned_minutes
                ? (int) $funding->reserved_minutes : $this->ledger->reserve($funding->account, $booking, $planned);
            $shortfall = max(0, $planned - $reserved);
            $amount = $this->amountForMinutes($booking, $shortfall);
            $covered = $this->protectedCardCents($funding);
            $held = $this->ledger->allocationsFor($booking)->contains(fn ($allocation) => $allocation->lot->status !== 'available');
            $changed = $planned !== (int) $funding->planned_minutes || $reserved !== (int) $funding->reserved_minutes || $amount !== (int) $funding->shortfall_cents;
            $funding->update([
                'planned_minutes' => $planned, 'reserved_minutes' => $reserved,
                'shortfall_minutes' => $shortfall, 'shortfall_cents' => $amount,
                'revision' => (int) $funding->revision + ($changed ? 1 : 0),
                'status' => ! $held && $covered >= $amount ? 'ready' : 'payment_attention',
                'last_error' => $held ? 'Some care hours are on hold. Contact support.' : ($covered < $amount ? 'Cover the remaining visit time before check-in.' : null),
            ]);

            return $funding->fresh();
        }, 3);
        if ($result->status === 'payment_attention') {
            $notify = fn () => $this->notifyPaymentAttention($result);
            if (DB::transactionLevel() > 0) {
                DB::afterCommit($notify);
            } else {
                $notify();
            }
        }

        return $result;
    }

    public function notifyPaymentAttention(CareHourBookingFunding $funding, ?CareHourSettlement $settlement = null): void
    {
        $booking = $funding->booking;
        $recipient = $booking->family;
        if (! $recipient) {
            return;
        }
        app(\App\Services\Notifications\MarketplaceNotificationService::class)->notify(
            recipients: $recipient, eventKey: 'care_hours_payment_attention', title: 'Visit coverage needs attention',
            body: $settlement ? 'The approved care includes additional time. Review the remaining visit payment.' : 'Review the care-hours and payment coverage for your upcoming visit.',
            url: route('family.requests.show', $booking->care_request_id), subject: $booking,
            payload: ['care_booking_id' => $booking->id, 'care_hour_account_id' => $funding->care_hour_account_id],
            dedupeKey: 'care-hours-attention:'.$funding->id.':'.($settlement ? 'settlement:'.$settlement->revision : 'funding:'.$funding->revision),
        );
    }

    public function protectScheduleChange(CareBooking $booking): void
    {
        $before = $this->forBooking($booking);
        if (! $before) {
            return;
        }
        $increasing = $this->plannedMinutes($booking) > (int) $before->planned_minutes;
        $after = $this->prepare($booking);
        if ($increasing && $after->status !== 'ready') {
            throw ValidationException::withMessages(['changeRequest' => 'Cover the extra time with care hours or a one-time payment before accepting. The original visit is still protected.']);
        }
    }

    public function readiness(CareBooking $booking): array
    {
        $funding = $this->forBooking($booking);
        if (! $funding) {
            return ['ready' => false, 'reason' => 'Care-hours funding is not selected.'];
        }
        $allocations = $this->ledger->allocationsFor($booking);
        $minutes = $allocations->where('status', 'reserved')->sum('minutes');
        $held = $allocations->where('status', 'reserved')->contains(fn ($allocation) => $allocation->lot->status !== 'available');
        $shortfall = $this->amountForMinutes($booking, max(0, $this->plannedMinutes($booking) - $minutes));
        $ready = $funding->status !== 'released' && ! $held && ! $this->hasDisputedShortfall($funding) && $this->protectedCardCents($funding) >= $shortfall;

        return ['ready' => $ready, 'reason' => $ready ? null : 'This visit needs care-hours or payment confirmation before check-in.'];
    }

    public function createShortfallCheckout(CareBooking $booking, User $actor, string $successUrl, string $cancelUrl): array
    {
        $funding = $this->forBooking($booking) ?? throw new PaymentException('Choose care hours first.');
        $this->identities->assertAccess($funding->account, $actor);
        if ($this->hasDisputedShortfall($funding)) {
            throw new PaymentException('This visit payment is under review. Contact support before making another payment.');
        }
        if ($booking->status === CareBooking::STATUS_CANCELLED) {
            throw new PaymentException('This visit was cancelled.');
        }
        $settlement = CareHourSettlement::where('care_booking_id', $booking->id)->first();
        if ($settlement) {
            $amount = (int) $settlement->amount_due_cents;
            $key = 'settlement:'.$settlement->id.':'.$settlement->revision;
        } else {
            $funding = $this->prepare($booking);
            $amount = max(0, (int) $funding->shortfall_cents - $this->protectedCardCents($funding));
            $key = 'booking:'.$funding->id.':'.$funding->revision;
        }
        if ($amount <= 0) {
            throw new PaymentException('This visit is already covered.');
        }
        $operation = DB::transaction(function () use ($funding, $settlement, $amount, $key) {
            CareHourBookingFunding::lockForUpdate()->findOrFail($funding->id);
            $expired = $funding->operations()->where('kind', 'shortfall')->where('status', 'authorized')->where('authorization_expires_at', '<=', now())->get();
            foreach ($expired as $old) {
                $old->update(['status' => 'expired']);
            }
            $attempt = $funding->operations()->where('kind', 'shortfall')->whereIn('status', ['failed', 'expired', 'cancelled'])->count();

            return CareHourPaymentOperation::firstOrCreate([
                'operation_key' => 'shortfall:'.$key.':'.$attempt,
            ], [
                'public_id' => (string) Str::uuid(), 'care_hour_booking_funding_id' => $funding->id,
                'care_hour_settlement_id' => $settlement?->id,
                'kind' => 'shortfall', 'status' => 'pending', 'amount_cents' => $amount, 'currency' => $funding->currency,
                'metadata' => ['funding_revision' => $funding->revision],
            ]);
        });
        if ($operation->checkout_url && in_array($operation->status, ['pending', 'checkout'], true)) {
            return ['id' => $operation->stripe_checkout_session_id, 'url' => $operation->checkout_url, 'operation_id' => $operation->public_id];
        }
        if (! in_array($operation->status, ['pending', 'checkout'], true)) {
            throw new PaymentException('Refresh this visit before starting another payment.');
        }
        if ($operation->created_at->lt(now()->subHours(23))) {
            throw new PaymentException('This payment needs support review before retrying.');
        }
        $result = app(CareHourPaymentGateway::class)->createShortfallCheckout($operation->public_id, $operation->amount_cents, $operation->currency, $successUrl, $cancelUrl, $actor->email);
        $operation->update(['stripe_checkout_session_id' => $result['id'], 'checkout_url' => $result['url'], 'status' => 'checkout']);

        return $result + ['operation_id' => $operation->public_id];
    }

    public function ownsOperation(string $id): bool
    {
        return $this->available() && CareHourPaymentOperation::where('public_id', $id)->where('kind', 'shortfall')->exists();
    }

    public function refreshCoverage(CareBooking $booking, User $actor): void
    {
        $funding = $this->forBooking($booking) ?? throw new PaymentException('This visit does not use care hours.');
        $this->identities->assertAccess($funding->account, $actor);
        foreach ($funding->operations()->where('kind', 'shortfall')->whereIn('status', ['pending', 'checkout'])->whereNotNull('stripe_checkout_session_id')->get() as $operation) {
            $gateway = app(CareHourPaymentGateway::class);
            $session = $gateway->retrieveCheckout($operation->stripe_checkout_session_id);
            if (($session['id'] ?? '') !== $operation->stripe_checkout_session_id || data_get($session, 'metadata.care_hour_operation_id') !== $operation->public_id) {
                throw new PaymentException('Checkout does not match this visit payment.');
            }
            if (($session['status'] ?? '') === 'expired') {
                $this->failShortfall($operation->public_id);

                continue;
            }
            $intent = $session['payment_intent'] ?? null;
            if (is_array($intent)) {
                $intent = $intent['id'] ?? null;
            }
            if ($intent) {
                $result = $gateway->financials($intent);
                if (in_array($result['status'] ?? '', ['requires_capture', 'succeeded'], true)) {
                    $this->completeShortfall($operation->public_id, $result);
                }
            }
        }
        if (! CareHourSettlement::where('care_booking_id', $booking->id)->exists()) {
            $this->prepare($booking);
        }
    }

    /** Called only with an authoritative, verified provider result by the care-hours webhook handler. */
    public function completeShortfall(string $id, array $result): void
    {
        $operation = CareHourPaymentOperation::where('public_id', $id)->where('kind', 'shortfall')->firstOrFail();
        $operation->assertProviderScope(isset($result['livemode']) ? (bool) $result['livemode'] : null, $result['connected_account'] ?? null);
        $capture = $operation->stripe_payment_intent_id ? CareHourPaymentOperation::where('kind', 'capture')
            ->where('care_hour_booking_funding_id', $operation->care_hour_booking_funding_id)
            ->where('stripe_payment_intent_id', $operation->stripe_payment_intent_id)->first() : null;
        $expectedCaptured = (int) $operation->captured_cents > 0 ? (int) $operation->captured_cents : (int) ($capture?->amount_cents ?? $operation->amount_cents);
        if (! in_array($result['status'] ?? '', ['requires_capture', 'succeeded'], true)
            || strtolower($result['currency'] ?? '') !== strtolower($operation->currency)
            || (int) ($result['amount_cents'] ?? 0) !== (int) $operation->amount_cents
            || (int) ($result['status'] === 'succeeded' ? ($result['amount_received'] ?? $result['amount_cents'] ?? 0) : ($result['authorized_cents'] ?? 0)) !== ($result['status'] === 'succeeded' ? $expectedCaptured : (int) $operation->amount_cents)
            || ($operation->stripe_payment_intent_id && $operation->stripe_payment_intent_id !== ($result['payment_intent_id'] ?? null))) {
            throw new PaymentException('The care-hours shortfall payment does not match this visit.');
        }
        DB::transaction(function () use ($operation, $result) {
            $locked = CareHourPaymentOperation::lockForUpdate()->findOrFail($operation->id);
            if (in_array($locked->status, ['captured', 'refunded'], true)) {
                return;
            }
            if ($locked->status === 'cancelled' && $result['status'] !== 'succeeded') {
                return;
            }
            $locked->update([
                'status' => $result['status'] === 'succeeded' ? 'captured' : 'authorized',
                'stripe_payment_intent_id' => $result['payment_intent_id'], 'stripe_charge_id' => $result['charge_id'] ?? null,
                'authorization_expires_at' => $result['authorization_expires_at'] ?? null,
                'captured_cents' => $result['status'] === 'succeeded' ? ($result['amount_received'] ?? $result['amount_cents'] ?? $locked->amount_cents) : 0,
                'processing_fee_cents' => $result['processing_fee_cents'] ?? null, 'last_error' => null,
            ]);
        });
        $booking = $operation->funding->booking;
        if ($booking->status === CareBooking::STATUS_CANCELLED || $operation->funding->status === 'released') {
            $this->release($booking);

            return;
        }
        if (data_get($operation->metadata, 'schedule_change_id')) {
            app(CareHourScheduleChangeFundingService::class)->afterProviderConfirmation($operation->fresh());

            return;
        }
        if (CareHourSettlement::where('care_booking_id', $booking->id)->exists()) {
            app(PrepaidBookingSettlementService::class)->settle($booking);

            return;
        }
        $this->prepare($booking);
    }

    public function failShortfall(string $id): void
    {
        $operation = CareHourPaymentOperation::where('public_id', $id)->where('kind', 'shortfall')->firstOrFail();
        DB::transaction(function () use ($operation) {
            // Use the same booking -> family -> funding order as cancellation.
            // Holding funding while waiting for the family lock can deadlock a
            // cancellation that has already returned this wallet's allocations.
            $booking = CareBooking::lockForUpdate()->findOrFail($operation->funding->care_booking_id);
            FamilyAccount::whereKey($operation->funding->account->family_account_id)->lockForUpdate()->firstOrFail();
            $funding = CareHourBookingFunding::lockForUpdate()->findOrFail($operation->care_hour_booking_funding_id);
            $operation = CareHourPaymentOperation::lockForUpdate()->findOrFail($operation->id);
            if (in_array($operation->status, ['authorized', 'captured', 'cancelled', 'refunded'], true)) {
                return;
            }
            $operation->update(['status' => 'failed']);
            if (data_get($operation->metadata, 'schedule_change_id')) {
                return;
            }
            if ((int) data_get($operation->metadata, 'funding_revision') !== (int) $funding->revision || in_array($funding->status, ['ready', 'released'], true)) {
                return;
            }
            $this->ledger->release($booking);
            $funding->update(['status' => 'payment_attention', 'reserved_minutes' => 0, 'revision' => $funding->revision + 1, 'last_error' => 'Payment did not complete. Review care coverage and try again.']);
        }, 3);
    }

    /** An expired Checkout cannot revoke a hold; an authoritative cancelled intent can. */
    public function cancelShortfall(string $id, array $result): void
    {
        $operation = CareHourPaymentOperation::where('public_id', $id)->where('kind', 'shortfall')->firstOrFail();
        $operation->assertProviderScope(isset($result['livemode']) ? (bool) $result['livemode'] : null, $result['connected_account'] ?? null);
        if (($result['status'] ?? '') !== 'canceled' || empty($result['payment_intent_id'])
            || data_get($result, 'metadata.care_hour_domain') !== 'shortfall'
            || data_get($result, 'metadata.care_hour_operation_id') !== $operation->public_id
            || (int) ($result['amount_cents'] ?? -1) !== (int) $operation->amount_cents
            || strtolower($result['currency'] ?? '') !== strtolower($operation->currency)) {
            throw new PaymentException('Cancelled payment does not match this visit.');
        }
        DB::transaction(function () use ($operation, $result) {
            $booking = CareBooking::lockForUpdate()->findOrFail($operation->funding->care_booking_id);
            $funding = CareHourBookingFunding::lockForUpdate()->findOrFail($operation->care_hour_booking_funding_id);
            $current = CareHourPaymentOperation::lockForUpdate()->findOrFail($operation->id);
            if ($current->stripe_payment_intent_id && $current->stripe_payment_intent_id !== $result['payment_intent_id']) {
                throw new PaymentException('Cancelled payment does not match the recorded authorization.');
            }
            // A concurrent capture or a replay cannot undo money already recorded as collected.
            if (in_array($current->status, ['captured', 'refunded', 'cancelled'], true) || (int) $current->captured_cents > 0) {
                return;
            }
            $current->update(['status' => 'cancelled', 'stripe_payment_intent_id' => $result['payment_intent_id'],
                'authorization_expires_at' => now(), 'last_error' => 'The card authorization was cancelled.']);
            if ($funding->status === 'released' || data_get($current->metadata, 'schedule_change_id')
                || CareHourSettlement::where('care_booking_id', $booking->id)->exists()) {
                return;
            }
            // Retain the hours already reserved for care, but remove the cancelled card coverage.
            if (! $this->readiness($booking)['ready']) {
                $funding->update(['status' => 'payment_attention', 'revision' => $funding->revision + 1,
                    'last_error' => 'The card authorization was cancelled. Confirm payment before check-in.']);
            }
        }, 3);
    }

    public function release(CareBooking $booking): void
    {
        $funding = $this->forBooking($booking);
        if (! $funding) {
            return;
        }
        DB::transaction(function () use ($booking, $funding) {
            $lockedBooking = CareBooking::lockForUpdate()->findOrFail($booking->id);
            FamilyAccount::whereKey($funding->account->family_account_id)->lockForUpdate()->firstOrFail();
            $lockedFunding = CareHourBookingFunding::lockForUpdate()->findOrFail($funding->id);
            $this->ledger->release($lockedBooking);
            $lockedFunding->update(['status' => 'released', 'reserved_minutes' => 0, 'last_error' => null]);
        }, 3);
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => $this->reconcileReleased($funding->id));
        } else {
            $this->reconcileReleased($funding->id);
        }
    }

    public function reconcileReleased(int $id): void
    {
        $funding = CareHourBookingFunding::findOrFail($id);
        foreach ($funding->operations()->where('kind', 'shortfall')->whereNotIn('status', ['cancelled', 'refunded', 'failed'])->get() as $operation) {
            try {
                if ($operation->status === 'captured') {
                    app(PrepaidBookingSettlementService::class)->refundUnused($operation);
                } elseif ($operation->stripe_payment_intent_id) {
                    app(StripeClient::class)->cancelPaymentIntent($operation->stripe_payment_intent_id);
                    $operation->update(['status' => 'cancelled']);
                }
                // Pending Checkout stays discoverable: a late successful callback cancels/refunds it.
            } catch (\Throwable $error) {
                $funding->update(['last_error' => $error->getMessage()]);
                report($error);
            }
        }
    }

    public function summary(CareBooking $booking): ?array
    {
        $funding = $this->forBooking($booking);
        if (! $funding) {
            return null;
        }
        $settlement = CareHourSettlement::where('care_booking_id', $booking->id)->first();

        return [
            'id' => $funding->id, 'account_id' => $funding->care_hour_account_id, 'status' => $funding->status, 'recipient' => 'Family care balance',
            'planned_minutes' => (int) $funding->planned_minutes, 'reserved_minutes' => (int) $funding->reserved_minutes,
            'shortfall_minutes' => (int) $funding->shortfall_minutes, 'shortfall_cents' => (int) $funding->shortfall_cents,
            'ready' => $this->readiness($booking)['ready'], 'last_error' => $funding->last_error,
            'payment_under_review' => $this->hasDisputedShortfall($funding),
            'approval_preview' => $this->approvalPreview($booking, (int) ($booking->worked_minutes ?? $this->plannedMinutes($booking))),
            'available_minutes' => $funding->account->balance()['available_minutes'],
            'settlement' => $settlement ? ['status' => $settlement->status, 'prepaid_minutes' => (int) $settlement->prepaid_minutes,
                'card_minutes' => (int) $settlement->card_minutes, 'amount_due_cents' => (int) $settlement->amount_due_cents,
                'card_paid_cents' => (int) $funding->operations()->where('kind', 'shortfall')->where('status', 'captured')->get()
                    ->filter(fn ($operation) => $this->operationIsUsable($operation))->sum(fn ($operation) => max(0, $operation->captured_cents - $operation->refunded_cents))] : null,
        ];
    }

    public function approvalPreview(CareBooking $booking, int $minutes): array
    {
        $record = $this->forBooking($booking);
        $current = CareHourSettlement::where('care_booking_id', $booking->id)->first();
        $original = (int) ($current?->prepaid_minutes ?? $record->reserved_minutes);
        $extra = max(0, $minutes - max((int) $record->planned_minutes, (int) ($current?->worked_minutes ?? 0)));
        $prepaid = min(max(0, $minutes), $original + min($extra, $this->ledger->balance($record->account)['available_minutes']));

        return ['billable_minutes' => max(0, $minutes), 'prepaid_minutes' => $prepaid,
            'card_cents' => $this->amountForMinutes($booking, max(0, $minutes - $prepaid)), 'restore_minutes' => max(0, $original - $prepaid)];
    }

    public function plannedMinutes(CareBooking $booking): int
    {
        return $booking->scheduled_start_at && $booking->scheduled_end_at
            ? max(0, (int) $booking->scheduled_start_at->diffInMinutes($booking->scheduled_end_at, false))
            : max(0, (int) $booking->expected_minutes);
    }

    public function amountForMinutes(CareBooking $booking, int $minutes): int
    {
        if ($minutes <= 0) {
            return 0;
        }

        return $this->pricing->prorateHourlyCents((int) ($booking->family_care_rate_cents ?? $this->pricing->familyCareHourlyCents()), $minutes)
            + $this->pricing->prorateHourlyCents((int) ($booking->family_processing_fee_rate_cents ?? $this->pricing->familyProcessingFeeHourlyCents()), $minutes);
    }

    public function protectedCardCents(CareHourBookingFunding $funding): int
    {
        return (int) $funding->operations()->where('kind', 'shortfall')->get()->sum(function ($operation) {
            if (! $this->operationIsUsable($operation)) {
                return 0;
            }
            if ($operation->status === 'captured') {
                return max(0, $operation->captured_cents - $operation->refunded_cents);
            }

            return $operation->status === 'authorized' && (! $operation->authorization_expires_at || $operation->authorization_expires_at->isFuture()) ? $operation->amount_cents : 0;
        });
    }

    public function operationIsUsable(CareHourPaymentOperation $operation): bool
    {
        if ($this->operationHasDispute($operation)) {
            return false;
        }
        $changeId = data_get($operation->metadata, 'schedule_change_id');
        if (! $changeId) {
            return true;
        }
        if (data_get($operation->metadata, 'release_requested')) {
            return false;
        }
        $change = \App\Models\CareBookingChangeRequest::whereKey($changeId)->where('status', \App\Models\CareBookingChangeRequest::STATUS_ACCEPTED)->first();

        return $change && $change->proposed_start_at?->toIso8601String() === data_get($operation->metadata, 'proposed_start_at')
            && $change->proposed_end_at?->toIso8601String() === data_get($operation->metadata, 'proposed_end_at');
    }

    public function operationHasDispute(CareHourPaymentOperation $operation): bool
    {
        return (bool) data_get($operation->metadata, 'dispute_recovery_required', false)
            || collect(data_get($operation->metadata, 'disputes', []))->contains(fn ($status) => ! in_array($status, ['won', 'warning_closed'], true));
    }

    public function hasDisputedShortfall(CareHourBookingFunding $funding): bool
    {
        return $funding->operations()->where('kind', 'shortfall')->get()->contains(fn ($operation) => $this->operationHasDispute($operation));
    }

    private function assertCanSelect(CareBooking $booking, CareHourAccount $account): void
    {
        if ($booking->status !== CareBooking::STATUS_SCHEDULED || $booking->started_at || $booking->payment()->exists()) {
            throw ValidationException::withMessages(['careHourAccountId' => 'Only a new, unprepared visit can use care hours. Existing card payments stay unchanged.']);
        }
        if ((int) $booking->family_account_id !== (int) $account->family_account_id
            || ! $account->active || $account->currency !== 'usd'
            || ! in_array((int) $this->identities->forBooking($booking)?->id, $account->familySourceIds(), true)) {
            throw ValidationException::withMessages(['careHourAccountId' => 'Choose your family care balance.']);
        }
        if ($booking->pricing_agreement_id || $this->pricing->hasUnappliedAgreement($booking)
            || $booking->carePlan?->care_arrangement_id || \App\Models\ContinuousCoverageShift::where('care_booking_id', $booking->id)->exists()
            || $booking->completedExtraVisitRequest()->exists()) {
            throw ValidationException::withMessages(['careHourAccountId' => 'This care arrangement uses its existing payment method.']);
        }
    }
}
