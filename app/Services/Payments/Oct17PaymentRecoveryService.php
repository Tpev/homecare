<?php

namespace App\Services\Payments;

use App\Models\CareBooking;
use App\Models\CareBookingEvent;
use App\Models\CareBookingPayment;
use App\Models\FamilyAccount;
use App\Models\User;
use App\Support\MarketplacePricing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/** The reviewed October 9/17 incident, not a general booking reassignment endpoint. */
class Oct17PaymentRecoveryService
{
    public const SOURCE_BOOKING_ID = 244;

    public const BOOKING_ID = 311;

    public const SOURCE_PAYMENT_ID = 126;

    public const PAYMENT_ID = 175;

    public const AMOUNT = 36012;

    public const MINUTES = 697;

    public const OLD_INTENT = 'pi_3UKiSN6ewFB76RHF0KKlFOqg';

    public const INTENT = 'pi_3URf2A6ewFB76RHF0YL59lIw';

    public const CUSTOMER_ID = 'cus_UzbLWUlGKpPlYP';

    private const RECEIPT = 'oct17_payment_recovery';

    private const SOURCE_MARKER = 'oct17_visit_reassignment';

    private const TIMES = [
        'started_at' => '2026-10-17 19:30:56',
        'completed_at' => '2026-10-18 07:08:49',
        'timesheet_submitted_at' => '2026-10-18 07:08:49',
    ];

    private const TRACKING = [
        'started_at', 'completed_at', 'timesheet_submitted_at', 'worked_minutes',
        'check_in_lat', 'check_in_lng', 'check_in_accuracy_meters', 'check_in_source', 'check_in_note',
        'check_out_lat', 'check_out_lng', 'check_out_accuracy_meters', 'check_out_source', 'check_out_note',
        'paused_at', 'total_paused_seconds',
    ];

    public function __construct(
        private readonly StripeClient $stripe,
        private readonly BookingPaymentV2Service $payments,
        private readonly MarketplacePricing $pricing,
    ) {}

    public function preview(): array
    {
        [$source, $target, $oldPayment, $payment] = $this->context();
        $receipt = $this->receipt($payment);

        return [
            'source_booking_id' => self::SOURCE_BOOKING_ID, 'source_request_id' => 238,
            'source_payment_id' => self::SOURCE_PAYMENT_ID, 'source_booking_status' => $source->status,
            'booking_id' => self::BOOKING_ID, 'request_id' => 317, 'payment_id' => self::PAYMENT_ID,
            'booking_status' => $target->status, 'recorded_times' => self::TIMES,
            'timestamp_note' => 'Original raw database values, America/New_York; no rounding or conversion.',
            'worked_minutes' => self::MINUTES, 'family_care_cents' => 34850,
            'family_processing_fee_cents' => 1162, 'target_charge_cents' => self::AMOUNT,
            'caregiver_gross_cents' => 31365, 'currency' => 'usd',
            'payment_status' => $payment->status, 'existing_payment_intent_id' => self::INTENT,
            'expected_state' => $receipt['expected_state'] ?? $this->fingerprint([$source, $target, $oldPayment, $payment]),
            'recovery' => $this->publicReceipt($receipt),
            'approval_basis' => 'auto-approval',
            'auto_approval_eligible' => $source->timesheet_submitted_at->lte(now()->subHours(24)),
            'caregiver_acceptance_note' => 'Booking 311 has no recorded caregiver acceptance. Preserve that fact; record administrator-reviewed reassignment referencing booking 244 and its accepted identical rates.',
            'administrators' => User::where('role', 'admin')->get(['id', 'name'])->toArray(),
            'effects_on_apply' => 'Cancel booking 244 and accept cancellation requests 6/7; retain its original times and events as evidence. Copy unchanged visit tracking to booking 311 with linked admin audit entries. Capture exactly USD 360.12 from its existing authorization; normal fee finalization may transfer caregiver earnings. No new authorization or support messages.',
            'provider_check' => 'Preview does not contact Stripe. Apply rechecks both intents. --provider-verified attests that no separate/manual charge, refund, dispute, or transfer was found; --assignment-confirmed records that October 9 was cancelled and these 697 minutes belong to October 17.',
        ];
    }

