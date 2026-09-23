<?php

namespace App\Services\Booking;

use App\Models\CareBooking;
use App\Models\CareBookingCorrection;
use App\Models\CareBookingEvent;
use App\Models\CareBookingPayment;
use App\Models\CareBookingPaymentOperation;
use App\Models\CaregiverPayoutItem;
use App\Models\CareRequest;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\PrepaidVisitPaymentGuard;
use App\Support\MarketplacePricing;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Admin-only, no Stripe writes and no messages. Every apply requires a fresh preview. */
class PrepaidVisitRecoveryService
{
    private const TRACKING = [
        'started_at', 'completed_at', 'paused_at', 'timesheet_submitted_at', 'worked_minutes',
        'family_confirmed_at', 'family_confirmed_by_user_id',
        'check_in_lat', 'check_in_lng', 'check_in_accuracy_meters', 'check_in_source', 'check_in_note',
        'check_out_lat', 'check_out_lng', 'check_out_accuracy_meters', 'check_out_source', 'check_out_note',
    ];

    public function __construct(private readonly BookingPaymentService $payments) {}

    public function preview(int $bookingId, int $ticketId, User $admin, bool $release = false): array
    {
        $this->assertAdmin($admin);

        return DB::transaction(function () use ($bookingId, $ticketId, $release): array {
            [$booking, $payment, $ticket] = $this->context($bookingId, $ticketId);

            return $this->buildPreview($booking, $payment, $ticket, $release);
        });
    }

    public function apply(
        int $bookingId,
        int $ticketId,
        User $admin,
        string $reason,
        string $requestId,
        string $expectedState,
        bool $noCareConfirmed,
        bool $providerVerified,
        bool $release = false,
    ): CareBookingCorrection {
        $this->assertAdmin($admin);
        $this->require(Str::isUuid($requestId), 'A UUID request ID is required.');
        $this->require(mb_strlen(trim($reason)) >= 10 && mb_strlen($reason) <= 2000, 'Record a reason between 10 and 2,000 characters.');
        $this->require($providerVerified, 'First verify the captured amount, refunds, disputes, and absence of transfers in Stripe.');
        $this->require($release || $noCareConfirmed, 'Explicit confirmation that no care occurred is required.');
        $action = $release ? CareBookingCorrection::ACTION_RELEASE_PREPAID : CareBookingCorrection::ACTION_REOPEN_PREPAID;

        return DB::transaction(function () use ($bookingId, $ticketId, $admin, $reason, $requestId, $expectedState, $release, $action): CareBookingCorrection {
            [$booking, $payment, $ticket] = $this->context($bookingId, $ticketId);
            $existing = CareBookingCorrection::query()->where('client_request_id', $requestId)->first();
            if ($existing) {
                $this->require((int) $existing->care_booking_id === $bookingId
                    && (int) $existing->support_ticket_id === $ticketId
                    && (int) $existing->actor_admin_user_id === $admin->id
                    && $existing->action === $action && $existing->succeeded(), 'Request ID already belongs to another operation.');

                return $existing;
            }

            $preview = $this->buildPreview($booking, $payment, $ticket, $release);
            $this->require(hash_equals($preview['expected_state'], $expectedState), 'Records changed after preview. Inspect again; nothing was changed.');
            $before = $this->snapshot($booking, $payment, $ticket);
            $correction = CareBookingCorrection::query()->create([
                'client_request_id' => $requestId,
                'care_booking_id' => $booking->id,
                'support_ticket_id' => $ticket->id,
                'actor_admin_user_id' => $admin->id,
                'source' => 'admin_prepaid_recovery',
                'action' => $action,
                'status' => CareBookingCorrection::STATUS_PENDING,
                'previous_charge_cents' => $payment->amount_captured_cents,
                'target_charge_cents' => $payment->amount_captured_cents,
                'payment_delta_cents' => 0,
                'caregiver_delta_cents' => 0,
                'family_approval_confirmed_at' => $release ? $booking->family_confirmed_at : null,
                'reason' => trim($reason),
                'before_snapshot' => $before,
                'requested_changes' => ['action' => $action, 'no_care_confirmed' => ! $release, 'provider_verified' => true],
                'preview' => $preview,
                'provider_payload' => [],
                'internal_note_client_id' => (string) Str::uuid(),
                'public_reply_client_id' => (string) Str::uuid(),
            ]);

            $metadata = (array) $payment->metadata;
            if ($release) {
                $metadata['prepaid_visit_recovery']['state'] = 'released';
                $metadata['prepaid_visit_recovery']['released_at'] = now()->toIso8601String();
                $metadata['prepaid_visit_recovery']['release_correction_id'] = $correction->id;
                $metadata['prepaid_visit_recovery']['released_by_user_id'] = $admin->id;
            } else {
                $metadata['prepaid_visit_recovery'] = [
                    'state' => 'held',
                    'reopened_at' => now()->toIso8601String(),
                    'reopen_correction_id' => $correction->id,
                    'support_ticket_id' => $ticket->id,
                    'financial_fingerprint' => $this->fingerprint($this->financialState($payment)),
                ];
                $booking->forceFill(array_merge(array_fill_keys(self::TRACKING, null), [
                    'status' => CareBooking::STATUS_SCHEDULED,
                    'total_paused_seconds' => 0,
                ]))->save();
                // Preserve schedule, terms, rates, notes, events and request/application identity.
                $ticket->forceFill(['care_booking_id' => $booking->id, 'care_request_id' => $booking->care_request_id])->save();
            }
            $payment->forceFill(['metadata' => $metadata])->save();
            $correction->forceFill([
                'status' => CareBookingCorrection::STATUS_SUCCEEDED,
                'booking_applied_at' => now(),
                'applied_at' => now(),
                'after_snapshot' => $this->snapshot($booking->fresh(), $payment->fresh(), $ticket->fresh()),
            ])->save();
            CareBookingEvent::query()->create([
                'care_booking_id' => $booking->id,
                'actor_user_id' => $admin->id,
                'actor_role' => 'admin',
                'event_type' => $release ? 'prepaid_visit_hold_released' : 'prepaid_visit_reopened_on_hold',
                'payload' => ['care_booking_correction_id' => $correction->id, 'support_ticket_id' => $ticket->id],
                'happened_at' => now(),
            ]);

            return $correction->fresh();
        });
    }

