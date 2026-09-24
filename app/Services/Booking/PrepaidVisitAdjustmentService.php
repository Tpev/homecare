<?php

namespace App\Services\Booking;

use App\Exceptions\Payments\PaymentActionRequiredException;
use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareBookingCorrection;
use App\Models\CareBookingEvent;
use App\Models\CareBookingPayment;
use App\Models\CareBookingTimeCorrection;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\BookingPaymentV2Service;
use App\Services\Payments\StripeClient;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Explicit, positive adjustment of a recovered visit. Hold, ticket and messages remain untouched. */
class PrepaidVisitAdjustmentService
{
    public function __construct(
        private readonly PrepaidVisitRecoveryService $recovery,
        private readonly BookingPaymentService $payments,
        private readonly BookingPaymentV2Service $ledger,
        private readonly StripeClient $stripe,
        private readonly FamilyAccountContext $families,
    ) {}

    public function preview(int $bookingId, int $ticketId, int $timeCorrectionId, User $admin): array
    {
        $this->assertAdmin($admin);

        return DB::transaction(function () use ($bookingId, $ticketId, $timeCorrectionId, $admin): array {
            $base = $this->recovery->previewAdjustment($bookingId, $ticketId, $admin, $timeCorrectionId);
            [$booking, $payment, $ticket, $approved] = $this->context($bookingId, $ticketId, $timeCorrectionId);
            $this->validateApproval($booking, $payment, $ticket, $approved);
            $this->require(! data_get($payment->metadata, 'prepaid_visit_recovery.adjustment_correction_id'), 'This hold already has an adjustment. Review its existing receipt.');
            $this->require(filled($payment->stripe_customer_id) && filled($payment->stripe_payment_method_id), 'The original saved customer and card are required.');
            $quote = $this->payments->quoteForWorkedMinutes($booking, $approved->proposed_worked_minutes);
            foreach (['total_charge_cents' => 'target_charge_cents', 'caregiver_amount_cents' => 'caregiver_amount_cents',
                'family_processing_fee_cents' => 'family_processing_fee_cents', 'hourly_rate' => 'hourly_rate',
                'platform_fee_percent' => 'platform_fee_percent'] as $current => $saved) {
                $this->require(isset($approved->financial_preview[$saved])
                    && (float) $quote[$current] === (float) $approved->financial_preview[$saved],
                    'The current price differs from the family-approved correction. Keep the hold for review.');
            }
            $delta = (int) $quote['total_charge_cents'] - (int) $payment->amount_captured_cents;
            $this->require($delta > 0, 'This workflow supports an additional approved charge only.');

            return array_merge($base, [
                'action' => CareBookingCorrection::ACTION_ADJUST_PREPAID,
                'time_correction_id' => $approved->id,
                'version' => $approved->version,
                'approved_by_user_id' => $approved->approved_by_user_id,
                'approved_at' => $approved->approved_at->toIso8601String(),
                'approved_start_eastern' => $approved->proposed_started_at->copy()->setTimezone('America/New_York')->format('Y-m-d H:i:s T'),
                'approved_end_eastern' => $approved->proposed_completed_at->copy()->setTimezone('America/New_York')->format('Y-m-d H:i:s T'),
                'break_minutes' => $approved->proposed_break_minutes,
                'worked_minutes' => $approved->proposed_worked_minutes,
                'target_charge_cents' => (int) $quote['total_charge_cents'],
                'payment_delta_cents' => $delta,
                'caregiver_gross_cents' => (int) $quote['caregiver_gross_amount_cents'],
                'caregiver_delta_before_additional_fee_cents' => (int) $quote['caregiver_amount_cents'] - (int) $payment->caregiver_amount_cents,
                'caregiver_net_cents' => null,
                'caregiver_net_note' => 'Calculated after Stripe finalizes the additional charge fee; transfers remain held.',
                'stripe_writes' => false,
                'apply_stripe_effect' => 'One additional charge of exactly '.$delta.' cents. No refund or transfer.',
                'payout_effect' => 'Transfers remain held. A separate release preview and approval are required.',
                'expected_state' => $this->fingerprint($this->state($booking, $payment, $ticket, $approved)),
            ]);
        });
    }