    public function apply(User $admin, string $expectedState, string $requestId, string $reason,
        bool $providerVerified = false, bool $assignmentConfirmed = false): array
    {
        $this->ensure($admin->isAdministrator(), 'A valid administrator is required.');
        $this->ensure(Str::isUuid($requestId), 'Use one stable UUID for --request-id, including retries.');
        $this->ensure(strlen(trim($reason)) >= 15, 'Record the cancellation, reassignment and payment review in --reason.');
        $this->ensure($providerVerified, 'Review Stripe for any separate/manual payment and pass --provider-verified.');
        $this->ensure($assignmentConfirmed, 'Confirm October 9 was cancelled and the recorded 697 minutes belong to October 17 with --assignment-confirmed.');
        $lock = Cache::lock('oct17-payment-recovery:244:311', 180);
        $this->ensure($lock->get(), 'Another recovery is running. Wait and retry the same request.');
        try {
            return $this->execute($admin, $expectedState, $requestId, trim($reason));
        } finally {
            $lock->release();
        }
    }

    private function execute(User $admin, string $expectedState, string $requestId, string $reason): array
    {
        $receipt = DB::transaction(function () use ($admin, $expectedState, $requestId, $reason): array {
            [$source, $target, $oldPayment, $payment] = $this->context(true);
            $receipt = $this->receipt($payment);
            $inputs = ['request_id' => $requestId, 'expected_state' => $expectedState,
                'admin_user_id' => $admin->id, 'reason' => $reason];
            if ($receipt !== []) {
                foreach ($inputs as $key => $value) {
                    $this->ensure(($receipt[$key] ?? null) === $value,
                        'Resume the existing recovery with exactly its original request ID, preview, administrator and reason.');
                }

                return $receipt;
            }
            $this->ensure(hash_equals($this->fingerprint([$source, $target, $oldPayment, $payment]), $expectedState),
                'The preview is stale. Run a fresh read-only preview before applying.');
            $this->ensure($source->timesheet_submitted_at->lte(now()->subHours(24)),
                'The existing 24-hour automatic approval window has not elapsed.');
            $receipt = array_merge($inputs, [
                'state' => 'reserved', 'reserved_at' => now()->toIso8601String(),
                'source_booking_id' => self::SOURCE_BOOKING_ID, 'booking_id' => self::BOOKING_ID,
                'target_booking_id' => self::BOOKING_ID, 'target_charge_cents' => self::AMOUNT,
                'worked_minutes' => self::MINUTES, 'payment_intent_id' => self::INTENT,
                'assignment_confirmed' => true, 'approval_basis' => 'auto-approval',
                'approved_by_user_id' => null,
                'caregiver_acceptance_basis' => 'Administrator-reviewed visit reassignment; original acceptance remains on booking 244. No new acceptance is asserted.',
                'source_caregiver_terms_accepted_at' => $source->getRawOriginal('caregiver_terms_accepted_at'),
                'source_tracking' => $this->tracking($source),
                'original_source_events' => $source->events()->reorder('id')->get()->map->getRawOriginal()->all(),
                'original_target_events' => $target->events()->reorder('id')->get()->map->getRawOriginal()->all(),
                'original_cancellations' => $source->changeRequests()->reorder('id')->get()->map->getRawOriginal()->all(),
                'source_status_before' => $source->status, 'target_status_before' => $target->status,
            ]);
            $this->storeReceipt($payment, $receipt);
            $metadata = (array) $oldPayment->metadata;
            $metadata[self::SOURCE_MARKER] = [
                'request_id' => $requestId, 'source_booking_id' => self::SOURCE_BOOKING_ID,
                'target_booking_id' => self::BOOKING_ID, 'target_payment_id' => self::PAYMENT_ID,
                'nonbillable' => true, 'recorded_at' => now()->toIso8601String(), 'reason' => $reason,
            ];
            $oldPayment->forceFill(['metadata' => $metadata])->save();
            $this->event($source->id, $admin->id, 'oct17_recovery_reserved', $receipt);
            $this->event($target->id, $admin->id, 'oct17_recovery_reserved', $receipt);

            return $receipt;
        });

        if ($receipt['state'] === 'complete') {
            return $this->result();
        }

        try {
            $this->verifyProvider($receipt);
            $this->reconcile($admin);
            [$source, $target, $oldPayment, $payment] = $this->context();
            // Uses the existing ledger, actual processing fees and source-charge transfer keys.
            $this->payments->capture($target, $payment);
        } catch (Throwable $exception) {
            $this->updateReceipt(['last_error' => $exception->getMessage()], true);
            throw $exception;
        }

        DB::transaction(function () use ($admin): void {
            [$source, $target, $oldPayment, $payment] = $this->context(true);
            $this->ensure((int) $payment->amount_captured_cents === self::AMOUNT
                && (int) $payment->overage_pending_cents === 0,
                'Capture has not reconciled to exactly USD 360.12. Review Stripe and retry this same request.');
            $receipt = $this->receipt($payment);
            // This is the existing automatic-approval policy, never a claimed family click.
            $target->forceFill(['family_confirmed_at' => $target->family_confirmed_at ?: now(),
                'family_confirmed_by_user_id' => null])->save();
            $receipt = array_merge($receipt, ['state' => 'complete',
                'completed_at' => now()->toIso8601String(), 'last_error' => null]);
            $this->storeReceipt($payment, $receipt);
            $this->event($target->id, $admin->id, 'oct17_timesheet_recovery_auto_approved', $receipt);
            $this->event($target->id, $admin->id, 'oct17_payment_recovery_completed', $receipt);
        });

        return $this->result();
    }