    private function buildPreview(CareBooking $booking, CareBookingPayment $payment, SupportTicket $ticket, bool $release): array
    {
        $this->require(in_array($ticket->status, [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_IN_PROGRESS], true), 'Keep the support ticket open during recovery.');
        $this->require(! $booking->care_plan_id && $booking->careRequest?->request_type === CareRequest::TYPE_ONE_TIME, 'This recovery supports one-time visits only.');
        $this->require(! $booking->cancelled_at && ! $booking->dispute_opened_at && ! $booking->no_show_flag
            && ! $booking->replacement_released_at, 'Cancelled, disputed, no-show, or replaced visits require separate review.');
        $this->require($booking->scheduled_start_at && $booking->scheduled_end_at
            && $booking->scheduled_end_at->gt($booking->scheduled_start_at), 'A valid existing schedule is required.');
        $this->require($payment->pricing_version === app(MarketplacePricing::class)->currentVersion()
            && $booking->pricing_version === $payment->pricing_version, 'Only the current payment ledger is supported.');
        $this->require(in_array($payment->status, [CareBookingPayment::STATUS_CAPTURED, CareBookingPayment::STATUS_TRANSFER_FAILED], true)
            && (int) $payment->amount_captured_cents > 0 && (int) $payment->amount_refunded_cents === 0
            && ! $payment->stripe_transfer_id && ! $payment->transferred_at
            && ! $payment->stripe_last_refund_id && ! $payment->stripe_overage_payment_intent_id
            && (int) $payment->overage_pending_cents === 0 && (int) $payment->amount_overage_cents === 0,
            'Payment must be captured, unrefunded, untransferred, and have no overage.');
        $allowedOperations = [CareBookingPaymentOperation::TYPE_AUTHORIZATION, CareBookingPaymentOperation::TYPE_CHARGE,
            CareBookingPaymentOperation::TYPE_PROCESSING_FEE, CareBookingPaymentOperation::TYPE_EARNING,
            CareBookingPaymentOperation::TYPE_AUTHORIZATION_RELEASE];
        $this->require(! $payment->operations()->whereNotIn('type', $allowedOperations)->exists()
            && ! $payment->operations()->where('status', '!=', CareBookingPaymentOperation::STATUS_SUCCEEDED)->exists(),
            'A transfer, refund, dispute, failed, or pending operation requires separate review.');
        $charges = $payment->operations()->where('type', CareBookingPaymentOperation::TYPE_CHARGE)->get();
        $this->require($charges->count() === 1 && (int) $charges->sum('amount_cents') === (int) $payment->amount_captured_cents
            && data_get($charges->first()?->metadata, 'kind') === 'primary'
            && $charges->first()?->stripe_object_id === $payment->stripe_primary_charge_id,
            'The original captured charge must match the ledger.');
        $this->require($payment->fee_finalization_status === 'finalized', 'Processing fees must be finalized before recovery.');
        $payoutItem = $booking->payoutItem()->with('payout')->first();
        $this->require(! $payoutItem || (! $payoutItem->paid_at && $payoutItem->status === CaregiverPayoutItem::STATUS_SCHEDULED
            && ! $payoutItem->stripe_transfer_ids && ! $payoutItem->payout?->paid_at && ! $payoutItem->payout?->provider_reference),
            'The payout record indicates payment or another settlement requiring review.');
        $this->require(! $booking->corrections()->whereIn('status', [CareBookingCorrection::STATUS_PENDING,
            CareBookingCorrection::STATUS_PROCESSING, CareBookingCorrection::STATUS_REQUIRES_ACTION, CareBookingCorrection::STATUS_FAILED])->exists()
            && ! $booking->timeCorrections()->whereIn('status', \App\Models\CareBookingTimeCorrection::activeStatuses())->exists(),
            'Finish or investigate existing corrections first.');

        if ($release) {
            $this->require($payment->hasPrepaidVisitHold(), 'This payment is not held for a prepaid visit recovery.');
            PrepaidVisitPaymentGuard::assertActualVisitCompleted($booking, $payment);
            $this->require($booking->family_confirmed_at && $booking->family_confirmed_at->gte($booking->completed_at)
                && ! $booking->family_confirmed_at->isFuture()
                && (int) $booking->family_confirmed_by_user_id === (int) $booking->family_user_id,
                'The owning family must explicitly approve the actual completed visit first.');
            $this->require(hash_equals((string) data_get($payment->metadata, 'prepaid_visit_recovery.financial_fingerprint'),
                $this->fingerprint($this->financialState($payment))), 'Financial records changed during the hold. Review them before release.');
            $quote = $this->payments->quoteForWorkedMinutes($booking, (int) $booking->worked_minutes);
            $this->require((int) $quote['total_charge_cents'] === (int) $payment->amount_captured_cents
                && (int) $quote['caregiver_amount_cents'] === (int) $payment->caregiver_amount_cents,
                'Actual approved hours change the bill or earnings. Keep the hold; a separately approved adjustment is required.');
        } else {
            $this->require(! data_get($payment->metadata, 'prepaid_visit_recovery'), 'This payment already has recovery history.');
            $this->require($booking->status === CareBooking::STATUS_COMPLETED && ! $booking->reviewed_at
                && ! $booking->reviews()->exists(), 'Only an accidentally completed, unreviewed visit can be reset.');
            $this->require($booking->scheduled_start_at->isFuture(), 'The intended visit must still be in the future; otherwise review actual hours.');
        }

        return [
            'action' => $release ? 'release_prepaid' : 'reopen_prepaid',
            'booking_id' => $booking->id,
            'care_request_id' => $booking->care_request_id,
            'support_ticket_id' => $ticket->id,
            'caregiver_user_id' => $booking->caregiver_user_id,
            'family_user_id' => $booking->family_user_id,
            'payment_id' => $payment->id,
            'scheduled_start_eastern' => $booking->scheduled_start_at->copy()->setTimezone('America/New_York')->format('Y-m-d H:i:s T'),
            'scheduled_end_eastern' => $booking->scheduled_end_at->copy()->setTimezone('America/New_York')->format('Y-m-d H:i:s T'),
            'captured_cents_retained' => (int) $payment->amount_captured_cents,
            'currency' => $payment->currency,
            'payment_delta_cents' => 0,
            'stripe_writes' => false,
            'messages_sent' => false,
            'payout_effect' => $release ? 'Automatic transfer retries become eligible; separate approval is required to apply this release.' : 'All money movement held until separate admin release.',
            'expected_state' => $this->fingerprint($this->snapshot($booking, $payment, $ticket)),
        ];
    }