    public function apply(int $bookingId, int $ticketId, int $timeCorrectionId, User $admin,
        string $requestId, string $expectedState, int $expectedAdditionalCents, string $reason, bool $providerVerified): CareBookingCorrection
    {
        $this->assertAdmin($admin);
        $this->require(Str::isUuid($requestId), 'A stable UUID request ID is required.');
        $this->require($providerVerified, 'Verify the original charge, refunds, disputes and transfers in Stripe first.');
        $this->require($expectedAdditionalCents > 0 && mb_strlen(trim($reason)) >= 10 && mb_strlen($reason) <= 2000,
            'Confirm the exact additional cents and record a reason between 10 and 2,000 characters.');

        // Commit the reservation BEFORE contacting Stripe. A crash cannot erase the fact that a charge may have been attempted.
        [$receipt, $dispatch] = DB::transaction(function () use ($bookingId, $ticketId, $timeCorrectionId, $admin, $requestId, $expectedState, $expectedAdditionalCents, $reason): array {
            [$booking, $payment, $ticket, $approved] = $this->context($bookingId, $ticketId, $timeCorrectionId);
            $existing = CareBookingCorrection::query()->where('client_request_id', $requestId)->first();
            if ($existing) {
                $this->assertReceipt($existing, $bookingId, $ticketId, $timeCorrectionId, $admin);
                $this->require((int) $existing->payment_delta_cents === $expectedAdditionalCents
                    && hash_equals((string) data_get($existing->preview, 'expected_state'), $expectedState), 'This request ID belongs to a different preview.');

                return [$existing, false];
            }
            $preview = $this->preview($bookingId, $ticketId, $timeCorrectionId, $admin);
            $this->require(hash_equals($preview['expected_state'], $expectedState), 'Records changed after preview. Inspect again; nothing was changed.');
            $this->require($preview['payment_delta_cents'] === $expectedAdditionalCents, 'The additional charge does not match the explicitly approved cents.');
            $receipt = CareBookingCorrection::query()->create([
                'client_request_id' => $requestId, 'care_booking_id' => $bookingId, 'support_ticket_id' => $ticketId,
                'actor_admin_user_id' => $admin->id, 'source' => 'admin_prepaid_adjustment',
                'time_correction_request_id' => $approved->id, 'requester_user_id' => $approved->requester_user_id,
                'approved_by_user_id' => $approved->approved_by_user_id,
                'action' => CareBookingCorrection::ACTION_ADJUST_PREPAID, 'status' => CareBookingCorrection::STATUS_PROCESSING,
                'attempt_count' => 1, 'previous_charge_cents' => $payment->amount_captured_cents,
                'target_charge_cents' => $preview['target_charge_cents'], 'payment_delta_cents' => $expectedAdditionalCents,
                // As with ordinary corrections, this immutable input uses the fees known at preview.
                // The actual final net and delta are recorded in provider_payload after fee reconciliation.
                'caregiver_delta_cents' => $preview['caregiver_delta_before_additional_fee_cents'],
                'family_approval_confirmed_at' => $approved->approved_at, 'reason' => trim($reason),
                'before_snapshot' => $this->state($booking, $payment, $ticket, $approved),
                'requested_changes' => [
                    'started_at' => $approved->proposed_started_at->toIso8601String(),
                    'completed_at' => $approved->proposed_completed_at->toIso8601String(),
                    'break_minutes' => $approved->proposed_break_minutes, 'worked_minutes' => $approved->proposed_worked_minutes,
                    'provider_verified' => true,
                ],
                'preview' => $preview,
                'provider_payload' => ['dispatch_reserved_at' => now()->toIso8601String(), 'idempotency_key' => 'prepaid-adjustment:'.$requestId],
                'internal_note_client_id' => (string) Str::uuid(), 'public_reply_client_id' => (string) Str::uuid(),
            ]);
            $metadata = (array) $payment->metadata;
            $metadata['prepaid_visit_recovery']['adjustment_correction_id'] = $receipt->id;
            $payment->forceFill(['metadata' => $metadata])->save();
            $this->checkpoint($receipt, $booking, $payment, $ticket, $approved);

            return [$receipt->fresh(), true];
        });

        // Same UUID never dispatches twice, even after timeout, a failed local commit, or Stripe's idempotency retention window.
        return $dispatch ? $this->execute($receipt, $admin, false) : $receipt;
    }