    private function verifyProvider(array $receipt): void
    {
        $original = $this->stripe->retrievePaymentIntent(self::OLD_INTENT);
        $this->assertProfile($original, self::OLD_INTENT);
        $this->ensure(($original['status'] ?? null) === 'canceled'
            && (int) ($original['amount_received'] ?? -1) === 0,
            'The old Stripe intent is not cancelled with zero received. Stop and reconcile Stripe.');
        $live = $this->stripe->retrievePaymentIntent(self::INTENT);
        $this->assertProfile($live, self::INTENT);
        if (($live['status'] ?? null) === 'succeeded') {
            $this->ensure(! empty($receipt['reconciled_at'])
                && (int) ($live['amount_received'] ?? -1) === self::AMOUNT,
                'An unexpected capture needs separate reconciliation. Do not charge again.');

            return;
        }
        $this->ensure(($live['status'] ?? null) === 'requires_capture'
            && (int) ($live['amount'] ?? 0) === 42780
            && (int) ($live['amount_received'] ?? -1) === 0
            && (int) ($live['amount_capturable'] ?? 0) >= self::AMOUNT,
            'The existing October 17 authorization is not capturable for USD 360.12. No new authorization will be created.');
        $this->ensure(empty($live['authorization_expires_at'])
            || Carbon::parse($live['authorization_expires_at'])->isFuture(),
            'The existing authorization expired. Stop and review family billing.');
    }

    private function reconcile(User $admin): void
    {
        DB::transaction(function () use ($admin): void {
            [$source, $target, $oldPayment, $payment] = $this->context(true);
            $receipt = $this->receipt($payment);
            if (! empty($receipt['reconciled_at'])) {
                return;
            }
            $this->ensure($source->timesheet_submitted_at->lte(now()->subHours(24)),
                'The original timesheet is not eligible for the 24-hour approval policy.');
            $source->forceFill(['status' => CareBooking::STATUS_CANCELLED, 'cancelled_at' => now(),
                'cancelled_by_user_id' => $admin->id,
                'cancellation_reason' => 'Reviewed October 9 cancellation; October 17 tracking retained as audit evidence and reassigned to booking 311. Recovery '.$receipt['request_id']])->save();
            foreach ($source->changeRequests()->reorder('id')->get() as $change) {
                $change->forceFill(['status' => 'accepted', 'resolved_at' => now(),
                    'resolved_by_user_id' => $admin->id,
                    'resolution_note' => 'October 9 was cancelled; October 17 visit belongs to booking 311. Recovery '.$receipt['request_id']])->save();
            }
            // Original event IDs and actors stay on 244; the new event records who reassigned them.
            $target->forceFill(array_merge($this->tracking($source), ['status' => CareBooking::STATUS_COMPLETED]))->save();
            $quote = $this->pricing->quoteForCurrentBooking($target, self::MINUTES);
            $payment->forceFill(array_merge($this->payments->paymentSnapshotAttributes($target), [
                'worked_minutes' => self::MINUTES, 'family_care_amount_cents' => 34850,
                'family_processing_fee_cents' => 1162, 'caregiver_gross_amount_cents' => 31365,
                'overage_pending_cents' => 0,
            ]))->save();
            $this->ensure((int) $quote['total_charge_cents'] === self::AMOUNT, 'The reviewed price changed.');
            $receipt = array_merge($receipt, ['state' => 'reconciled',
                'reconciled_at' => now()->toIso8601String(),
                'provider_verified_at' => now()->toIso8601String(), 'last_error' => null]);
            $this->storeReceipt($payment, $receipt);
            $this->event($source->id, $admin->id, 'oct17_visit_reassigned_out', $receipt);
            $this->event($target->id, $admin->id, 'oct17_visit_reassigned_in', $receipt);
        });
    }