    private function context(int $bookingId, int $ticketId): array
    {
        $booking = CareBooking::query()->lockForUpdate()->findOrFail($bookingId);
        $payment = $booking->payment()->lockForUpdate()->firstOrFail();
        $ticket = SupportTicket::query()->lockForUpdate()->findOrFail($ticketId);
        $this->require((int) $payment->family_user_id === (int) $booking->family_user_id
            && (int) $payment->caregiver_user_id === (int) $booking->caregiver_user_id,
            'Payment and booking parties do not match.');
        $this->require((int) $ticket->opener_user_id === (int) $booking->caregiver_user_id
            && (! $ticket->care_booking_id || (int) $ticket->care_booking_id === $booking->id)
            && (! $ticket->care_request_id || (int) $ticket->care_request_id === (int) $booking->care_request_id)
            && ((int) $ticket->care_booking_id === $booking->id
                || (int) $ticket->care_request_id === (int) $booking->care_request_id
                || parse_url((string) $ticket->origin_path, PHP_URL_PATH) === '/care-requests/'.$booking->care_request_id.'/apply'),
            'Ticket, caregiver, and exact visit do not match.');

        return [$booking, $payment, $ticket];
    }

    private function snapshot(CareBooking $booking, CareBookingPayment $payment, SupportTicket $ticket): array
    {
        return [
            'booking' => $booking->getAttributes(),
            'financial' => $this->financialState($payment),
            'hold' => data_get($payment->metadata, 'prepaid_visit_recovery'),
            'ticket' => $ticket->only(['id', 'status', 'opener_user_id', 'care_request_id', 'care_booking_id', 'origin_path']),
            'corrections' => $booking->corrections()->orderBy('id')->get(['id', 'action', 'status', 'updated_at'])->toArray(),
            'events' => $booking->events()->orderBy('id')->get(['id', 'event_type', 'actor_user_id', 'happened_at'])->toArray(),
        ];
    }

    private function financialState(CareBookingPayment $payment): array
    {
        $attributes = $payment->getAttributes();
        unset($attributes['stripe_payment_intent_client_secret'], $attributes['updated_at']);
        $metadata = (array) $payment->metadata;
        unset($metadata['prepaid_visit_recovery']);
        $attributes['metadata'] = $metadata;

        return [
            'payment' => $attributes,
            'operations' => $payment->operations()->orderBy('id')->get()->map(function ($operation): array {
                $attributes = $operation->getAttributes();
                unset($attributes['updated_at']);

                return $attributes;
            })->all(),
            'payout_item' => CaregiverPayoutItem::query()->with('payout')->where('care_booking_id', $payment->care_booking_id)->first()?->toArray(),
        ];
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
            throw ValidationException::withMessages(['prepaid_recovery' => $message]);
        }
    }
}