    /** Explicit reconciliation can only READ the already known Stripe intent, never create or confirm another charge. */
    public function reconcile(int $bookingId, int $ticketId, int $timeCorrectionId, User $admin, string $requestId, bool $providerVerified): CareBookingCorrection
    {
        $this->assertAdmin($admin);
        $this->require($providerVerified, 'Verify the charge outcome, refunds, disputes and transfers in Stripe before reconciliation.');
        $receipt = CareBookingCorrection::query()->where('client_request_id', $requestId)->firstOrFail();
        $this->assertReceipt($receipt, $bookingId, $ticketId, $timeCorrectionId, $admin);
        if ($receipt->succeeded()) {
            return $receipt;
        }
        $this->require(filled(data_get($receipt->provider_payload, 'charge.id')), 'Charge outcome is uncertain and no intent ID is stored. Keep the hold and review Stripe using this receipt UUID; do not charge again.');

        return $this->execute($receipt, $admin, true);
    }

    private function execute(CareBookingCorrection $receipt, User $admin, bool $reconcile): CareBookingCorrection
    {
        return DB::transaction(function () use ($receipt, $admin, $reconcile): CareBookingCorrection {
            [$booking, $payment, $ticket, $approved] = $this->context($receipt->care_booking_id, $receipt->support_ticket_id, $receipt->time_correction_request_id);
            $receipt = CareBookingCorrection::query()->lockForUpdate()->findOrFail($receipt->id);
            if ($receipt->succeeded()) {
                return $receipt;
            }
            $this->require($payment->hasPrepaidVisitHold()
                && (int) data_get($payment->metadata, 'prepaid_visit_recovery.adjustment_correction_id') === $receipt->id,
                'The reserved payment hold changed. Review before proceeding.');
            $this->require(hash_equals((string) data_get($receipt->provider_payload, 'checkpoint'),
                $this->fingerprint($this->state($booking, $payment, $ticket, $approved, $receipt->id))),
                'Records changed after the adjustment was reserved. Keep the hold for review.');
            $this->validateApproval($booking, $payment, $ticket, $approved);

            try {
                $receipt->forceFill(['status' => CareBookingCorrection::STATUS_PROCESSING, 'last_error' => null])->save();
                $result = $reconcile
                    ? $this->stripe->retrievePaymentIntentFinancials((string) data_get($receipt->provider_payload, 'charge.id'), (int) $receipt->payment_delta_cents)
                    : $this->stripe->createAndConfirmCharge($booking, (string) $payment->stripe_customer_id,
                        (string) $payment->stripe_payment_method_id, (int) $receipt->payment_delta_cents, (string) $payment->currency,
                        ['care_booking_payment_id' => (string) $payment->id, 'care_booking_correction_id' => (string) $receipt->id,
                            'time_correction_id' => (string) $approved->id, 'prepaid_adjustment_request_id' => $receipt->client_request_id],
                        (string) data_get($receipt->provider_payload, 'idempotency_key'));
                $knownId = data_get($receipt->provider_payload, 'charge.id');
                $this->require(! $knownId || $knownId === ($result['id'] ?? null), 'Stripe returned a different intent. Keep the hold for review.');
                $provider = (array) $receipt->provider_payload;
                $provider['charge'] = Arr::only($result, ['id', 'status', 'amount_received', 'latest_charge_id', 'balance_transaction_id',
                    'processing_fee_cents', 'fee_finalized', 'processing_fee_components']);
                $receipt->forceFill(['provider_payload' => $provider])->save();
                $this->require(($result['status'] ?? '') === 'succeeded'
                    && (int) ($result['amount_received'] ?? 0) === (int) $receipt->payment_delta_cents,
                    'The additional charge is not confirmed for the approved amount. Keep the hold for review.');

                $booking->forceFill([
                    'started_at' => $approved->proposed_started_at, 'completed_at' => $approved->proposed_completed_at,
                    'worked_minutes' => $approved->proposed_worked_minutes, 'total_paused_seconds' => $approved->proposed_break_minutes * 60,
                    'family_confirmed_at' => $approved->approved_at, 'family_confirmed_by_user_id' => $approved->approved_by_user_id,
                ])->save();
                // Preserve original GPS, check-in/out evidence and submission time; the immutable receipt records the correction.
                $receipt->forceFill(['booking_applied_at' => $receipt->booking_applied_at ?: now()])->save();
                $payment = $this->ledger->recordHeldVisitAdjustment($payment, $receipt, $result);
                $this->require((int) $payment->amount_captured_cents === (int) $receipt->target_charge_cents, 'Captured totals do not match the approved bill. Keep the hold.');
                $provider = (array) $receipt->fresh()->provider_payload;
                $provider['earnings'] = [
                    'fees_finalized' => $payment->fee_finalization_status === 'finalized',
                    'caregiver_gross_cents' => (int) $payment->caregiver_gross_amount_cents,
                    'caregiver_net_cents' => $payment->fee_finalization_status === 'finalized' ? (int) $payment->caregiver_amount_cents : null,
                    'caregiver_delta_cents' => $payment->fee_finalization_status === 'finalized'
                        ? (int) $payment->caregiver_amount_cents - (int) data_get($receipt->before_snapshot, 'financial.payment.caregiver_amount_cents') : null,
                ];
                $receipt->forceFill(['provider_payload' => $provider])->save();
                if ($payment->fee_finalization_status !== 'finalized') {
                    $receipt->forceFill(['last_error' => 'The additional charge succeeded. Processing fees are pending; reconcile this receipt without another charge.'])->save();
                } else {
                    $approved->forceFill(['status' => CareBookingTimeCorrection::STATUS_APPLIED, 'finalized_at' => now(), 'last_error' => null])->save();
                    $metadata = (array) $payment->metadata;
                    $metadata['prepaid_visit_recovery']['adjusted_financial_fingerprint'] = $this->fingerprint($this->recovery->financialState($payment));
                    $payment->forceFill(['metadata' => $metadata])->save();
                    $receipt->forceFill(['status' => CareBookingCorrection::STATUS_SUCCEEDED,
                        'applied_at' => now(), 'last_error' => null,
                        'after_snapshot' => $this->state($booking->fresh(), $payment->fresh(), $ticket, $approved->fresh(), $receipt->id),
                    ])->save();
                    CareBookingEvent::query()->create([
                        'care_booking_id' => $booking->id, 'actor_user_id' => $admin->id, 'actor_role' => 'admin',
                        'event_type' => 'prepaid_visit_adjusted_on_hold', 'happened_at' => now(),
                        'payload' => ['care_booking_correction_id' => $receipt->id, 'time_correction_id' => $approved->id,
                            'support_ticket_id' => $ticket->id, 'payment_delta_cents' => $receipt->payment_delta_cents],
                    ]);
                }
            } catch (Throwable $exception) {
                // Keep known provider evidence and the reservation committed even if local application fails.
                $provider = (array) $receipt->fresh()->provider_payload;
                if ($exception instanceof PaymentActionRequiredException && $exception->paymentIntentId) {
                    $provider['charge'] = ['id' => $exception->paymentIntentId];
                }
                $receipt->forceFill([
                    'status' => $exception instanceof PaymentActionRequiredException ? CareBookingCorrection::STATUS_REQUIRES_ACTION : CareBookingCorrection::STATUS_FAILED,
                    'provider_payload' => $provider,
                    'last_error' => $exception instanceof PaymentException ? $exception->userMessage : 'The adjustment needs review. Do not submit another charge; keep this receipt and hold.',
                ])->save();
                report($exception);
            }
            $this->checkpoint($receipt, $booking->fresh(), $payment->fresh(), $ticket->fresh(), $approved->fresh());

            return $receipt->fresh();
        });
    }