    /** Lock both bookings in ID order, then both payments, matching normal booking-before-payment locks. */
    private function context(bool $lock = false): array
    {
        $bookings = CareBooking::whereIn('id', [self::SOURCE_BOOKING_ID, self::BOOKING_ID])
            ->orderBy('id')->when($lock, fn ($q) => $q->lockForUpdate())->get()->keyBy('id');
        $payments = CareBookingPayment::whereIn('id', [self::SOURCE_PAYMENT_ID, self::PAYMENT_ID])
            ->orderBy('id')->when($lock, fn ($q) => $q->lockForUpdate())->get()->keyBy('id');
        $source = $bookings->get(self::SOURCE_BOOKING_ID);
        $target = $bookings->get(self::BOOKING_ID);
        $oldPayment = $payments->get(self::SOURCE_PAYMENT_ID);
        $payment = $payments->get(self::PAYMENT_ID);
        $this->ensure($source && $target && $oldPayment && $payment, 'Both reviewed bookings and payments must exist.');
        $receipt = $this->receipt($payment);
        $reconciled = ! empty($receipt['reconciled_at']);
        foreach ([[$source, $oldPayment, 238, self::OLD_INTENT, 44640], [$target, $payment, 317, self::INTENT, 42780]] as [$booking, $pay, $request, $intent, $amount]) {
            $this->ensure((int) $booking->care_request_id === $request
                && (int) $booking->family_account_id === 3 && (int) $booking->family_user_id === 335
                && (int) $booking->caregiver_user_id === 349 && ! $booking->care_plan_id
                && (int) $pay->care_booking_id === $booking->id && (int) $pay->family_account_id === 3
                && (int) $pay->family_user_id === 335 && (int) $pay->caregiver_user_id === 349
                && $booking->payment()->count() === 1, 'Booking or payment identity changed.');
            $this->ensure($booking->dispute_status === 'none' && ! $booking->dispute_opened_at
                && ! $booking->no_show_flag && ! $booking->late_cancel_flag && ! $booking->paused_at
                && (int) $booking->total_paused_seconds === 0
                && ! $booking->timeCorrections()->exists() && ! $booking->corrections()->exists()
                && ! $pay->hasPrepaidVisitHold()
                && ! data_get($pay->metadata, 'authorization_in_progress_at')
                && ! data_get($pay->metadata, 'pending_authorization'),
                'A dispute, pause, correction, prepaid hold or in-flight authorization needs separate review.');
            $this->ensure($booking->pricing_version === '2026-08-v2' && ! $booking->pricing_agreement_id
                && (int) $booking->family_care_rate_cents === 3000 && (int) $booking->family_processing_fee_rate_cents === 100
                && (int) $booking->caregiver_gross_rate_cents === 2700
                && $booking->caregiver_fee_policy === 'successful_charge_balance_transaction'
                && $pay->pricing_version === $booking->pricing_version && ! $this->pricing->hasUnappliedAgreement($booking),
                'The accepted saved rates changed. Do not use this incident command.');
            $this->ensure($pay->stripe_payment_intent_id === $intent && $pay->currency === 'usd'
                && $pay->stripe_customer_id === self::CUSTOMER_ID && $pay->stripe_payment_method_id
                && (int) $pay->amount_authorized_cents === $amount
                && (int) $pay->amount_refunded_cents === 0 && ! $pay->stripe_last_refund_id
                && ! $pay->stripe_last_transfer_reversal_id && ! $pay->stripe_overage_payment_intent_id
                && ! $pay->stripe_overage_charge_id && (int) $pay->amount_overage_cents === 0
                && (int) $pay->overage_pending_cents === 0
                && ! $pay->operations()->whereIn('type', ['refund', 'transfer_reversal', 'dispute'])->exists(),
                'Billing identity, authorization or refund/overage evidence changed.');
            $this->ensure($pay->attempts()->count() === 1
                && ! $pay->attempts()->where('stripe_payment_intent_id', '!=', $intent)->exists(),
                'An additional or missing authorization attempt needs review.');
        }
        $account = FamilyAccount::find(3);
        $this->ensure($account && (int) $account->owner_user_id === 335 && $account->status === FamilyAccount::STATUS_ACTIVE
            && (! $account->stripe_customer_id || $account->stripe_customer_id === self::CUSTOMER_ID), 'Family billing account changed.');
        foreach (self::TIMES as $field => $time) {
            $this->ensure($source->getRawOriginal($field) === $time, 'The original recorded timestamps changed.');
        }
        $this->ensure((int) $source->worked_minutes === self::MINUTES
            && $source->check_in_source === 'manual' && $source->check_out_source === 'manual'
            && $source->getRawOriginal('caregiver_terms_accepted_at') === '2026-09-28 13:54:00'
            && $source->getRawOriginal('family_terms_accepted_at') === '2026-09-28 13:35:14'
            && $target->getRawOriginal('family_terms_accepted_at') === '2026-10-17 17:20:54'
            && ! $target->caregiver_terms_accepted_at && ! $source->family_confirmed_at
            && ! $source->family_confirmed_by_user_id && ! $target->family_confirmed_by_user_id,
            'Recorded minutes, tracking or acceptance/approval evidence changed.');
        foreach ([[$source, '2026-10-09 19:00:00', '2026-10-10 07:00:00', 720],
            [$target, '2026-10-17 19:30:00', '2026-10-18 07:00:00', 690]] as [$booking, $start, $end, $minutes]) {
            $this->ensure($booking->getRawOriginal('scheduled_start_at') === $start
                && $booking->getRawOriginal('scheduled_end_at') === $end && (int) $booking->expected_minutes === $minutes,
                'The original schedules changed.');
        }
        foreach ([[825, 'checked_in_by_caregiver', self::TIMES['started_at']], [826, 'checked_out_by_caregiver', self::TIMES['completed_at']],
            [646, 'agreement_accepted_by_caregiver', '2026-09-28 13:54:00']] as [$id, $type, $time]) {
            $this->ensure($source->events()->whereKey($id)->where('event_type', $type)->where('actor_user_id', 349)
                ->where('actor_role', 'caregiver')->where('happened_at', $time)->exists(), 'Original caregiver event evidence is missing or changed.');
        }
        $this->ensure($target->events()->whereKey(824)->where('event_type', 'booking_hired')->where('actor_user_id', 378)->exists()
            && ! $target->events()->whereIn('event_type', ['checked_in_by_caregiver', 'checked_out_by_caregiver', 'agreement_accepted_by_caregiver'])->exists(),
            'The target booking already has different caregiver activity.');
        $recoveryEvents = ['oct17_recovery_reserved', 'oct17_visit_reassigned_out', 'oct17_visit_reassigned_in',
            'oct17_timesheet_recovery_auto_approved', 'oct17_payment_recovery_completed'];
        $this->ensure($source->events()->whereNotIn('event_type', $recoveryEvents)->reorder('id')->pluck('id')->all() === [645, 646, 765, 766, 825, 826]
            && $target->events()->whereNotIn('event_type', $recoveryEvents)->reorder('id')->pluck('id')->all() === [824],
            'Additional or missing visit events require review.');
        $changes = $source->changeRequests()->reorder('id')->when($lock, fn ($q) => $q->lockForUpdate())->get();
        $this->ensure($changes->pluck('id')->all() === [6, 7] && ! $target->changeRequests()->exists(),
            'Cancellation or change request set changed.');
        foreach ($changes as $change) {
            $this->ensure($change->type === 'cancel' && (int) $change->requester_user_id === 378
                && $change->status === ($reconciled ? 'accepted' : 'pending')
                && ($reconciled ? ((int) $change->resolved_by_user_id === (int) $receipt['admin_user_id']
                    && str_contains((string) $change->resolution_note, $receipt['request_id']) && $change->resolved_at)
                    : (! $change->resolved_at && ! $change->resolved_by_user_id)), 'Cancellation review state changed.');
        }
        $this->assertNoMoney($source, $oldPayment);
        $this->ensure($oldPayment->status === 'cancelled'
            && ! $oldPayment->attempts()->where('is_active', true)->exists(), 'The cancelled October 9 payment changed.');
        if ($receipt !== []) {
            $this->ensure(data_get($oldPayment->metadata, self::SOURCE_MARKER.'.request_id') === $receipt['request_id']
                && data_get($oldPayment->metadata, self::SOURCE_MARKER.'.target_booking_id') === self::BOOKING_ID
                && ($receipt['target_charge_cents'] ?? null) === self::AMOUNT
                && ($receipt['payment_intent_id'] ?? null) === self::INTENT
                && $this->sameRecord((array) ($receipt['source_tracking'] ?? []), $this->tracking($source)), 'Recovery audit receipt changed.');
            foreach ($receipt['original_cancellations'] as $original) {
                $current = $changes->firstWhere('id', $original['id'])->getRawOriginal();
                $allowed = $reconciled ? array_flip(['status', 'resolved_at', 'resolved_by_user_id', 'resolution_note', 'updated_at']) : [];
                $this->ensure($this->sameRecord(array_diff_key($current, $allowed), array_diff_key($original, $allowed)),
                    'Original cancellation evidence changed after reservation.');
            }
            foreach ([[$source, 'original_source_events'], [$target, 'original_target_events']] as [$booking, $key]) {
                foreach ($receipt[$key] as $event) {
                    $this->ensure($this->sameRecord((array) $booking->events()->find($event['id'])?->getRawOriginal(), $event),
                        'An original visit event changed after reservation.');
                }
            }
        } else {
            $this->ensure(! data_get($oldPayment->metadata, self::SOURCE_MARKER), 'A different source reassignment already exists.');
        }
        if (! $reconciled) {
            $this->assertNoMoney($target, $payment);
            $this->ensure($source->status === 'completed' && ! $source->cancelled_at
                && $target->status === 'scheduled' && ! $target->cancelled_at && ! $target->family_confirmed_at
                && $payment->status === 'authorized' && $payment->attempts()->where('is_active', true)->where('status', 'authorized')->exists(),
                'The original booking/payment state changed.');
            foreach (self::TRACKING as $field) {
                $this->ensure($field === 'total_paused_seconds' || $target->getRawOriginal($field) === null,
                    'The target already has recorded work. Stop before overwriting it.');
            }
        } else {
            $this->ensure($source->status === 'cancelled' && $source->cancelled_at
                && (int) $source->cancelled_by_user_id === (int) $receipt['admin_user_id']
                && $target->status === 'completed' && ! $target->cancelled_at
                && $this->sameRecord($this->tracking($target), $receipt['source_tracking'])
                && (int) $payment->worked_minutes === self::MINUTES
                && in_array((int) $payment->amount_captured_cents, [0, self::AMOUNT], true)
                && ! $payment->operations()->where('type', 'charge')->where(fn ($q) => $q
                    ->whereNull('stripe_parent_object_id')->orWhere('stripe_parent_object_id', '!=', self::INTENT)
                    ->orWhere('amount_cents', '!=', self::AMOUNT))->exists(), 'The reconciled visit or captured amount changed.');
            if ($receipt['state'] === 'complete') {
                $this->ensure((int) $payment->amount_captured_cents === self::AMOUNT && $target->family_confirmed_at,
                    'Completed recovery no longer matches its capture and approval.');
            }
        }
        $quote = $this->pricing->quoteForCurrentBooking($target, self::MINUTES);
        $this->ensure((int) $quote['total_charge_cents'] === self::AMOUNT
            && (int) $quote['caregiver_gross_amount_cents'] === 31365, 'The USD 360.12 quote no longer matches.');

        return [$source, $target, $oldPayment, $payment];
    }

