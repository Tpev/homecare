<?php

namespace App\Services\Booking;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareBookingCorrection;
use App\Models\CareBookingPayment;
use App\Models\CareBookingTimeCorrection;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Services\Payments\BookingPaymentService;
use App\Services\RegularCare\CarePlanHealthService;
use App\Support\MarketplaceEvent;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CareBookingTimeCorrectionService
{
    public function __construct(
        private readonly BookingPaymentService $payments,
        private readonly BookingTrustService $trust,
        private readonly MarketplaceNotificationService $notifications,
        private readonly CarePlanHealthService $planHealth,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('marketplace.time_corrections.enabled', false);
    }

    public function timezoneFor(CareBooking $booking): string
    {
        $booking->loadMissing('carePlan');
        $candidate = trim((string) ($booking->carePlan?->timezone ?: config('app.timezone', 'America/New_York')));

        try {
            new DateTimeZone($candidate);

            return $candidate;
        } catch (Throwable) {
            return (string) config('app.timezone', 'America/New_York');
        }
    }

    public function latestForBooking(CareBooking $booking): ?CareBookingTimeCorrection
    {
        return CareBookingTimeCorrection::query()
            ->with(['requester:id,name', 'approvedBy:id,name', 'supportTicket:id,status'])
            ->where('care_booking_id', $booking->id)
            ->latest('version')
            ->first();
    }

    public function activeForBooking(CareBooking $booking): ?CareBookingTimeCorrection
    {
        return CareBookingTimeCorrection::query()
            ->active()
            ->where('care_booking_id', $booking->id)
            ->latest('version')
            ->first();
    }

    /** @return array<string, mixed> */
    public function preview(CareBooking $booking, array $input): array
    {
        $this->assertEnabled();
        $booking->loadMissing(['carePlan', 'application', 'family', 'caregiver.caregiverProfile', 'payment']);

        $timezone = $this->timezoneFor($booking);
        $startedAt = $this->parseLocalDate($input['started_at'] ?? null, $timezone, 'timeCorrectionStartedAt', 'Enter the actual start time.');
        $completedAt = $this->parseLocalDate($input['completed_at'] ?? null, $timezone, 'timeCorrectionCompletedAt', 'Enter the actual end time.');
        $breakMinutes = max(0, (int) ($input['break_minutes'] ?? 0));

        $this->validateTimeRange($booking, $startedAt, $completedAt, $breakMinutes);
        $workedMinutes = $startedAt->diffInMinutes($completedAt) - $breakMinutes;
        $quote = $this->payments->quoteForWorkedMinutes($booking, $workedMinutes);

        return [
            'timezone' => $timezone,
            'started_at' => $startedAt->toIso8601String(),
            'completed_at' => $completedAt->toIso8601String(),
            'break_minutes' => $breakMinutes,
            'worked_minutes' => $workedMinutes,
            'worked_label' => $this->durationLabel($workedMinutes),
            'hourly_rate' => (float) $quote['hourly_rate'],
            'subtotal_cents' => (int) $quote['subtotal_cents'],
            'platform_fee_percent' => (float) $quote['platform_fee_percent'],
            'target_charge_cents' => (int) $quote['total_charge_cents'],
            'caregiver_amount_cents' => (int) $quote['caregiver_amount_cents'],
            'target_charge_label' => $this->moneyLabel((int) $quote['total_charge_cents']),
            'caregiver_amount_label' => $this->moneyLabel((int) $quote['caregiver_amount_cents']),
            'admin_required' => $this->requiresAdminApplication($booking),
        ];
    }

    public function submit(
        CareBooking $booking,
        User $caregiver,
        array $input,
        string $clientRequestId,
        ?int $supersedesId = null,
    ): CareBookingTimeCorrection {
        $this->assertEnabled();
        $this->assertCaregiver($booking, $caregiver);
        $this->assertBookingAcceptsProposal($booking);

        if (! Str::isUuid($clientRequestId)) {
            throw ValidationException::withMessages(['timeCorrectionClientRequestId' => 'Invalid correction request. Refresh and try again.']);
        }

        $existing = CareBookingTimeCorrection::query()->where('client_request_id', $clientRequestId)->first();
        if ($existing) {
            if ((int) $existing->care_booking_id !== (int) $booking->id || (int) $existing->requester_user_id !== (int) $caregiver->id) {
                throw new AuthorizationException;
            }

            return $existing;
        }

        $reason = (string) ($input['reason_code'] ?? '');
        $explanation = trim((string) ($input['explanation'] ?? ''));
        if (! in_array($reason, CareBookingTimeCorrection::reasonCodes(), true)) {
            throw ValidationException::withMessages(['timeCorrectionReason' => 'Choose what happened.']);
        }
        if (mb_strlen($explanation) < 8 || mb_strlen($explanation) > 2000) {
            throw ValidationException::withMessages(['timeCorrectionExplanation' => 'Explain what happened in 8 to 2,000 characters.']);
        }
        if (! filter_var($input['confirmed'] ?? false, FILTER_VALIDATE_BOOL)) {
            throw ValidationException::withMessages(['timeCorrectionConfirmed' => 'Confirm that these are the hours you actually provided care.']);
        }

        $preview = $this->preview($booking, $input);

        $correction = DB::transaction(function () use ($booking, $caregiver, $clientRequestId, $supersedesId, $reason, $explanation, $preview): CareBookingTimeCorrection {
            $lockedBooking = CareBooking::query()->lockForUpdate()->findOrFail($booking->id);
            $active = CareBookingTimeCorrection::query()
                ->where('care_booking_id', $lockedBooking->id)
                ->active()
                ->lockForUpdate()
                ->latest('version')
                ->first();

            if ($active && ($active->status !== CareBookingTimeCorrection::STATUS_CHANGES_REQUESTED
                || (int) $active->id !== (int) $supersedesId)) {
                throw ValidationException::withMessages([
                    'timeCorrectionSubmit' => 'This visit already has a time correction in progress.',
                ]);
            }

            if ($supersedesId && (! $active || (int) $active->id !== (int) $supersedesId)) {
                throw ValidationException::withMessages([
                    'timeCorrectionSubmit' => 'This correction changed while you were editing it. Refresh and try again.',
                ]);
            }

            $version = ((int) CareBookingTimeCorrection::query()
                ->where('care_booking_id', $lockedBooking->id)
                ->max('version')) + 1;

            if ($active) {
                $active->forceFill(['status' => CareBookingTimeCorrection::STATUS_SUPERSEDED])->save();
            }

            return CareBookingTimeCorrection::query()->create([
                'client_request_id' => $clientRequestId,
                'care_booking_id' => $lockedBooking->id,
                'requester_user_id' => $caregiver->id,
                'family_account_id' => $lockedBooking->family_account_id,
                'family_user_id' => $lockedBooking->family_user_id,
                'version' => $version,
                'supersedes_id' => $active?->id,
                'status' => CareBookingTimeCorrection::STATUS_PENDING_FAMILY,
                'reason_code' => $reason,
                'explanation' => $explanation,
                'proposed_started_at' => Carbon::parse((string) $preview['started_at']),
                'proposed_completed_at' => Carbon::parse((string) $preview['completed_at']),
                'proposed_break_minutes' => (int) $preview['break_minutes'],
                'proposed_worked_minutes' => (int) $preview['worked_minutes'],
                'original_snapshot' => $this->snapshot($lockedBooking),
                'financial_preview' => $preview,
                'submitted_at' => now(),
            ]);
        });

        $this->trust->recordEvent($booking, $caregiver->id, 'caregiver', $correction->version > 1
            ? 'time_correction_resubmitted_by_caregiver'
            : 'time_correction_requested_by_caregiver', [
                'time_correction_id' => $correction->id,
                'version' => $correction->version,
                'reason_code' => $correction->reason_code,
                'proposed_worked_minutes' => $correction->proposed_worked_minutes,
            ]);
        $this->notifySubmitted($correction, $correction->version > 1);

        return $correction->fresh(['requester:id,name']);
    }

    public function requestChanges(CareBookingTimeCorrection $correction, User $family, string $note): CareBookingTimeCorrection
    {
        $this->assertEnabled();
        $this->assertFamily($correction, $family);
        $note = trim($note);
        if (mb_strlen($note) < 8 || mb_strlen($note) > 2000) {
            throw ValidationException::withMessages([
                'timeCorrectionResponseNote' => 'Tell the caregiver what should change in 8 to 2,000 characters.',
            ]);
        }

        $updated = DB::transaction(function () use ($correction, $note): CareBookingTimeCorrection {
            $locked = CareBookingTimeCorrection::query()->lockForUpdate()->findOrFail($correction->id);
            $this->assertLatestVersion($locked);
            if ($locked->status !== CareBookingTimeCorrection::STATUS_PENDING_FAMILY) {
                throw ValidationException::withMessages(['timeCorrectionResponse' => 'This correction is no longer waiting for your response.']);
            }

            $locked->forceFill([
                'status' => CareBookingTimeCorrection::STATUS_CHANGES_REQUESTED,
                'family_response_note' => $note,
                'changes_requested_at' => now(),
            ])->save();

            return $locked;
        });

        $booking = $updated->booking()->firstOrFail();
        $this->trust->recordEvent($booking, $family->id, 'family', 'time_correction_changes_requested_by_family', [
            'time_correction_id' => $updated->id,
            'version' => $updated->version,
            'note' => $note,
        ]);
        $this->notifyCaregiver(
            $updated,
            MarketplaceEvent::TIME_CORRECTION_CHANGES_REQUESTED,
            'Please update your visit time',
            $family->name.' asked you to change the time correction for visit #'.$booking->id.'.',
            'time-correction-changes-requested'
        );

        return $updated->fresh();
    }

    public function approve(CareBookingTimeCorrection $correction, User $family): CareBookingTimeCorrection
    {
        $this->assertEnabled();
        $this->assertFamily($correction, $family);

        $result = DB::transaction(function () use ($correction, $family): array {
            $locked = CareBookingTimeCorrection::query()->lockForUpdate()->findOrFail($correction->id);
            $this->assertLatestVersion($locked);
            if ($locked->status !== CareBookingTimeCorrection::STATUS_PENDING_FAMILY) {
                throw ValidationException::withMessages(['timeCorrectionResponse' => 'This correction is no longer waiting for approval.']);
            }

            $booking = CareBooking::query()
                ->with(['carePlan', 'application', 'family', 'caregiver.caregiverProfile', 'payment'])
                ->lockForUpdate()
                ->findOrFail($locked->care_booking_id);
            $this->assertFamilyOwnsBooking($booking, $family);
            $this->assertBookingCanBeApproved($booking);
            $this->assertFinancialPreviewCurrent($booking, $locked);

            $adminRequired = $this->requiresAdminApplication($booking);
            $locked->forceFill([
                'status' => $adminRequired
                    ? CareBookingTimeCorrection::STATUS_APPROVED_ADMIN_REQUIRED
                    : CareBookingTimeCorrection::STATUS_APPROVED_PROCESSING,
                'approved_by_user_id' => $family->id,
                'approved_at' => now(),
                'processing_started_at' => $adminRequired ? null : now(),
                'processing_attempts' => $adminRequired ? 0 : 1,
                'last_error' => null,
            ])->save();

            if ($adminRequired) {
                $ticket = $this->createSupportTicket($locked, 'The family approved corrected hours for a visit whose payment or review is already finalized.');
                $locked->forceFill(['support_ticket_id' => $ticket->id])->save();
            } else {
                $this->applyBookingAndCreateLedger($booking, $locked, $family);
            }

            return [$locked->fresh(), $adminRequired];
        });

        /** @var CareBookingTimeCorrection $approved */
        [$approved, $adminRequired] = $result;
        $booking = $approved->booking()->with(['family', 'caregiver'])->firstOrFail();
        $this->trust->recordEvent($booking, $family->id, 'family', 'time_correction_approved_by_family', [
            'time_correction_id' => $approved->id,
            'version' => $approved->version,
            'admin_required' => $adminRequired,
        ]);

        if ($adminRequired) {
            $this->notifyBoth(
                $approved,
                MarketplaceEvent::TIME_CORRECTION_ESCALATED,
                'Time correction sent to LoLo Care',
                'The corrected hours were approved. LoLo Care will review the existing payment before updating this visit.',
                'time-correction-approved-admin-required'
            );

            return $approved->fresh(['supportTicket']);
        }

        $this->notifyCaregiver(
            $approved,
            MarketplaceEvent::TIME_CORRECTION_APPROVED,
            'Your corrected hours were approved',
            $family->name.' approved '.$this->durationLabel($approved->proposed_worked_minutes).' for visit #'.$booking->id.'.',
            'time-correction-approved'
        );

        return $this->finalizeApprovedSafely($approved->fresh());
    }

    public function resumeApproved(CareBookingTimeCorrection $correction, User $family): CareBookingTimeCorrection
    {
        $this->assertEnabled();
        $this->assertFamily($correction, $family);

        $correction = DB::transaction(function () use ($correction): CareBookingTimeCorrection {
            $locked = CareBookingTimeCorrection::query()->lockForUpdate()->findOrFail($correction->id);
            if ($locked->status !== CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED) {
                return $locked;
            }
            $locked->forceFill([
                'status' => CareBookingTimeCorrection::STATUS_APPROVED_PROCESSING,
                'processing_started_at' => now(),
                'processing_attempts' => (int) $locked->processing_attempts + 1,
                'last_error' => null,
            ])->save();

            return $locked;
        });

        return $this->finalizeApprovedSafely($correction);
    }

    public function retryApprovedProcessing(CareBookingTimeCorrection $correction): CareBookingTimeCorrection
    {
        $this->assertEnabled();

        $correction = DB::transaction(function () use ($correction): CareBookingTimeCorrection {
            $locked = CareBookingTimeCorrection::query()->lockForUpdate()->findOrFail($correction->id);
            if ($locked->status !== CareBookingTimeCorrection::STATUS_APPROVED_PROCESSING) {
                return $locked;
            }

            $locked->forceFill([
                'processing_started_at' => now(),
                'processing_attempts' => (int) $locked->processing_attempts + 1,
                'last_error' => null,
            ])->save();

            return $locked;
        });

        return $this->finalizeApprovedSafely($correction);
    }

    public function completeAdminApplication(
        CareBookingTimeCorrection $correction,
        CareBookingCorrection $ledger,
        User $admin,
    ): CareBookingTimeCorrection {
        if (! $admin->isAdministrator()
            || (int) $ledger->time_correction_request_id !== (int) $correction->id
            || ! $ledger->succeeded()) {
            throw new AuthorizationException;
        }

        $updated = DB::transaction(function () use ($correction): CareBookingTimeCorrection {
            $locked = CareBookingTimeCorrection::query()->lockForUpdate()->findOrFail($correction->id);
            if ($locked->status === CareBookingTimeCorrection::STATUS_APPLIED) {
                return $locked;
            }
            if (! in_array($locked->status, [
                CareBookingTimeCorrection::STATUS_APPROVED_ADMIN_REQUIRED,
                CareBookingTimeCorrection::STATUS_ESCALATED,
            ], true)) {
                throw ValidationException::withMessages([
                    'correctionApply' => 'This collaboration request is not ready for admin application.',
                ]);
            }

            $locked->forceFill([
                'status' => CareBookingTimeCorrection::STATUS_APPLIED,
                'finalized_at' => now(),
                'last_error' => null,
            ])->save();

            return $locked;
        });

        $booking = $updated->booking()->firstOrFail();
        $this->trust->recordEvent($booking, $admin->id, 'admin', 'time_correction_applied_by_admin', [
            'time_correction_id' => $updated->id,
            'booking_correction_id' => $ledger->id,
            'version' => $updated->version,
        ]);
        if ($booking->care_plan_id) {
            $this->planHealth->reconcileForBooking($booking);
        }
        $this->notifyBoth(
            $updated,
            MarketplaceEvent::TIME_CORRECTION_APPLIED,
            'Visit time updated',
            'LoLo Care finalized the corrected time for visit #'.$booking->id.'.',
            'time-correction-admin-applied'
        );

        return $updated->fresh(['appliedCorrection']);
    }

    public function withdraw(CareBookingTimeCorrection $correction, User $caregiver): CareBookingTimeCorrection
    {
        $this->assertEnabled();
        $booking = $correction->booking()->firstOrFail();
        $this->assertCaregiver($booking, $caregiver);

        $updated = DB::transaction(function () use ($correction): CareBookingTimeCorrection {
            $locked = CareBookingTimeCorrection::query()->lockForUpdate()->findOrFail($correction->id);
            $this->assertLatestVersion($locked);
            if (! in_array($locked->status, [
                CareBookingTimeCorrection::STATUS_PENDING_FAMILY,
                CareBookingTimeCorrection::STATUS_CHANGES_REQUESTED,
            ], true)) {
                throw ValidationException::withMessages(['timeCorrectionWithdraw' => 'This correction can no longer be withdrawn.']);
            }
            $locked->forceFill([
                'status' => CareBookingTimeCorrection::STATUS_WITHDRAWN,
                'withdrawn_at' => now(),
            ])->save();

            return $locked;
        });

        $this->notifyFamily(
            $updated,
            MarketplaceEvent::TIME_CORRECTION_WITHDRAWN,
            'Time correction withdrawn',
            $caregiver->name.' withdrew the time correction for visit #'.$booking->id.'.',
            'time-correction-withdrawn'
        );

        return $updated;
    }

    public function escalate(CareBookingTimeCorrection $correction, User|string|null $actor = null, ?string $reason = null): CareBookingTimeCorrection
    {
        $this->assertEnabled();
        $booking = $correction->booking()->firstOrFail();
        if ($actor instanceof User
            && ! ($actor->role === 'family' && app(FamilyAccountContext::class)->canAccessRecord($actor, $booking))
            && (int) $actor->id !== (int) $booking->caregiver_user_id
            && ! $actor->isAdministrator()) {
            throw new AuthorizationException;
        }

        $actorLabel = $actor instanceof User ? $actor->name : (is_string($actor) ? $actor : 'LoLo Care automation');
        $message = trim((string) $reason) ?: 'The time correction needs LoLo Care review.';

        $updated = DB::transaction(function () use ($correction, $message): CareBookingTimeCorrection {
            $locked = CareBookingTimeCorrection::query()->lockForUpdate()->findOrFail($correction->id);
            if (in_array($locked->status, [
                CareBookingTimeCorrection::STATUS_APPLIED,
                CareBookingTimeCorrection::STATUS_WITHDRAWN,
                CareBookingTimeCorrection::STATUS_SUPERSEDED,
            ], true)) {
                return $locked;
            }

            $ticket = $locked->support_ticket_id
                ? SupportTicket::query()->find($locked->support_ticket_id)
                : $this->createSupportTicket($locked, $message);

            $locked->forceFill([
                'status' => CareBookingTimeCorrection::STATUS_ESCALATED,
                'support_ticket_id' => $ticket?->id,
                'escalated_at' => $locked->escalated_at ?: now(),
                'last_error' => $message,
            ])->save();

            return $locked;
        });

        if ($updated->status === CareBookingTimeCorrection::STATUS_ESCALATED) {
            $this->trust->recordEvent($booking, $actor instanceof User ? $actor->id : null, $actor instanceof User ? $actor->role : 'system', 'time_correction_escalated', [
                'time_correction_id' => $updated->id,
                'actor' => $actorLabel,
                'reason' => $message,
            ]);
            $this->notifyBoth(
                $updated,
                MarketplaceEvent::TIME_CORRECTION_ESCALATED,
                'LoLo Care is reviewing the visit time',
                'The time correction for visit #'.$booking->id.' was sent to LoLo Care support.',
                'time-correction-escalated'
            );
        }

        return $updated->fresh(['supportTicket']);
    }

    public function sendReminder(CareBookingTimeCorrection $correction, bool $second): CareBookingTimeCorrection
    {
        $correction->refresh();
        if (! in_array($correction->status, [
            CareBookingTimeCorrection::STATUS_PENDING_FAMILY,
            CareBookingTimeCorrection::STATUS_CHANGES_REQUESTED,
        ], true)) {
            return $correction;
        }

        $field = $second ? 'second_reminded_at' : 'first_reminded_at';
        if ($correction->{$field}) {
            return $correction;
        }

        $correction->forceFill([$field => now()])->save();
        if ($correction->status === CareBookingTimeCorrection::STATUS_PENDING_FAMILY) {
            $this->notifyFamily(
                $correction,
                MarketplaceEvent::TIME_CORRECTION_REQUESTED,
                'Visit time waiting for your review',
                'Review the corrected hours for visit #'.$correction->care_booking_id.'.',
                $second ? 'time-correction-second-reminder' : 'time-correction-first-reminder'
            );
        } else {
            $this->notifyCaregiver(
                $correction,
                MarketplaceEvent::TIME_CORRECTION_CHANGES_REQUESTED,
                'Your visit time needs an update',
                'The family is waiting for an updated time correction for visit #'.$correction->care_booking_id.'.',
                $second ? 'time-correction-second-reminder' : 'time-correction-first-reminder'
            );
        }

        return $correction->fresh();
    }

    private function finalizeApproved(CareBookingTimeCorrection $correction): CareBookingTimeCorrection
    {
        if ($correction->status !== CareBookingTimeCorrection::STATUS_APPROVED_PROCESSING) {
            return $correction;
        }

        $booking = $correction->booking()->with(['payment', 'family', 'caregiver.caregiverProfile', 'application', 'carePlan'])->firstOrFail();
        $target = (int) data_get($correction->financial_preview, 'target_charge_cents', 0);
        $payment = $booking->payment;

        try {
            $authorizationInsufficient = ! $payment
                || $payment->status !== CareBookingPayment::STATUS_AUTHORIZED
                || (int) $payment->amount_authorized_cents < $target
                || ($payment->authorization_expires_at && $payment->authorization_expires_at->lte(now()->addMinutes(5)));

            if ($authorizationInsufficient) {
                $payment = $this->payments->prepareOnSessionAuthorization($booking, true);
            }

            if ($payment->status !== CareBookingPayment::STATUS_AUTHORIZED) {
                return $this->markPaymentActionRequired($correction, $payment->last_error ?: 'Confirm the card authorization to finish the approved correction.');
            }

            $payment = $this->payments->captureForBooking($booking->fresh(['payment', 'family', 'caregiver.caregiverProfile', 'application']));
            if ((int) $payment->overage_pending_cents > 0) {
                return $this->markPaymentActionRequired($correction, 'Confirm the additional visit amount to finish payment.');
            }
        } catch (PaymentException $exception) {
            return $this->markPaymentActionRequired($correction, $exception->userMessage);
        }

        $completed = DB::transaction(function () use ($correction): CareBookingTimeCorrection {
            $locked = CareBookingTimeCorrection::query()->lockForUpdate()->findOrFail($correction->id);
            if ($locked->status === CareBookingTimeCorrection::STATUS_APPLIED) {
                return $locked;
            }

            $ledger = CareBookingCorrection::query()
                ->where('time_correction_request_id', $locked->id)
                ->firstOrFail();
            $booking = CareBooking::query()->with('payment')->findOrFail($locked->care_booking_id);

            $ledger->forceFill([
                'status' => CareBookingCorrection::STATUS_SUCCEEDED,
                'after_snapshot' => $this->snapshot($booking),
                'booking_applied_at' => $ledger->booking_applied_at ?: now(),
                'payout_applied_at' => $booking->payment?->transferred_at,
                'applied_at' => now(),
                'last_error' => null,
            ])->save();
            $locked->forceFill([
                'status' => CareBookingTimeCorrection::STATUS_APPLIED,
                'finalized_at' => now(),
                'last_error' => null,
            ])->save();

            return $locked;
        });

        $booking = $completed->booking()->firstOrFail();
        $this->trust->recordEvent($booking, $completed->approved_by_user_id, 'family', 'time_correction_applied', [
            'time_correction_id' => $completed->id,
            'version' => $completed->version,
            'worked_minutes' => $completed->proposed_worked_minutes,
        ]);
        $this->trust->recomputeReliabilityForBooking($booking);
        if ($booking->care_plan_id) {
            $this->planHealth->reconcileForBooking($booking);
        }
        $this->notifyBoth(
            $completed,
            MarketplaceEvent::TIME_CORRECTION_APPLIED,
            'Visit time updated',
            'The approved time for visit #'.$booking->id.' is now finalized at '.$this->durationLabel($completed->proposed_worked_minutes).'.',
            'time-correction-applied'
        );

        return $completed->fresh(['appliedCorrection', 'booking.payment']);
    }

    private function finalizeApprovedSafely(CareBookingTimeCorrection $correction): CareBookingTimeCorrection
    {
        try {
            return $this->finalizeApproved($correction);
        } catch (Throwable $exception) {
            report($exception);

            CareBookingTimeCorrection::query()
                ->whereKey($correction->id)
                ->where('status', CareBookingTimeCorrection::STATUS_APPROVED_PROCESSING)
                ->update([
                    'last_error' => 'Automatic finalization paused. LoLo Care will retry this approved correction safely.',
                    'updated_at' => now(),
                ]);

            return $correction->fresh(['booking.payment']);
        }
    }

    private function markPaymentActionRequired(CareBookingTimeCorrection $correction, string $message): CareBookingTimeCorrection
    {
        $updated = DB::transaction(function () use ($correction, $message): CareBookingTimeCorrection {
            $locked = CareBookingTimeCorrection::query()->lockForUpdate()->findOrFail($correction->id);
            $ledger = CareBookingCorrection::query()->where('time_correction_request_id', $locked->id)->first();
            $ledger?->forceFill([
                'status' => CareBookingCorrection::STATUS_REQUIRES_ACTION,
                'attempt_count' => max(1, (int) $ledger->attempt_count),
                'last_error' => $message,
            ])->save();
            $locked->forceFill([
                'status' => CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED,
                'payment_action_required_at' => now(),
                'last_error' => $message,
            ])->save();

            return $locked;
        });

        $this->notifyFamily(
            $updated,
            MarketplaceEvent::TIME_CORRECTION_PAYMENT_ACTION_REQUIRED,
            'Payment confirmation needed',
            'The visit hours are approved. Confirm billing to finish visit #'.$updated->care_booking_id.'.',
            'time-correction-payment-action'
        );

        return $updated->fresh(['booking.payment']);
    }

    private function applyBookingAndCreateLedger(CareBooking $booking, CareBookingTimeCorrection $correction, User $family): void
    {
        $before = $this->snapshot($booking);
        $startedChanged = ! $booking->started_at || ! $booking->started_at->equalTo($correction->proposed_started_at);
        $completedChanged = ! $booking->completed_at || ! $booking->completed_at->equalTo($correction->proposed_completed_at);

        $booking->forceFill([
            'status' => CareBooking::STATUS_COMPLETED,
            'started_at' => $correction->proposed_started_at,
            'completed_at' => $correction->proposed_completed_at,
            'paused_at' => null,
            'total_paused_seconds' => $correction->proposed_break_minutes * 60,
            'worked_minutes' => $correction->proposed_worked_minutes,
            'timesheet_submitted_at' => $booking->timesheet_submitted_at ?: $correction->submitted_at,
            'family_confirmed_at' => $correction->approved_at ?: now(),
            'check_in_source' => $startedChanged ? 'family_approved_manual' : $booking->check_in_source,
            'check_in_note' => $startedChanged
                ? $this->appendCorrectionNote($booking->check_in_note, $correction)
                : $booking->check_in_note,
            'check_out_source' => $completedChanged ? 'family_approved_manual' : $booking->check_out_source,
            'check_out_note' => $completedChanged
                ? $this->appendCorrectionNote($booking->check_out_note, $correction)
                : $booking->check_out_note,
        ])->save();

        $preview = (array) $correction->financial_preview;
        CareBookingCorrection::query()->create([
            'client_request_id' => $correction->client_request_id,
            'care_booking_id' => $booking->id,
            'support_ticket_id' => null,
            'actor_admin_user_id' => null,
            'source' => 'family_approved_time',
            'time_correction_request_id' => $correction->id,
            'requester_user_id' => $correction->requester_user_id,
            'approved_by_user_id' => $family->id,
            'action' => CareBookingCorrection::ACTION_COMPLETE_AND_BILL,
            'status' => CareBookingCorrection::STATUS_PROCESSING,
            'attempt_count' => 1,
            'previous_charge_cents' => (int) data_get($before, 'payment.net_captured_cents', 0),
            'target_charge_cents' => (int) ($preview['target_charge_cents'] ?? 0),
            'payment_delta_cents' => (int) ($preview['target_charge_cents'] ?? 0) - (int) data_get($before, 'payment.net_captured_cents', 0),
            'caregiver_delta_cents' => (int) ($preview['caregiver_amount_cents'] ?? 0) - (int) data_get($before, 'payment.caregiver_amount_cents', 0),
            'family_approval_confirmed_at' => $correction->approved_at ?: now(),
            'reason' => $correction->explanation,
            'before_snapshot' => $before,
            'requested_changes' => [
                'action' => CareBookingCorrection::ACTION_COMPLETE_AND_BILL,
                'started_at' => $correction->proposed_started_at->toIso8601String(),
                'completed_at' => $correction->proposed_completed_at->toIso8601String(),
                'break_minutes' => $correction->proposed_break_minutes,
                'family_approved' => true,
                'reason_code' => $correction->reason_code,
                'version' => $correction->version,
            ],
            'preview' => array_merge($preview, ['action' => CareBookingCorrection::ACTION_COMPLETE_AND_BILL]),
            'provider_payload' => [],
            'internal_note_client_id' => (string) Str::uuid(),
            'public_reply_client_id' => (string) Str::uuid(),
            'booking_applied_at' => now(),
        ]);
    }

    private function assertFinancialPreviewCurrent(CareBooking $booking, CareBookingTimeCorrection $correction): void
    {
        $quote = $this->payments->quoteForWorkedMinutes($booking, $correction->proposed_worked_minutes);
        $preview = (array) $correction->financial_preview;
        if ((int) $quote['total_charge_cents'] !== (int) data_get($preview, 'target_charge_cents')
            || (int) $quote['caregiver_amount_cents'] !== (int) data_get($preview, 'caregiver_amount_cents')
            || round((float) $quote['hourly_rate'], 2) !== round((float) data_get($preview, 'hourly_rate'), 2)
            || round((float) $quote['platform_fee_percent'], 4) !== round((float) data_get($preview, 'platform_fee_percent'), 4)) {
            throw ValidationException::withMessages([
                'timeCorrectionResponse' => 'The visit price changed. Ask the caregiver to resubmit so you can review the current total.',
            ]);
        }
    }

    private function validateTimeRange(CareBooking $booking, Carbon $startedAt, Carbon $completedAt, int $breakMinutes): void
    {
        if ($completedAt->lessThanOrEqualTo($startedAt)) {
            throw ValidationException::withMessages(['timeCorrectionCompletedAt' => 'The actual end time must be after the start time.']);
        }

        $futureLimit = now()->addMinutes(max(0, (int) config('marketplace.time_corrections.future_clock_skew_minutes', 5)));
        if ($completedAt->gt($futureLimit)) {
            throw ValidationException::withMessages(['timeCorrectionCompletedAt' => 'The actual end time cannot be in the future.']);
        }

        $elapsed = $startedAt->diffInMinutes($completedAt);
        if ($breakMinutes >= $elapsed) {
            throw ValidationException::withMessages(['timeCorrectionBreakMinutes' => 'Break time must be shorter than the visit.']);
        }
        if (($elapsed - $breakMinutes) > max(1, (int) config('marketplace.time_corrections.max_duration_minutes', 960))) {
            throw ValidationException::withMessages(['timeCorrectionCompletedAt' => 'The requested visit is longer than the allowed correction window. Contact LoLo Care for help.']);
        }

        if ($booking->scheduled_start_at && $booking->scheduled_end_at) {
            $reasonableStart = $booking->scheduled_start_at->copy()->subHours(12);
            $reasonableEnd = $booking->scheduled_end_at->copy()->addHours(12);
            if ($startedAt->lt($reasonableStart) || $completedAt->gt($reasonableEnd)) {
                throw ValidationException::withMessages([
                    'timeCorrectionStartedAt' => 'The requested time is too far from this visit. Contact LoLo Care if the visit happened on another day.',
                ]);
            }
        }

        $overlap = CareBooking::query()
            ->where('caregiver_user_id', $booking->caregiver_user_id)
            ->whereKeyNot($booking->id)
            ->where('status', '!=', CareBooking::STATUS_CANCELLED)
            ->where(function ($query) use ($startedAt, $completedAt): void {
                $query
                    ->where(function ($recorded) use ($startedAt, $completedAt): void {
                        $recorded
                            ->whereNotNull('started_at')
                            ->whereNotNull('completed_at')
                            ->where('started_at', '<', $completedAt)
                            ->where('completed_at', '>', $startedAt);
                    })
                    ->orWhere(function ($scheduled) use ($startedAt, $completedAt): void {
                        $scheduled
                            ->where(function ($missingRecorded): void {
                                $missingRecorded->whereNull('started_at')->orWhereNull('completed_at');
                            })
                            ->where('scheduled_start_at', '<', $completedAt)
                            ->where('scheduled_end_at', '>', $startedAt);
                    });
            })
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages([
                'timeCorrectionStartedAt' => 'These hours overlap another scheduled visit. Contact LoLo Care if both records need correction.',
            ]);
        }
    }

    private function assertBookingAcceptsProposal(CareBooking $booking): void
    {
        if ($booking->no_show_flag || in_array($booking->status, [CareBooking::STATUS_CANCELLED, CareBooking::STATUS_DISPUTED], true)) {
            throw ValidationException::withMessages(['timeCorrectionSubmit' => 'This visit needs LoLo Care support before its hours can be changed.']);
        }
        if ($booking->status === CareBooking::STATUS_SCHEDULED
            && $booking->scheduled_start_at
            && now()->lt($booking->scheduled_start_at)) {
            throw ValidationException::withMessages(['timeCorrectionSubmit' => 'You can fix visit time after the scheduled visit begins.']);
        }
        if (! in_array($booking->status, [
            CareBooking::STATUS_SCHEDULED,
            CareBooking::STATUS_IN_PROGRESS,
            CareBooking::STATUS_PAUSED,
            CareBooking::STATUS_COMPLETED,
            CareBooking::STATUS_REVIEWED,
        ], true)) {
            throw ValidationException::withMessages(['timeCorrectionSubmit' => 'This visit is not eligible for a time correction.']);
        }
    }

    private function assertBookingCanBeApproved(CareBooking $booking): void
    {
        if ($booking->no_show_flag || in_array($booking->status, [CareBooking::STATUS_CANCELLED, CareBooking::STATUS_DISPUTED], true)) {
            throw ValidationException::withMessages(['timeCorrectionResponse' => 'This visit now needs LoLo Care review.']);
        }
    }

    private function requiresAdminApplication(CareBooking $booking): bool
    {
        $booking->loadMissing('payment');
        if ($booking->family_confirmed_at) {
            return true;
        }
        if ($booking->payment && in_array($booking->payment->status, [
            CareBookingPayment::STATUS_CAPTURED,
            CareBookingPayment::STATUS_TRANSFERRED,
            CareBookingPayment::STATUS_TRANSFER_FAILED,
            CareBookingPayment::STATUS_PARTIALLY_REFUNDED,
            CareBookingPayment::STATUS_REFUNDED,
        ], true)) {
            return true;
        }

        $reference = $booking->scheduled_end_at ?: $booking->scheduled_start_at;
        $window = max(1, (int) config('marketplace.time_corrections.self_service_window_hours', 72));

        return $reference ? now()->gt($reference->copy()->addHours($window)) : false;
    }

    private function assertLatestVersion(CareBookingTimeCorrection $correction): void
    {
        $latestId = CareBookingTimeCorrection::query()
            ->where('care_booking_id', $correction->care_booking_id)
            ->orderByDesc('version')
            ->value('id');
        if ((int) $latestId !== (int) $correction->id) {
            throw ValidationException::withMessages(['timeCorrectionResponse' => 'A newer correction is available. Refresh to review it.']);
        }
    }

    private function assertEnabled(): void
    {
        if (! $this->enabled()) {
            throw ValidationException::withMessages(['timeCorrectionSubmit' => 'Time corrections are not available yet. Contact LoLo Care for help.']);
        }
    }

    private function assertCaregiver(CareBooking $booking, User $caregiver): void
    {
        if ($caregiver->role !== 'caregiver' || (int) $booking->caregiver_user_id !== (int) $caregiver->id) {
            throw new AuthorizationException;
        }
    }

    private function assertFamily(CareBookingTimeCorrection $correction, User $family): void
    {
        if ($family->role !== 'family' || ! app(FamilyAccountContext::class)->canAccessRecord($family, $correction)) {
            throw new AuthorizationException;
        }
    }

    private function assertFamilyOwnsBooking(CareBooking $booking, User $family): void
    {
        if (! app(FamilyAccountContext::class)->canAccessRecord($family, $booking)) {
            throw new AuthorizationException;
        }
    }

    private function parseLocalDate(mixed $value, string $timezone, string $field, string $message): Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            throw ValidationException::withMessages([$field => $message]);
        }

        try {
            $localValue = trim($value);
            $parsed = Carbon::createFromFormat('Y-m-d\TH:i', $localValue, $timezone);
            if ($parsed->format('Y-m-d\TH:i') !== $localValue) {
                throw new \InvalidArgumentException('Local time does not exist in this timezone.');
            }

            return $parsed->setTimezone((string) config('app.timezone', 'America/New_York'));
        } catch (Throwable) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    private function createSupportTicket(CareBookingTimeCorrection $correction, string $reason): SupportTicket
    {
        if ($correction->support_ticket_id) {
            return SupportTicket::query()->findOrFail($correction->support_ticket_id);
        }

        $booking = $correction->booking()->with(['caregiver:id,name', 'family:id,name'])->firstOrFail();
        $history = CareBookingTimeCorrection::query()
            ->where('care_booking_id', $booking->id)
            ->orderBy('version')
            ->get()
            ->map(fn (CareBookingTimeCorrection $item): string => sprintf(
                'Version %d: %s to %s, %d break minutes, %s. Status: %s.',
                $item->version,
                $item->proposed_started_at?->toIso8601String(),
                $item->proposed_completed_at?->toIso8601String(),
                $item->proposed_break_minutes,
                $item->explanation,
                $item->status,
            ))
            ->implode("\n");

        return SupportTicket::query()->create([
            'family_account_id' => $booking->family_account_id,
            'family_visibility' => 'shared_care',
            'opener_user_id' => $correction->requester_user_id,
            'counterparty_user_id' => $booking->family_user_id,
            'care_request_id' => $booking->care_request_id,
            'care_booking_id' => $booking->id,
            'category' => 'time_correction',
            'priority' => 'normal',
            'subject' => 'Time correction for visit #'.$booking->id,
            'description' => $reason."\n\nCorrection history:\n".$history,
        ]);
    }

    /** @return array<string, mixed> */
    private function snapshot(CareBooking $booking): array
    {
        $booking->loadMissing('payment');
        $dates = ['scheduled_start_at', 'scheduled_end_at', 'started_at', 'completed_at', 'paused_at', 'timesheet_submitted_at', 'family_confirmed_at'];
        $fields = [
            'status', 'scheduled_start_at', 'scheduled_end_at', 'started_at', 'completed_at', 'paused_at',
            'total_paused_seconds', 'timesheet_submitted_at', 'expected_minutes', 'worked_minutes', 'family_confirmed_at',
            'check_in_lat', 'check_in_lng', 'check_in_accuracy_meters', 'check_in_source', 'check_in_note',
            'check_out_lat', 'check_out_lng', 'check_out_accuracy_meters', 'check_out_source', 'check_out_note',
        ];
        $bookingSnapshot = [];
        foreach ($fields as $field) {
            $value = $booking->{$field};
            $bookingSnapshot[$field] = in_array($field, $dates, true) ? $value?->toIso8601String() : $value;
        }

        $payment = $booking->payment;
        $captured = (int) ($payment?->amount_captured_cents ?? 0);
        $refunded = (int) ($payment?->amount_refunded_cents ?? 0);

        return [
            'booking' => $bookingSnapshot,
            'payment' => $payment ? [
                'id' => $payment->id,
                'status' => $payment->status,
                'amount_authorized_cents' => (int) $payment->amount_authorized_cents,
                'amount_captured_cents' => $captured,
                'amount_refunded_cents' => $refunded,
                'net_captured_cents' => max(0, $captured - $refunded),
                'caregiver_amount_cents' => (int) $payment->caregiver_amount_cents,
                'authorization_expires_at' => $payment->authorization_expires_at?->toIso8601String(),
                'stripe_payment_intent_id' => $payment->stripe_payment_intent_id,
                'stripe_transfer_id' => $payment->stripe_transfer_id,
            ] : null,
        ];
    }

    private function appendCorrectionNote(?string $existing, CareBookingTimeCorrection $correction): string
    {
        $note = 'Family-approved time correction #'.$correction->id.': '.$correction->explanation;

        return trim(collect([$existing, $note])->filter()->implode("\n"));
    }

    private function notifySubmitted(CareBookingTimeCorrection $correction, bool $resubmitted): void
    {
        $this->notifyFamily(
            $correction,
            $resubmitted ? MarketplaceEvent::TIME_CORRECTION_RESUBMITTED : MarketplaceEvent::TIME_CORRECTION_REQUESTED,
            $resubmitted ? 'Updated visit time ready to review' : 'Visit time ready to review',
            $correction->requester?->name.' requested '.$this->durationLabel($correction->proposed_worked_minutes).' for visit #'.$correction->care_booking_id.'.',
            $resubmitted ? 'time-correction-resubmitted' : 'time-correction-requested'
        );
    }

    private function notifyFamily(CareBookingTimeCorrection $correction, string $event, string $title, string $body, string $key): void
    {
        $correction->loadMissing(['family', 'booking']);
        if (! $correction->family) {
            return;
        }
        $this->notifications->notify(
            recipients: $correction->family,
            eventKey: $event,
            title: $title,
            body: $body,
            url: route('family.requests.show', ['careRequest' => $correction->booking->care_request_id, 'tab' => 'shift'])
                .'#time-correction-review-'.$correction->id,
            payload: ['care_booking_id' => $correction->care_booking_id, 'time_correction_id' => $correction->id, 'version' => $correction->version],
            subject: $correction,
            dedupeKey: $key.':correction-'.$correction->id.'-version-'.$correction->version.'-user-'.$correction->family->id,
        );
    }

    private function notifyCaregiver(CareBookingTimeCorrection $correction, string $event, string $title, string $body, string $key): void
    {
        $correction->loadMissing(['requester', 'booking']);
        if (! $correction->requester) {
            return;
        }
        $this->notifications->notify(
            recipients: $correction->requester,
            eventKey: $event,
            title: $title,
            body: $body,
            url: route('care-requests.apply', $correction->booking->care_request_id),
            payload: ['care_booking_id' => $correction->care_booking_id, 'time_correction_id' => $correction->id, 'version' => $correction->version],
            subject: $correction,
            dedupeKey: $key.':correction-'.$correction->id.'-version-'.$correction->version.'-user-'.$correction->requester->id,
        );
    }

    private function notifyBoth(CareBookingTimeCorrection $correction, string $event, string $title, string $body, string $key): void
    {
        $this->notifyFamily($correction, $event, $title, $body, $key);
        $this->notifyCaregiver($correction, $event, $title, $body, $key);
    }

    private function durationLabel(int $minutes): string
    {
        $hours = intdiv(max(0, $minutes), 60);
        $remaining = max(0, $minutes) % 60;

        return $hours > 0
            ? $hours.' hr'.($hours === 1 ? '' : 's').($remaining ? ' '.$remaining.' min' : '')
            : $remaining.' min';
    }

    private function moneyLabel(int $cents): string
    {
        return '$'.number_format(max(0, $cents) / 100, 2);
    }
}