    private function validateApproval(CareBooking $booking, CareBookingPayment $payment, SupportTicket $ticket, CareBookingTimeCorrection $approved): void
    {
        $this->require($approved->status === CareBookingTimeCorrection::STATUS_APPROVED_ADMIN_REQUIRED
            && (int) $approved->care_booking_id === $booking->id && (int) $approved->support_ticket_id === $ticket->id
            && (int) $ticket->care_booking_id === $booking->id && (int) $approved->requester_user_id === (int) $booking->caregiver_user_id
            && (int) $approved->family_user_id === (int) $booking->family_user_id
            && (int) $approved->family_account_id === (int) $booking->family_account_id
            && (int) $booking->timeCorrections()->orderByDesc('version')->value('id') === $approved->id,
            'Use the latest family-approved correction and its exact support ticket.');
        $approver = $approved->approvedBy;
        $this->require($approver?->role === 'family' && $this->families->canAccessRecord($approver, $booking)
            && $this->families->canAccessRecord($approver, $approved), 'The approving family member must still have access to this family account.');
        $start = $approved->proposed_started_at;
        $end = $approved->proposed_completed_at;
        $seconds = $start && $end ? (int) $start->diffInSeconds($end, false) : 0;
        $break = (int) $approved->proposed_break_minutes;
        $this->require($approved->approved_at && ! $approved->approved_at->isFuture()
            && $start && $start->gte(Carbon::parse((string) data_get($payment->metadata, 'prepaid_visit_recovery.reopened_at')))
            && $end && ! $end->isFuture() && $approved->approved_at->gte($end)
            && $break >= 0 && $seconds > $break * 60 && $seconds % 60 === 0
            && (int) $approved->proposed_worked_minutes === intdiv($seconds, 60) - $break
            && $booking->timesheet_submitted_at && $booking->timesheet_submitted_at->gte($end),
            'The approved hours must describe the real completed visit after recovery.');
        $this->require(! app(CareBookingTimeCorrectionService::class)->overlappingVisits($booking, $start, $end)->exists(),
            'The approved hours overlap another visit. Keep the hold for review.');
    }