    private function assertNoMoney(CareBooking $booking, CareBookingPayment $payment): void
    {
        $this->ensure((int) $payment->amount_captured_cents === 0 && ! $payment->captured_at
            && ! $payment->stripe_primary_charge_id && ! $payment->stripe_transfer_id
            && ! $payment->transferred_at && ! $booking->payoutItem()->exists()
            && ! $payment->operations()->where('type', '!=', 'authorization')->exists(),
            'Existing money movement requires separate reconciliation.');
    }

    private function assertProfile(array $intent, string $id): void
    {
        $this->ensure(($intent['id'] ?? $intent['payment_intent_id'] ?? null) === $id
            && ($intent['customer_id'] ?? null) === self::CUSTOMER_ID
            && ($intent['currency'] ?? null) === 'usd' && ($intent['livemode'] ?? null) === true,
            'Stripe intent identity, customer, currency or live mode differs from the reviewed payment.');
    }

    private function tracking(CareBooking $booking): array
    {
        return array_intersect_key($booking->getRawOriginal(), array_flip(self::TRACKING));
    }

    /** MySQL JSON storage may reorder object keys; values and types must still match. */
    private function sameRecord(array $left, array $right): bool
    {
        ksort($left);
        ksort($right);

        return $left === $right;
    }

    private function fingerprint(array $models): string
    {
        [$source, $target, $oldPayment, $payment] = $models;
        $state = ['records' => array_map(fn ($model) => $model->getRawOriginal(), $models)];
        foreach ([$source, $target] as $booking) {
            foreach (['events', 'changeRequests', 'timeCorrections', 'corrections'] as $relation) {
                $state['booking_'.$booking->id][$relation] = $booking->$relation()->reorder('id')->get()->map->getRawOriginal()->all();
            }
        }
        foreach ([$oldPayment, $payment] as $pay) {
            foreach (['attempts', 'operations'] as $relation) {
                $state['payment_'.$pay->id][$relation] = $pay->$relation()->reorder('id')->get()->map->getRawOriginal()->all();
            }
        }

        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }

