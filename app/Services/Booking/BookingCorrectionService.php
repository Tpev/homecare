<?php

namespace App\Services\Booking;

use App\Exceptions\Payments\PaymentActionRequiredException;
use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareBookingCorrection;
use App\Models\CareBookingPayment;
use App\Models\CareBookingPaymentOperation;
use App\Models\CaregiverPayout;
use App\Models\CaregiverPayoutItem;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\BookingPaymentV2Service;
use App\Services\Payments\StripeClient;
use App\Services\RegularCare\CarePlanHealthService;
use App\Support\MarketplaceEvent;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class BookingCorrectionService
{
    public function __construct(
        private readonly BookingPaymentService $payments,
        private readonly StripeClient $stripe,
        private readonly BookingTrustService $trust,
        private readonly MarketplaceNotificationService $notifications,
        private readonly CarePlanHealthService $planHealth,
        private readonly BookingPaymentV2Service $paymentsV2,
    ) {}

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    public function preview(CareBooking $booking, array $changes): array
    {
        $booking->loadMissing([
            'application',
            'family',
            'caregiver.caregiverProfile',
            'payment',
        ]);

        $action = (string) ($changes['action'] ?? '');
        if (! in_array($action, [
            CareBookingCorrection::ACTION_REOPEN,
            CareBookingCorrection::ACTION_COMPLETE_AND_BILL,
        ], true)) {
            throw ValidationException::withMessages(['correctionAction' => 'Choose a supported correction action.']);
        }

        $payment = $booking->payment;
        $currentChargeCents = $this->effectiveChargeCents($payment);
        $currentCaregiverCents = $this->effectiveCaregiverCents($payment);

        if ($action === CareBookingCorrection::ACTION_REOPEN) {
            $financiallySettled = $currentChargeCents > 0
                || ($payment && in_array($payment->status, [
                    CareBookingPayment::STATUS_CAPTURED,
                    CareBookingPayment::STATUS_TRANSFERRED,
                    CareBookingPayment::STATUS_TRANSFER_FAILED,
                    CareBookingPayment::STATUS_PARTIALLY_REFUNDED,
                    CareBookingPayment::STATUS_REFUNDED,
                ], true));

            return [
                'action' => $action,
                'can_apply' => ! $financiallySettled,
                'blocking_message' => $financiallySettled
                    ? 'This visit already has captured payment activity. Correct and rebill it instead of reopening it.'
                    : null,
                'started_at' => null,
                'completed_at' => null,
                'break_minutes' => 0,
                'worked_minutes' => 0,
                'current_worked_minutes' => (int) ($booking->worked_minutes ?? 0),
                'current_charge_cents' => $currentChargeCents,
                'target_charge_cents' => $currentChargeCents,
                'payment_delta_cents' => 0,
                'current_caregiver_cents' => $currentCaregiverCents,
                'target_caregiver_cents' => $currentCaregiverCents,
                'caregiver_delta_cents' => 0,
                'hourly_rate' => null,
                'platform_fee_percent' => null,
                'payment_operation' => 'none',
                'payment_status' => $payment?->status ?: 'none',
            ];
        }

        $startedAt = $this->parseDate($changes['started_at'] ?? null, 'correctionStartedAt', 'Enter the approved start time.');
        $completedAt = $this->parseDate($changes['completed_at'] ?? null, 'correctionCompletedAt', 'Enter the approved end time.');
        if ($completedAt->lessThanOrEqualTo($startedAt)) {
            throw ValidationException::withMessages([
                'correctionCompletedAt' => 'The approved end time must be after the start time.',
            ]);
        }

        $elapsedMinutes = $startedAt->diffInMinutes($completedAt);
        $breakMinutes = max(0, (int) ($changes['break_minutes'] ?? 0));
        if ($breakMinutes >= $elapsedMinutes) {
            throw ValidationException::withMessages([
                'correctionBreakMinutes' => 'Break time must be shorter than the visit.',
            ]);
        }

        $workedMinutes = $elapsedMinutes - $breakMinutes;
        $quote = $this->payments->quoteForWorkedMinutes($booking, $workedMinutes);
        $targetChargeCents = (int) $quote['total_charge_cents'];
        $targetCaregiverCents = (int) $quote['caregiver_amount_cents'];
        $paymentDeltaCents = $targetChargeCents - $currentChargeCents;
        $caregiverDeltaCents = $targetCaregiverCents - $currentCaregiverCents;

        return [
            'action' => $action,
            'can_apply' => true,
            'blocking_message' => null,
            'started_at' => $startedAt->toIso8601String(),
            'completed_at' => $completedAt->toIso8601String(),
            'break_minutes' => $breakMinutes,
            'worked_minutes' => $workedMinutes,
            'current_worked_minutes' => (int) ($booking->worked_minutes ?? 0),
            'current_charge_cents' => $currentChargeCents,
            'target_charge_cents' => $targetChargeCents,
            'payment_delta_cents' => $paymentDeltaCents,
            'current_caregiver_cents' => $currentCaregiverCents,
            'target_caregiver_cents' => $targetCaregiverCents,
            'caregiver_delta_cents' => $caregiverDeltaCents,
            'hourly_rate' => (float) $quote['hourly_rate'],
            'platform_fee_percent' => (float) $quote['platform_fee_percent'],
            'payment_operation' => $paymentDeltaCents > 0
                ? ($currentChargeCents > 0 ? 'additional_charge' : 'capture')
                : ($paymentDeltaCents < 0 ? 'refund' : 'none'),
            'payment_status' => $payment?->status ?: 'none',
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function apply(
        SupportTicket $ticket,
        User $admin,
        array $changes,
        string $clientRequestId,
    ): CareBookingCorrection {
        $this->assertAdministrator($admin);
        $this->assertTicketCanBeCorrected($ticket);

        if (! Str::isUuid($clientRequestId)) {
            throw ValidationException::withMessages(['correctionClientRequestId' => 'Invalid correction request. Refresh and try again.']);
        }

        $existing = CareBookingCorrection::query()
            ->where('client_request_id', $clientRequestId)
            ->first();
        if ($existing) {
            if ((int) $existing->care_booking_id !== (int) $ticket->care_booking_id) {
                throw new AuthorizationException;
            }

            return $existing;
        }

        $reason = trim((string) ($changes['reason'] ?? ''));
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages([
                'correctionReason' => 'Enter an operational reason between 10 and 2,000 characters.',
            ]);
        }

        $booking = $this->bookingForTicket($ticket);
        $timeCorrection = $ticket->timeCorrection()->first();
        $preview = $this->preview($booking, $changes);
        if (! ($preview['can_apply'] ?? false)) {
            throw ValidationException::withMessages([
                'correctionAction' => (string) ($preview['blocking_message'] ?? 'This correction cannot be applied.'),
            ]);
        }

        if (($preview['action'] ?? null) === CareBookingCorrection::ACTION_COMPLETE_AND_BILL
            && ! filter_var($changes['family_approved'] ?? false, FILTER_VALIDATE_BOOL)) {
            throw ValidationException::withMessages([
                'correctionFamilyApproved' => 'Confirm that the family approved this correction.',
            ]);
        }

        $correction = DB::transaction(function () use ($booking, $ticket, $timeCorrection, $admin, $changes, $clientRequestId, $reason, $preview): CareBookingCorrection {
            $lockedBooking = CareBooking::query()->lockForUpdate()->findOrFail($booking->id);
            $alreadyProcessing = CareBookingCorrection::query()
                ->where('care_booking_id', $lockedBooking->id)
                ->whereIn('status', [
                    CareBookingCorrection::STATUS_PROCESSING,
                    CareBookingCorrection::STATUS_REQUIRES_ACTION,
                ])
                ->exists();

            if ($alreadyProcessing) {
                throw ValidationException::withMessages([
                    'correctionAction' => 'This booking already has a correction in progress. Retry or finish that correction first.',
                ]);
            }

            return CareBookingCorrection::query()->create([
                'client_request_id' => $clientRequestId,
                'care_booking_id' => $lockedBooking->id,
                'support_ticket_id' => $ticket->id,
                'actor_admin_user_id' => $admin->id,
                'source' => $timeCorrection ? 'admin_time_correction' : 'admin',
                'time_correction_request_id' => $timeCorrection?->id,
                'requester_user_id' => $timeCorrection?->requester_user_id,
                'approved_by_user_id' => $timeCorrection?->approved_by_user_id,
                'action' => $preview['action'],
                'status' => CareBookingCorrection::STATUS_PENDING,
                'previous_charge_cents' => (int) $preview['current_charge_cents'],
                'target_charge_cents' => (int) $preview['target_charge_cents'],
                'payment_delta_cents' => (int) $preview['payment_delta_cents'],
                'caregiver_delta_cents' => (int) $preview['caregiver_delta_cents'],
                'family_approval_confirmed_at' => ($preview['action'] === CareBookingCorrection::ACTION_COMPLETE_AND_BILL) ? now() : null,
                'reason' => $reason,
                'before_snapshot' => $this->snapshot($lockedBooking),
                'requested_changes' => [
                    'action' => $preview['action'],
                    'started_at' => $preview['started_at'],
                    'completed_at' => $preview['completed_at'],
                    'break_minutes' => (int) $preview['break_minutes'],
                    'family_approved' => (bool) ($changes['family_approved'] ?? false),
                ],
                'preview' => $preview,
                'provider_payload' => [],
                'internal_note_client_id' => (string) Str::uuid(),
                'public_reply_client_id' => (string) Str::uuid(),
            ]);
        });

        return $this->execute($correction, $admin);
    }

    public function retry(CareBookingCorrection $correction, User $admin): CareBookingCorrection
    {
        $this->assertAdministrator($admin);
        if (! in_array($correction->status, [
            CareBookingCorrection::STATUS_REQUIRES_ACTION,
            CareBookingCorrection::STATUS_FAILED,
        ], true)) {
            return $correction->fresh();
        }

        if ($correction->status === CareBookingCorrection::STATUS_REQUIRES_ACTION) {
            $provider = (array) $correction->provider_payload;
            $intentId = trim((string) data_get($provider, 'action_required.payment_intent_id', ''));
            if ($intentId !== '') {
                $this->stripe->cancelPaymentIntent($intentId);
                data_set($provider, 'action_required.cancelled_at', now()->toIso8601String());
                data_forget($provider, 'additional_charge');
                $correction->forceFill([
                    'provider_payload' => $provider,
                    'attempt_count' => max(1, (int) $correction->attempt_count) + 1,
                ])->save();
            }
        }

        return $this->execute($correction->fresh(), $admin);
    }

    private function execute(CareBookingCorrection $correction, User $admin): CareBookingCorrection
    {
        if ($correction->succeeded()) {
            return $correction;
        }

        $correction->forceFill([
            'status' => CareBookingCorrection::STATUS_PROCESSING,
            'attempt_count' => max(1, (int) $correction->attempt_count),
            'last_error' => null,
        ])->save();

        try {
            if ($correction->action === CareBookingCorrection::ACTION_REOPEN) {
                $this->executeReopen($correction, $admin);
            } else {
                $this->executeCompleteAndBill($correction, $admin);
            }
        } catch (PaymentActionRequiredException $exception) {
            $provider = (array) $correction->fresh()->provider_payload;
            $provider['action_required'] = [
                'payment_intent_id' => $exception->paymentIntentId,
                'client_secret' => $exception->clientSecret,
                'recorded_at' => now()->toIso8601String(),
            ];
            $correction->forceFill([
                'status' => CareBookingCorrection::STATUS_REQUIRES_ACTION,
                'provider_payload' => $provider,
                'last_error' => $exception->userMessage,
                'after_snapshot' => $this->snapshot($correction->booking()->firstOrFail()),
            ])->save();
            $this->recordCorrectionEvent($correction, $admin, 'admin_booking_correction_requires_action');
            $this->notifyPaymentActionRequired($correction);
        } catch (PaymentException $exception) {
            $this->markFailed($correction, $admin, $exception->userMessage);
        } catch (Throwable $exception) {
            report($exception);
            $this->markFailed($correction, $admin, 'The correction could not be completed. No automatic retry was made.');
        } finally {
            $booking = $correction->booking()->first();
            if ($booking?->care_plan_id) {
                try {
                    $this->planHealth->reconcileForBooking($booking);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        }

        return $correction->fresh(['booking.payment']);
    }

    private function executeReopen(CareBookingCorrection $correction, User $admin): void
    {
        DB::transaction(function () use ($admin, $correction): void {
            $booking = CareBooking::query()->lockForUpdate()->findOrFail($correction->care_booking_id);
            $preview = $this->preview($booking, ['action' => CareBookingCorrection::ACTION_REOPEN]);
            if (! ($preview['can_apply'] ?? false)) {
                throw new PaymentException((string) $preview['blocking_message']);
            }

            $booking->forceFill([
                'status' => CareBooking::STATUS_SCHEDULED,
                'started_at' => null,
                'completed_at' => null,
                'paused_at' => null,
                'total_paused_seconds' => 0,
                'timesheet_submitted_at' => null,
                'worked_minutes' => null,
                'family_confirmed_at' => null,
                'check_in_lat' => null,
                'check_in_lng' => null,
                'check_in_accuracy_meters' => null,
                'check_in_source' => null,
                'check_in_note' => null,
                'check_in_override_at' => now(),
                'check_in_override_by_user_id' => $admin->id,
                'check_in_override_reason' => $correction->reason,
                'check_out_lat' => null,
                'check_out_lng' => null,
                'check_out_accuracy_meters' => null,
                'check_out_source' => null,
                'check_out_note' => null,
            ])->save();

            $correction->forceFill([
                'status' => CareBookingCorrection::STATUS_SUCCEEDED,
                'booking_applied_at' => now(),
                'payout_applied_at' => now(),
                'applied_at' => now(),
                'after_snapshot' => $this->snapshot($booking->fresh()),
                'last_error' => null,
            ])->save();
        });

        $this->recordCorrectionEvent($correction, $admin, 'admin_booking_reopened');
        $this->trust->recomputeReliabilityForBooking($correction->booking()->firstOrFail());
    }

    private function executeCompleteAndBill(CareBookingCorrection $correction, User $admin): void
    {
        $booking = $correction->booking()->with(['payment', 'family', 'caregiver.caregiverProfile', 'application'])->firstOrFail();
        $requested = (array) $correction->requested_changes;

        if (! $correction->booking_applied_at) {
            DB::transaction(function () use ($booking, $correction, $requested): void {
                $lockedBooking = CareBooking::query()->lockForUpdate()->findOrFail($booking->id);
                $startedAt = Carbon::parse((string) $requested['started_at']);
                $completedAt = Carbon::parse((string) $requested['completed_at']);
                $breakMinutes = max(0, (int) ($requested['break_minutes'] ?? 0));
                $workedMinutes = $startedAt->diffInMinutes($completedAt) - $breakMinutes;

                $lockedBooking->forceFill([
                    'status' => CareBooking::STATUS_COMPLETED,
                    'started_at' => $startedAt,
                    'completed_at' => $completedAt,
                    'paused_at' => null,
                    'total_paused_seconds' => $breakMinutes * 60,
                    'worked_minutes' => $workedMinutes,
                    'timesheet_submitted_at' => $lockedBooking->timesheet_submitted_at ?: now(),
                    'family_confirmed_at' => $lockedBooking->family_confirmed_at ?: now(),
                    'check_in_source' => 'admin_correction',
                    'check_in_note' => 'Corrected by admin: '.$correction->reason,
                    'check_out_source' => 'admin_correction',
                    'check_out_note' => 'Corrected by admin: '.$correction->reason,
                ])->save();

                $correction->forceFill(['booking_applied_at' => now()])->save();
            });
        }

        $booking = $correction->booking()->with(['payment', 'family', 'caregiver.caregiverProfile', 'application'])->firstOrFail();
        $this->reconcileFinancials($correction, $booking);

        $booking = $correction->booking()->with('payment')->firstOrFail();
        if ((int) ($booking->payment?->overage_pending_cents ?? 0) > 0) {
            throw new PaymentActionRequiredException(
                'The approved hours exceed the completed card charge. The family must update or confirm billing before retrying.',
            );
        }

        $currentNet = $this->effectiveChargeCents($booking->payment);
        if ($currentNet !== (int) $correction->target_charge_cents) {
            throw new PaymentException('Payment totals do not yet match the approved correction. Retry after reviewing billing.');
        }

        $correction->forceFill([
            'status' => CareBookingCorrection::STATUS_SUCCEEDED,
            'applied_at' => now(),
            'after_snapshot' => $this->snapshot($booking),
            'last_error' => null,
        ])->save();

        $this->recordCorrectionEvent($correction, $admin, 'admin_booking_correction_applied');
        $this->trust->recomputeReliabilityForBooking($booking);
        $this->notifyCorrectionSucceeded($correction);
    }

    private function reconcileFinancials(CareBookingCorrection $correction, CareBooking $booking): void
    {
        $payment = $booking->payment;
        $isCaptured = $payment && in_array($payment->status, [
            CareBookingPayment::STATUS_CAPTURED,
            CareBookingPayment::STATUS_TRANSFERRED,
            CareBookingPayment::STATUS_TRANSFER_FAILED,
            CareBookingPayment::STATUS_PARTIALLY_REFUNDED,
            CareBookingPayment::STATUS_REFUNDED,
        ], true);

        if (! $isCaptured) {
            if (! $payment || ! in_array($payment->status, [CareBookingPayment::STATUS_AUTHORIZED], true)) {
                $this->payments->authorizeForBooking($booking, forceReauthorize: (bool) $payment);
            }

            $this->payments->captureForBooking($booking->fresh());
            $booking->refresh()->load('payment');
            $payment = $booking->payment;
        }

        if (! $payment) {
            throw new PaymentException('Payment record is missing after the correction capture attempt.');
        }

        if ($payment->status === CareBookingPayment::STATUS_TRANSFER_FAILED) {
            $payment = $this->payments->retryTransfer($payment);
            $booking->refresh()->load(['payment', 'caregiver.caregiverProfile']);
        }

        $currentNet = $this->effectiveChargeCents($payment);
        $target = (int) $correction->target_charge_cents;
        if ($currentNet < $target) {
            $this->chargeIncrease($correction, $booking->fresh(['payment', 'family', 'caregiver.caregiverProfile']), $target - $currentNet);
        } elseif ($currentNet > $target) {
            $this->refundDecrease($correction, $booking->fresh(['payment', 'caregiver.caregiverProfile']), $currentNet - $target);
        } elseif (! $correction->payout_applied_at && data_get($correction->provider_payload, 'additional_charge.payment_intent_id')) {
            $this->applyPayoutIncrease(
                $correction,
                $booking->fresh(['payment', 'caregiver.caregiverProfile']),
                max(0, (int) data_get(
                    $correction->provider_payload,
                    'additional_charge.caregiver_delta_cents',
                    $correction->caregiver_delta_cents,
                )),
            );
        } elseif (! $correction->payout_applied_at && data_get($correction->provider_payload, 'refunds')) {
            if ($this->paymentsV2->usesV2($payment)) {
                $this->paymentsV2->retryPendingTransferReversals($payment);
                $this->recordV2CorrectionRefunds(
                    $correction,
                    $payment->fresh(),
                    'booking-correction-'.$correction->id,
                );
                $correction->forceFill(['payout_applied_at' => now()])->save();
            } else {
                $caregiverRefundCents = abs(min(0, (int) $correction->caregiver_delta_cents));
                $this->reversePayoutTransfers($correction, $payment, $caregiverRefundCents);
                $this->applyPayoutDecrease($correction, $booking, $caregiverRefundCents);
            }
        } else {
            $correction->forceFill(['payout_applied_at' => $correction->payout_applied_at ?: now()])->save();
        }
    }

    private function chargeIncrease(CareBookingCorrection $correction, CareBooking $booking, int $amountCents): void
    {
        $payment = $booking->payment;
        if (! $payment) {
            throw new PaymentException('Payment record is missing for the additional charge.');
        }
        if ($this->paymentsV2->usesV2($payment)) {
            $adjustmentReference = 'booking-correction-'.$correction->id;
            $updatedPayment = $this->paymentsV2->chargeAdjustment(
                $booking,
                $payment,
                $amountCents,
                $adjustmentReference,
            );
            $charge = $updatedPayment->operations()
                ->where('type', CareBookingPaymentOperation::TYPE_CHARGE)
                ->where('metadata->kind', 'adjustment-'.$adjustmentReference)
                ->latest('id')
                ->first();
            $provider = (array) $correction->provider_payload;
            $provider['additional_charge'] = [
                'payment_intent_id' => (string) data_get($charge?->metadata, 'payment_intent_id', ''),
                'charge_id' => $charge?->stripe_object_id,
                'amount_cents' => (int) ($charge?->amount_cents ?? $amountCents),
                'caregiver_delta_cents' => max(0, (int) $correction->caregiver_delta_cents),
                'ledger_operation_id' => $charge?->id,
                'charged_at' => ($charge?->processed_at ?: now())->toIso8601String(),
            ];
            $correction->forceFill([
                'provider_payload' => $provider,
                'payout_applied_at' => now(),
            ])->save();

            return;
        }

        $provider = (array) $correction->provider_payload;
        $charge = (array) ($provider['additional_charge'] ?? []);
        if (! isset($charge['payment_intent_id'])) {
            $family = $booking->family;
            if (! $family) {
                throw new PaymentException('Family billing profile is missing.');
            }

            $customerId = (string) ($payment->stripe_customer_id ?: $this->stripe->ensureFamilyCustomer($family));
            $paymentMethod = $this->stripe->defaultPaymentMethodForCustomer($customerId);
            if (! $paymentMethod) {
                throw new PaymentActionRequiredException('The family must add or update a card before this correction can be charged.');
            }

            $attempt = max(1, (int) $correction->attempt_count);
            $result = $this->stripe->createAndConfirmCharge(
                $booking,
                $customerId,
                (string) $paymentMethod['id'],
                $amountCents,
                (string) $payment->currency,
                [
                    'care_booking_correction_id' => (string) $correction->id,
                    'support_ticket_id' => (string) $correction->support_ticket_id,
                    'type' => 'admin_booking_correction',
                ],
                'booking-correction-'.$correction->client_request_id.':charge:'.$attempt,
            );

            $charge = [
                'payment_intent_id' => (string) $result['id'],
                'amount_cents' => (int) $result['amount_received'],
                'attempt' => $attempt,
                'charged_at' => now()->toIso8601String(),
            ];
            $provider['additional_charge'] = $charge;
            $correction->forceFill(['provider_payload' => $provider])->save();
        }

        $actualChargeCents = (int) ($charge['amount_cents'] ?? $amountCents);
        $targetCaregiverCents = (int) data_get($correction->preview, 'target_caregiver_cents', 0);
        $caregiverDeltaCents = max(0, $targetCaregiverCents - $this->effectiveCaregiverCents($payment));
        $platformDeltaCents = max(0, $actualChargeCents - $caregiverDeltaCents);
        if (! isset($charge['caregiver_delta_cents'])) {
            $charge['caregiver_delta_cents'] = $caregiverDeltaCents;
            $provider['additional_charge'] = $charge;
            $correction->forceFill(['provider_payload' => $provider])->save();
        }

        DB::transaction(function () use ($payment, $correction, $charge, $actualChargeCents, $caregiverDeltaCents, $platformDeltaCents): void {
            $lockedPayment = CareBookingPayment::query()->lockForUpdate()->findOrFail($payment->id);
            $metadata = (array) $lockedPayment->metadata;
            $components = array_values((array) ($metadata['correction_charge_components'] ?? []));
            $alreadyApplied = collect($components)->contains(
                fn ($component): bool => (int) ($component['correction_id'] ?? 0) === $correction->id
            );

            if (! $alreadyApplied) {
                $components[] = [
                    'correction_id' => $correction->id,
                    'payment_intent_id' => (string) $charge['payment_intent_id'],
                    'amount_cents' => $actualChargeCents,
                    'refunded_cents' => 0,
                ];
                $metadata['correction_charge_components'] = $components;

                $lockedPayment->forceFill([
                    'status' => CareBookingPayment::STATUS_CAPTURED,
                    'stripe_overage_payment_intent_id' => (string) $charge['payment_intent_id'],
                    'amount_captured_cents' => (int) $lockedPayment->amount_captured_cents + $actualChargeCents,
                    'amount_overage_cents' => (int) $lockedPayment->amount_overage_cents + $actualChargeCents,
                    'overage_pending_cents' => 0,
                    'platform_fee_cents' => (int) $lockedPayment->platform_fee_cents + $platformDeltaCents,
                    'caregiver_amount_cents' => (int) $lockedPayment->caregiver_amount_cents + $caregiverDeltaCents,
                    'metadata' => $metadata,
                    'last_error' => null,
                    'failed_at' => null,
                ])->save();
            }
        });

        $this->applyPayoutIncrease($correction, $booking->fresh(['payment', 'caregiver.caregiverProfile']), $caregiverDeltaCents);
    }

    private function refundDecrease(CareBookingCorrection $correction, CareBooking $booking, int $amountCents): void
    {
        $payment = $booking->payment;
        if (! $payment) {
            throw new PaymentException('Payment record is missing for the refund.');
        }
        if ($this->paymentsV2->usesV2($payment)) {
            $adjustmentReference = 'booking-correction-'.$correction->id;
            try {
                $updatedPayment = $this->paymentsV2->refund(
                    $booking,
                    $payment,
                    $amountCents,
                    'requested_by_customer',
                    abs(min(0, (int) $correction->caregiver_delta_cents)),
                    $adjustmentReference,
                );
            } catch (PaymentException $exception) {
                $this->recordV2CorrectionRefunds(
                    $correction,
                    $payment->fresh(),
                    $adjustmentReference,
                );

                throw $exception;
            }
            $this->recordV2CorrectionRefunds($correction, $updatedPayment, $adjustmentReference);
            $correction->forceFill([
                'payout_applied_at' => now(),
            ])->save();

            return;
        }

        $components = $this->chargeComponents($payment);
        $provider = (array) $correction->provider_payload;
        $refunds = array_values((array) ($provider['refunds'] ?? []));
        $remaining = $amountCents;

        foreach (array_reverse(array_keys($components)) as $index) {
            if ($remaining <= 0) {
                break;
            }

            $component = $components[$index];
            $available = max(0, (int) $component['amount_cents'] - (int) $component['refunded_cents']);
            if ($available <= 0 || empty($component['payment_intent_id'])) {
                continue;
            }

            $refundCents = min($remaining, $available);
            $operationKey = (string) $component['payment_intent_id'].':'.$refundCents;
            $existing = collect($refunds)->firstWhere('operation_key', $operationKey);
            if (! $existing) {
                $result = $this->stripe->createRefund(
                    (string) $component['payment_intent_id'],
                    $refundCents,
                    'requested_by_customer',
                    [
                        'care_booking_id' => (string) $booking->id,
                        'care_booking_correction_id' => (string) $correction->id,
                        'support_ticket_id' => (string) $correction->support_ticket_id,
                    ],
                    'booking-correction-'.$correction->client_request_id.':refund:'.count($refunds),
                );
                $existing = [
                    'operation_key' => $operationKey,
                    'payment_intent_id' => (string) $component['payment_intent_id'],
                    'refund_id' => (string) $result['id'],
                    'amount_cents' => (int) $result['amount'],
                    'status' => (string) $result['status'],
                ];
                $refunds[] = $existing;
                $provider['refunds'] = $refunds;
                $correction->forceFill(['provider_payload' => $provider])->save();
            }

            $applied = (int) $existing['amount_cents'];
            $components[$index]['refunded_cents'] = (int) $components[$index]['refunded_cents'] + $applied;
            $remaining -= $applied;
        }

        if ($remaining > 0) {
            throw new PaymentException('The refundable payment balance is lower than this correction requires.');
        }

        $caregiverRefundCents = abs(min(0, (int) $correction->caregiver_delta_cents));
        $this->reversePayoutTransfers($correction, $payment, $caregiverRefundCents);

        DB::transaction(function () use ($payment, $correction, $components, $amountCents): void {
            $lockedPayment = CareBookingPayment::query()->lockForUpdate()->findOrFail($payment->id);
            $metadata = (array) $lockedPayment->metadata;
            $appliedCorrections = array_map('intval', (array) ($metadata['applied_refund_correction_ids'] ?? []));
            if (! in_array($correction->id, $appliedCorrections, true)) {
                $newRefunded = (int) $lockedPayment->amount_refunded_cents + $amountCents;
                $captured = (int) $lockedPayment->amount_captured_cents;
                $metadata['normalized_charge_components'] = $components;
                $metadata['applied_refund_correction_ids'] = [...$appliedCorrections, $correction->id];

                $lockedPayment->forceFill([
                    'status' => $newRefunded >= $captured
                        ? CareBookingPayment::STATUS_REFUNDED
                        : CareBookingPayment::STATUS_PARTIALLY_REFUNDED,
                    'amount_refunded_cents' => $newRefunded,
                    'stripe_last_refund_id' => (string) data_get($correction->fresh()->provider_payload, 'refunds.'.(count((array) data_get($correction->fresh()->provider_payload, 'refunds', [])) - 1).'.refund_id'),
                    'metadata' => $metadata,
                    'last_error' => null,
                    'failed_at' => null,
                ])->save();
            }
        });

        $this->applyPayoutDecrease($correction, $booking, $caregiverRefundCents);
    }

    private function applyPayoutIncrease(CareBookingCorrection $correction, CareBooking $booking, int $caregiverDeltaCents): void
    {
        if ($caregiverDeltaCents <= 0 || $correction->payout_applied_at) {
            $correction->forceFill(['payout_applied_at' => $correction->payout_applied_at ?: now()])->save();

            return;
        }

        $provider = (array) $correction->provider_payload;
        $profile = $booking->caregiver?->caregiverProfile;
        $transferId = (string) data_get($provider, 'payout_transfer.transfer_id', '');
        if ($profile?->stripeConnectIsReady() && $profile->stripe_connect_account_id && $transferId === '') {
            $transfer = $this->stripe->createTransfer(
                (string) $profile->stripe_connect_account_id,
                $caregiverDeltaCents,
                (string) $booking->payment?->currency,
                [
                    'care_booking_id' => (string) $booking->id,
                    'care_booking_correction_id' => (string) $correction->id,
                    'type' => 'admin_booking_correction',
                ],
                'booking-correction-'.$correction->client_request_id.':transfer',
            );
            $transferId = (string) $transfer['id'];
            $provider['payout_transfer'] = [
                'transfer_id' => $transferId,
                'amount_cents' => $caregiverDeltaCents,
                'status' => (string) $transfer['status'],
            ];
            $correction->forceFill(['provider_payload' => $provider])->save();
        }

        if ($transferId !== '') {
            DB::transaction(function () use ($booking, $correction, $transferId, $caregiverDeltaCents): void {
                $payment = CareBookingPayment::query()->lockForUpdate()->where('care_booking_id', $booking->id)->firstOrFail();
                $metadata = (array) $payment->metadata;
                $transfers = array_values((array) ($metadata['correction_transfer_components'] ?? []));
                if (! collect($transfers)->contains(fn ($component): bool => (int) ($component['correction_id'] ?? 0) === $correction->id)) {
                    $transfers[] = [
                        'correction_id' => $correction->id,
                        'transfer_id' => $transferId,
                        'amount_cents' => $caregiverDeltaCents,
                        'reversed_cents' => 0,
                    ];
                    $metadata['correction_transfer_components'] = $transfers;
                    $payment->forceFill([
                        'status' => CareBookingPayment::STATUS_TRANSFERRED,
                        'metadata' => $metadata,
                    ])->save();
                }
            });
        }

        DB::transaction(function () use ($correction, $booking, $caregiverDeltaCents, $transferId): void {
            $lockedCorrection = CareBookingCorrection::query()->lockForUpdate()->findOrFail($correction->id);
            if ($lockedCorrection->payout_applied_at) {
                return;
            }

            $item = CaregiverPayoutItem::query()->where('care_booking_id', $booking->id)->first();
            $payout = $item?->payout;
            if (! $payout) {
                $payout = CaregiverPayout::query()->create([
                    'caregiver_user_id' => $booking->caregiver_user_id,
                    'period_start_on' => ($booking->completed_at ?: now())->toDateString(),
                    'period_end_on' => ($booking->completed_at ?: now())->toDateString(),
                    'scheduled_for' => $transferId !== '' ? now() : now()->next(Carbon::FRIDAY)->setTime(10, 0),
                    'paid_at' => $transferId !== '' ? now() : null,
                    'status' => $transferId !== '' ? CaregiverPayout::STATUS_PAID : CaregiverPayout::STATUS_SCHEDULED,
                    'currency' => strtoupper((string) $booking->payment?->currency),
                    'gross_amount' => 0,
                    'adjustments_amount' => 0,
                    'net_amount' => 0,
                    'provider_reference' => $transferId ?: null,
                    'notes' => 'Generated by booking correction #'.$correction->id,
                ]);
            }

            if ($item) {
                $item->forceFill([
                    'amount' => (float) $item->amount + ($caregiverDeltaCents / 100),
                    'status' => $transferId !== '' ? CaregiverPayoutItem::STATUS_PAID : $item->status,
                    'paid_at' => $transferId !== '' ? now() : $item->paid_at,
                    'notes' => trim(($item->notes ? $item->notes.' | ' : '').'Correction #'.$correction->id.' +$'.number_format($caregiverDeltaCents / 100, 2)),
                ])->save();
            } else {
                CaregiverPayoutItem::query()->create([
                    'caregiver_payout_id' => $payout->id,
                    'caregiver_user_id' => $booking->caregiver_user_id,
                    'care_booking_id' => $booking->id,
                    'status' => $transferId !== '' ? CaregiverPayoutItem::STATUS_PAID : CaregiverPayoutItem::STATUS_SCHEDULED,
                    'currency' => strtoupper((string) $booking->payment?->currency),
                    'amount' => $caregiverDeltaCents / 100,
                    'included_at' => now(),
                    'paid_at' => $transferId !== '' ? now() : null,
                    'notes' => 'Correction #'.$correction->id,
                ]);
            }

            $gross = (float) $payout->items()->sum('amount');
            $payout->forceFill([
                'gross_amount' => $gross,
                'net_amount' => $gross + (float) $payout->adjustments_amount,
            ])->save();
            $lockedCorrection->forceFill(['payout_applied_at' => now()])->save();
        });
    }

    private function applyPayoutDecrease(CareBookingCorrection $correction, CareBooking $booking, int $caregiverRefundCents): void
    {
        if ($caregiverRefundCents <= 0 || $correction->payout_applied_at) {
            $correction->forceFill(['payout_applied_at' => $correction->payout_applied_at ?: now()])->save();

            return;
        }

        DB::transaction(function () use ($correction, $booking, $caregiverRefundCents): void {
            $lockedCorrection = CareBookingCorrection::query()->lockForUpdate()->findOrFail($correction->id);
            if ($lockedCorrection->payout_applied_at) {
                return;
            }

            $item = CaregiverPayoutItem::query()->where('care_booking_id', $booking->id)->first();
            $payout = $item?->payout;
            if ($item && $payout) {
                $refundAmount = $caregiverRefundCents / 100;
                $item->forceFill([
                    'status' => (int) $correction->target_charge_cents === 0 ? CaregiverPayoutItem::STATUS_REVERSED : $item->status,
                    'notes' => trim(($item->notes ? $item->notes.' | ' : '').'Correction #'.$correction->id.' -$'.number_format($refundAmount, 2)),
                ])->save();
                $gross = (float) $payout->items()->sum('amount');
                $adjustments = (float) $payout->adjustments_amount - $refundAmount;
                $payout->forceFill([
                    'adjustments_amount' => $adjustments,
                    'net_amount' => $gross + $adjustments,
                ])->save();
            }

            $lockedCorrection->forceFill(['payout_applied_at' => now()])->save();
        });
    }

    private function reversePayoutTransfers(CareBookingCorrection $correction, CareBookingPayment $payment, int $amountCents): void
    {
        if ($amountCents <= 0) {
            return;
        }

        $components = $this->transferComponents($payment);
        $provider = (array) $correction->provider_payload;
        $reversals = array_values((array) ($provider['payout_reversals'] ?? []));
        $remaining = $amountCents;

        if ($components === [] && CaregiverPayoutItem::query()
            ->where('care_booking_id', $payment->care_booking_id)
            ->where('status', CaregiverPayoutItem::STATUS_PAID)
            ->exists()) {
            throw new PaymentException('The paid caregiver payout is missing its transfer reference. Review the payout before retrying this correction.');
        }

        foreach (array_reverse(array_keys($components)) as $index) {
            if ($remaining <= 0) {
                break;
            }

            $component = $components[$index];
            $available = max(0, (int) $component['amount_cents'] - (int) $component['reversed_cents']);
            if ($available <= 0 || empty($component['transfer_id'])) {
                continue;
            }

            $reversalCents = min($remaining, $available);
            $operationKey = (string) $component['transfer_id'].':'.$reversalCents;
            $existing = collect($reversals)->firstWhere('operation_key', $operationKey);
            if (! $existing) {
                $result = $this->stripe->createTransferReversal(
                    (string) $component['transfer_id'],
                    $reversalCents,
                    [
                        'care_booking_id' => (string) $payment->care_booking_id,
                        'care_booking_correction_id' => (string) $correction->id,
                    ],
                    'booking-correction-'.$correction->client_request_id.':transfer-reversal:'.count($reversals),
                );
                $existing = [
                    'operation_key' => $operationKey,
                    'transfer_id' => (string) $component['transfer_id'],
                    'reversal_id' => (string) $result['id'],
                    'amount_cents' => (int) $result['amount'],
                    'status' => (string) $result['status'],
                ];
                $reversals[] = $existing;
                $provider['payout_reversals'] = $reversals;
                $correction->forceFill(['provider_payload' => $provider])->save();
            }

            $applied = (int) $existing['amount_cents'];
            $components[$index]['reversed_cents'] = (int) $components[$index]['reversed_cents'] + $applied;
            $remaining -= $applied;
        }

        if ($remaining > 0 && collect($components)->contains(fn ($component): bool => ! empty($component['transfer_id']))) {
            throw new PaymentException('The caregiver transfer balance is lower than the payout reversal required by this correction.');
        }

        if ($components !== []) {
            $metadata = (array) $payment->metadata;
            $metadata['normalized_transfer_components'] = $components;
            $payment->forceFill([
                'stripe_last_transfer_reversal_id' => (string) data_get($reversals, (count($reversals) - 1).'.reversal_id'),
                'metadata' => $metadata,
            ])->save();
        }
    }

    /** @return list<array<string, mixed>> */
    private function transferComponents(CareBookingPayment $payment): array
    {
        $metadata = (array) $payment->metadata;
        $normalized = array_values((array) ($metadata['normalized_transfer_components'] ?? []));
        $correctionTransfers = array_values((array) ($metadata['correction_transfer_components'] ?? []));

        if ($normalized !== []) {
            $components = $normalized;
        } else {
            $correctionTotal = collect($correctionTransfers)->sum(fn ($component): int => (int) ($component['amount_cents'] ?? 0));
            $primaryAmount = max(0, (int) $payment->caregiver_amount_cents - $correctionTotal);
            $payoutTransferId = CaregiverPayoutItem::query()
                ->with('payout:id,provider_reference')
                ->where('care_booking_id', $payment->care_booking_id)
                ->first()
                ?->payout
                ?->provider_reference;
            $primaryTransferId = $payment->stripe_transfer_id ?: $payoutTransferId;
            $components = [];
            if ($primaryAmount > 0 && $primaryTransferId) {
                $components[] = [
                    'transfer_id' => (string) $primaryTransferId,
                    'amount_cents' => $primaryAmount,
                    'reversed_cents' => 0,
                ];
            }
        }

        foreach ($correctionTransfers as $transfer) {
            $transferId = (string) ($transfer['transfer_id'] ?? '');
            if ($transferId === '' || collect($components)->contains(fn ($component): bool => ($component['transfer_id'] ?? null) === $transferId)) {
                continue;
            }
            $components[] = [
                'transfer_id' => $transferId,
                'amount_cents' => (int) ($transfer['amount_cents'] ?? 0),
                'reversed_cents' => (int) ($transfer['reversed_cents'] ?? 0),
            ];
        }

        $knownReversed = collect($components)->sum(fn ($component): int => (int) ($component['reversed_cents'] ?? 0));
        $captured = max(1, (int) $payment->amount_captured_cents);
        $estimatedReversed = (int) round((int) $payment->amount_refunded_cents * ((int) $payment->caregiver_amount_cents / $captured));
        $unallocated = max(0, $estimatedReversed - $knownReversed);
        foreach (array_keys($components) as $index) {
            if ($unallocated <= 0) {
                break;
            }
            $available = max(0, (int) $components[$index]['amount_cents'] - (int) $components[$index]['reversed_cents']);
            $allocation = min($available, $unallocated);
            $components[$index]['reversed_cents'] = (int) $components[$index]['reversed_cents'] + $allocation;
            $unallocated -= $allocation;
        }

        return array_values($components);
    }

    /** @return list<array<string, mixed>> */
    private function chargeComponents(CareBookingPayment $payment): array
    {
        $metadata = (array) $payment->metadata;
        $normalized = array_values((array) ($metadata['normalized_charge_components'] ?? []));
        if ($normalized !== []) {
            $components = $normalized;
        } else {
            $primaryAmount = max(0, (int) $payment->amount_captured_cents - (int) $payment->amount_overage_cents);
            $components = [];
            if ($primaryAmount > 0 && $payment->stripe_payment_intent_id) {
                $components[] = [
                    'payment_intent_id' => (string) $payment->stripe_payment_intent_id,
                    'amount_cents' => $primaryAmount,
                    'refunded_cents' => 0,
                ];
            }
            $legacyOverage = max(0, (int) $payment->amount_overage_cents);
            if ($legacyOverage > 0 && $payment->stripe_overage_payment_intent_id) {
                $components[] = [
                    'payment_intent_id' => (string) $payment->stripe_overage_payment_intent_id,
                    'amount_cents' => $legacyOverage,
                    'refunded_cents' => 0,
                ];
            }
        }

        foreach ((array) ($metadata['correction_charge_components'] ?? []) as $correctionComponent) {
            $intentId = (string) ($correctionComponent['payment_intent_id'] ?? '');
            if ($intentId === '' || collect($components)->contains(fn ($component): bool => ($component['payment_intent_id'] ?? null) === $intentId)) {
                continue;
            }
            $components[] = [
                'payment_intent_id' => $intentId,
                'amount_cents' => (int) ($correctionComponent['amount_cents'] ?? 0),
                'refunded_cents' => (int) ($correctionComponent['refunded_cents'] ?? 0),
            ];
        }

        $knownRefunded = collect($components)->sum(fn ($component): int => (int) ($component['refunded_cents'] ?? 0));
        $unallocatedRefunded = max(0, (int) $payment->amount_refunded_cents - $knownRefunded);
        foreach (array_keys($components) as $index) {
            if ($unallocatedRefunded <= 0) {
                break;
            }
            $available = max(0, (int) $components[$index]['amount_cents'] - (int) $components[$index]['refunded_cents']);
            $allocation = min($available, $unallocatedRefunded);
            $components[$index]['refunded_cents'] = (int) $components[$index]['refunded_cents'] + $allocation;
            $unallocatedRefunded -= $allocation;
        }

        return array_values($components);
    }

    private function recordV2CorrectionRefunds(
        CareBookingCorrection $correction,
        CareBookingPayment $payment,
        string $adjustmentReference,
    ): void {
        $refunds = $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_REFUND)
            ->where('metadata->adjustment_reference', $adjustmentReference)
            ->orderBy('id')
            ->get()
            ->map(fn (CareBookingPaymentOperation $refund): array => [
                'operation_key' => $refund->idempotency_key,
                'refund_id' => $refund->stripe_object_id,
                'charge_id' => $refund->stripe_parent_object_id,
                'amount_cents' => (int) $refund->amount_cents,
                'status' => $refund->status,
                'ledger_operation_id' => $refund->id,
                'refunded_at' => $refund->processed_at?->toIso8601String(),
            ])
            ->all();
        $provider = (array) $correction->provider_payload;
        $provider['refunds'] = $refunds;
        $correction->forceFill(['provider_payload' => $provider])->save();
    }

    private function markFailed(CareBookingCorrection $correction, User $admin, string $message): void
    {
        $booking = $correction->booking()->with('payment')->firstOrFail();
        $provider = (array) $correction->provider_payload;
        $providerFinancialChanged = filled(data_get($provider, 'additional_charge.payment_intent_id'))
            || ! empty($provider['refunds'] ?? [])
            || filled(data_get($provider, 'payout_transfer.transfer_id'))
            || ! empty($provider['payout_reversals'] ?? []);
        $financialChanged = $providerFinancialChanged
            || $this->effectiveChargeCents($booking->payment) !== (int) $correction->previous_charge_cents;
        if (! $financialChanged) {
            $this->restoreBookingSnapshot($booking, (array) $correction->before_snapshot);
            $correction->forceFill(['booking_applied_at' => null])->save();
        }

        $correction->forceFill([
            'status' => $financialChanged
                ? CareBookingCorrection::STATUS_REQUIRES_ACTION
                : CareBookingCorrection::STATUS_FAILED,
            'last_error' => $message,
            'after_snapshot' => $this->snapshot($booking->fresh()),
        ])->save();
        $this->recordCorrectionEvent($correction, $admin, $financialChanged
            ? 'admin_booking_correction_requires_action'
            : 'admin_booking_correction_failed');
    }

    /** @param array<string, mixed> $snapshot */
    private function restoreBookingSnapshot(CareBooking $booking, array $snapshot): void
    {
        $fields = [
            'status', 'started_at', 'completed_at', 'paused_at', 'total_paused_seconds',
            'timesheet_submitted_at', 'worked_minutes', 'family_confirmed_at',
            'check_in_lat', 'check_in_lng', 'check_in_accuracy_meters', 'check_in_source', 'check_in_note',
            'check_out_lat', 'check_out_lng', 'check_out_accuracy_meters', 'check_out_source', 'check_out_note',
        ];

        $booking->forceFill(collect($fields)->mapWithKeys(
            fn (string $field): array => [$field => data_get($snapshot, 'booking.'.$field)]
        )->all())->save();
    }

    /** @return array<string, mixed> */
    private function snapshot(CareBooking $booking): array
    {
        $booking->loadMissing('payment');
        $dateFields = [
            'scheduled_start_at', 'scheduled_end_at', 'started_at', 'completed_at', 'paused_at',
            'timesheet_submitted_at', 'family_confirmed_at',
        ];
        $bookingFields = [
            'status', 'scheduled_start_at', 'scheduled_end_at', 'started_at', 'completed_at', 'paused_at',
            'total_paused_seconds', 'timesheet_submitted_at', 'expected_minutes', 'worked_minutes', 'family_confirmed_at',
            'check_in_lat', 'check_in_lng', 'check_in_accuracy_meters', 'check_in_source', 'check_in_note',
            'check_out_lat', 'check_out_lng', 'check_out_accuracy_meters', 'check_out_source', 'check_out_note',
        ];
        $bookingSnapshot = [];
        foreach ($bookingFields as $field) {
            $value = $booking->{$field};
            $bookingSnapshot[$field] = in_array($field, $dateFields, true)
                ? $value?->toIso8601String()
                : $value;
        }

        $payment = $booking->payment;

        return [
            'booking' => $bookingSnapshot,
            'payment' => $payment ? [
                'id' => $payment->id,
                'status' => $payment->status,
                'amount_authorized_cents' => (int) $payment->amount_authorized_cents,
                'amount_captured_cents' => (int) $payment->amount_captured_cents,
                'amount_refunded_cents' => (int) $payment->amount_refunded_cents,
                'amount_overage_cents' => (int) $payment->amount_overage_cents,
                'overage_pending_cents' => (int) $payment->overage_pending_cents,
                'platform_fee_cents' => (int) $payment->platform_fee_cents,
                'caregiver_amount_cents' => (int) $payment->caregiver_amount_cents,
                'stripe_payment_intent_id' => $payment->stripe_payment_intent_id,
                'stripe_overage_payment_intent_id' => $payment->stripe_overage_payment_intent_id,
                'stripe_transfer_id' => $payment->stripe_transfer_id,
            ] : null,
        ];
    }

    private function effectiveChargeCents(?CareBookingPayment $payment): int
    {
        if (! $payment) {
            return 0;
        }

        return max(0, (int) $payment->amount_captured_cents - (int) $payment->amount_refunded_cents);
    }

    private function effectiveCaregiverCents(?CareBookingPayment $payment): int
    {
        if (! $payment) {
            return 0;
        }

        $captured = max(0, (int) $payment->amount_captured_cents);
        $caregiver = max(0, (int) $payment->caregiver_amount_cents);
        if ($captured === 0) {
            return 0;
        }

        $caregiverRefund = (int) round((int) $payment->amount_refunded_cents * ($caregiver / $captured));

        return max(0, $caregiver - $caregiverRefund);
    }

    private function parseDate(mixed $value, string $field, string $message): Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            throw ValidationException::withMessages([$field => $message]);
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    private function bookingForTicket(SupportTicket $ticket): CareBooking
    {
        if (! $ticket->care_booking_id) {
            throw ValidationException::withMessages([
                'correctionAction' => 'This support ticket is not linked to a booking.',
            ]);
        }

        return CareBooking::query()
            ->with(['payment', 'family', 'caregiver.caregiverProfile', 'application'])
            ->findOrFail($ticket->care_booking_id);
    }

    private function assertAdministrator(User $admin): void
    {
        if (! $admin->isAdministrator()) {
            throw new AuthorizationException;
        }
    }

    private function assertTicketCanBeCorrected(SupportTicket $ticket): void
    {
        if ($ticket->status === SupportTicket::STATUS_CLOSED) {
            throw ValidationException::withMessages([
                'correctionAction' => 'Reopen this support ticket before applying a visit correction.',
            ]);
        }
    }

    private function recordCorrectionEvent(CareBookingCorrection $correction, User $admin, string $eventType): void
    {
        $this->trust->recordEvent(
            $correction->booking()->firstOrFail(),
            $admin->id,
            'admin',
            $eventType,
            [
                'care_booking_correction_id' => $correction->id,
                'support_ticket_id' => $correction->support_ticket_id,
                'action' => $correction->action,
                'status' => $correction->status,
                'reason' => $correction->reason,
                'family_approval_confirmed_at' => $correction->family_approval_confirmed_at?->toIso8601String(),
                'before' => $correction->before_snapshot,
                'after' => $correction->after_snapshot,
                'preview' => $correction->preview,
                'provider_payload' => $correction->provider_payload,
                'last_error' => $correction->last_error,
            ],
        );
    }

    private function notifyPaymentActionRequired(CareBookingCorrection $correction): void
    {
        $booking = $correction->booking()->with('family')->firstOrFail();
        if (! $booking->family) {
            return;
        }

        $this->notifications->notify(
            recipients: $booking->family,
            eventKey: MarketplaceEvent::PAYMENT_ACTION_REQUIRED,
            title: 'Payment action needed for a visit correction',
            body: 'Support approved corrected visit hours, but your saved card needs attention before the adjustment can finish.',
            url: route('family.billing.show', ['correction' => $correction->client_request_id]),
            payload: [
                'care_booking_id' => $booking->id,
                'care_booking_correction_id' => $correction->id,
                'support_ticket_id' => $correction->support_ticket_id,
            ],
            subject: $booking,
            dedupeKey: 'booking-correction-'.$correction->id.':payment-action:'.$correction->attempt_count,
        );
    }

    private function notifyCorrectionSucceeded(CareBookingCorrection $correction): void
    {
        $booking = $correction->booking()->with(['family', 'caregiver'])->firstOrFail();
        $delta = (int) $correction->payment_delta_cents;
        if ($booking->family) {
            $this->notifications->notify(
                recipients: $booking->family,
                eventKey: $delta < 0 ? MarketplaceEvent::PAYMENT_REFUNDED : MarketplaceEvent::PAYMENT_CAPTURED,
                title: 'Visit correction completed',
                body: 'Support corrected the approved hours for booking #'.$booking->id.'. The payment record is now up to date.',
                url: route('family.requests.show', $booking->care_request_id),
                payload: [
                    'care_booking_id' => $booking->id,
                    'care_booking_correction_id' => $correction->id,
                    'payment_delta_cents' => $delta,
                ],
                subject: $booking,
                dedupeKey: 'booking-correction-'.$correction->id.':family-success',
            );
        }

        if ($booking->caregiver) {
            $this->notifications->notify(
                recipients: $booking->caregiver,
                eventKey: $delta < 0 ? MarketplaceEvent::PAYMENT_REFUNDED : MarketplaceEvent::PAYOUT_TRANSFERRED,
                title: 'Visit and payout correction completed',
                body: 'Support corrected the approved hours for booking #'.$booking->id.'. Your payout record is now up to date.',
                url: route('care-requests.apply', $booking->care_request_id),
                payload: [
                    'care_booking_id' => $booking->id,
                    'care_booking_correction_id' => $correction->id,
                    'caregiver_delta_cents' => (int) $correction->caregiver_delta_cents,
                ],
                subject: $booking,
                dedupeKey: 'booking-correction-'.$correction->id.':caregiver-success',
            );
        }
    }
}