    private function context(int $bookingId, int $ticketId, int $timeCorrectionId): array
    {
        $booking = CareBooking::query()->lockForUpdate()->findOrFail($bookingId);
        $payment = $booking->payment()->lockForUpdate()->firstOrFail();
        $ticket = SupportTicket::query()->lockForUpdate()->findOrFail($ticketId);
        $approved = CareBookingTimeCorrection::query()->lockForUpdate()->findOrFail($timeCorrectionId);

        return [$booking, $payment, $ticket, $approved];
    }

    private function state(CareBooking $booking, CareBookingPayment $payment, SupportTicket $ticket, CareBookingTimeCorrection $approved, ?int $receiptId = null): array
    {
        $approver = User::query()->find($approved->approved_by_user_id);
        $membership = $approver ? $this->families->membershipFor($approver, false) : null;

        return [
            'booking' => $booking->getAttributes(), 'financial' => $this->recovery->financialState($payment),
            'hold' => data_get($payment->metadata, 'prepaid_visit_recovery'),
            'ticket' => $ticket->only(['id', 'status', 'care_booking_id', 'care_request_id', 'opener_user_id']),
            'time_corrections' => $booking->timeCorrections()->orderBy('id')->get()->map->getAttributes()->all(),
            'approver' => $approver?->only(['id', 'role']), 'membership' => $membership?->getAttributes(),
            'family_account' => $membership?->familyAccount?->getAttributes(),
            'other_corrections' => $booking->corrections()->when($receiptId, fn ($query) => $query->whereKeyNot($receiptId))
                ->orderBy('id')->get(['id', 'action', 'status', 'updated_at'])->toArray(),
            'events' => $booking->events()->orderBy('id')->get(['id', 'event_type', 'actor_user_id', 'happened_at'])->toArray(),
        ];
    }

    private function checkpoint(CareBookingCorrection $receipt, CareBooking $booking, CareBookingPayment $payment, SupportTicket $ticket, CareBookingTimeCorrection $approved): void
    {
        $provider = (array) $receipt->fresh()->provider_payload;
        $provider['checkpoint'] = $this->fingerprint($this->state($booking, $payment, $ticket, $approved, $receipt->id));
        $receipt->forceFill(['provider_payload' => $provider])->save();
    }

    private function assertReceipt(CareBookingCorrection $receipt, int $bookingId, int $ticketId, int $timeCorrectionId, User $admin): void
    {
        $this->require((int) $receipt->care_booking_id === $bookingId && (int) $receipt->support_ticket_id === $ticketId
            && (int) $receipt->time_correction_request_id === $timeCorrectionId && (int) $receipt->actor_admin_user_id === $admin->id
            && $receipt->action === CareBookingCorrection::ACTION_ADJUST_PREPAID, 'The request ID belongs to another operation.');
    }

    private function fingerprint(array $state): string
    {
        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }

    private function assertAdmin(User $admin): void
    {
        if (! $admin->fresh()?->isAdministrator()) {
            throw new AuthorizationException;
        }
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['prepaid_adjustment' => $message]);
        }
    }
}