    private function receipt(CareBookingPayment $payment): array
    {
        return (array) data_get($payment->metadata, self::RECEIPT, []);
    }

    private function storeReceipt(CareBookingPayment $payment, array $receipt): void
    {
        $metadata = (array) $payment->metadata;
        $metadata[self::RECEIPT] = $receipt;
        $payment->forceFill(['metadata' => $metadata])->save();
    }

    private function updateReceipt(array $changes, bool $failed = false): void
    {
        DB::transaction(function () use ($changes, $failed): void {
            CareBooking::whereIn('id', [self::SOURCE_BOOKING_ID, self::BOOKING_ID])->orderBy('id')->lockForUpdate()->get();
            $payment = CareBookingPayment::whereKey(self::PAYMENT_ID)->lockForUpdate()->firstOrFail();
            $receipt = $this->receipt($payment);
            if (($receipt['state'] ?? null) !== 'complete') {
                if ($failed && ! empty($receipt['reconciled_at'])) {
                    $changes['state'] = 'capture_unknown';
                }
                $this->storeReceipt($payment, array_merge($receipt, $changes));
            }
        });
    }

    private function event(int $bookingId, int $adminId, string $type, array $receipt): void
    {
        CareBookingEvent::firstOrCreate(['care_booking_id' => $bookingId, 'event_type' => $type], [
            'actor_user_id' => $adminId, 'actor_role' => 'admin', 'happened_at' => now(),
            'payload' => array_merge($this->publicReceipt($receipt), [
                'source_tracking_event_ids' => [825, 826], 'source_agreement_event_id' => 646,
                'recorded_times' => self::TIMES,
                'caregiver_acceptance_basis' => $receipt['caregiver_acceptance_basis'],
            ]),
        ]);
    }

    private function publicReceipt(array $receipt): array
    {
        return array_intersect_key($receipt, array_flip(['state', 'request_id', 'admin_user_id', 'reason',
            'source_booking_id', 'booking_id', 'target_charge_cents', 'worked_minutes', 'payment_intent_id',
            'assignment_confirmed', 'approval_basis', 'approved_by_user_id', 'reserved_at', 'reconciled_at',
            'completed_at', 'last_error']));
    }

    private function result(): array
    {
        $payment = CareBookingPayment::findOrFail(self::PAYMENT_ID);

        return ['recovery' => $this->publicReceipt($this->receipt($payment)),
            'source_booking' => CareBooking::findOrFail(self::SOURCE_BOOKING_ID)->only(['id', 'status', 'started_at', 'completed_at', 'timesheet_submitted_at', 'worked_minutes']),
            'booking' => CareBooking::findOrFail(self::BOOKING_ID)->only(['id', 'status', 'started_at', 'completed_at', 'timesheet_submitted_at', 'worked_minutes', 'family_confirmed_at', 'family_confirmed_by_user_id', 'caregiver_terms_accepted_at']),
            'payment' => $payment->only(['id', 'status', 'amount_captured_cents', 'caregiver_gross_amount_cents',
                'stripe_processing_fee_cents', 'caregiver_amount_cents', 'fee_finalization_status',
                'stripe_payment_intent_id', 'stripe_primary_charge_id', 'stripe_transfer_id'])];
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['recovery' => $message]);
        }
    }
}
