<?php

namespace App\Services\RegularCare;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CarePlan;
use App\Models\CarePlanEvent;
use App\Models\CompletedExtraVisitRequest;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Booking\BookingTrustService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Services\Payments\BookingPaymentService;
use App\Support\MarketplaceEvent;
use App\Support\MarketplacePricing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CompletedExtraVisitService
{
    public function __construct(
        private readonly CarePlanOccurrenceService $occurrences,
        private readonly BookingPaymentService $payments,
        private readonly BookingTrustService $trust,
        private readonly MarketplaceNotificationService $notifications,
        private readonly MarketplacePricing $pricing,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('marketplace.completed_extra_visits.enabled', true);
    }

    public function canReport(CarePlan $plan, User $caregiver): bool
    {
        try {
            $this->assertCaregiverCanReport($plan, $caregiver);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function preview(CarePlan $plan, User $caregiver, array $input, ?int $supersedesId = null): array
    {
        $this->assertCaregiverCanReport($plan, $caregiver);
        $period = $this->validatedPeriod($plan, $input);
        $this->assertNoOverlap($plan, $period['start'], $period['end'], $supersedesId);

        return $period + ['financial' => $this->financialPreview($plan, $period['worked_minutes'])];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function submit(
        CarePlan $plan,
        User $caregiver,
        array $input,
        string $clientRequestId,
        ?int $supersedesId = null,
    ): CompletedExtraVisitRequest {
        $existing = CompletedExtraVisitRequest::query()->where('client_request_id', $clientRequestId)->first();
        if ($existing) {
            abort_unless(
                (int) $existing->care_plan_id === (int) $plan->id
                    && (int) $existing->caregiver_user_id === (int) $caregiver->id,
                403
            );

            return $existing;
        }

        $preview = $this->preview($plan, $caregiver, $input, $supersedesId);
        $reason = (string) ($input['reason_code'] ?? '');
        $explanation = trim((string) ($input['explanation'] ?? ''));
        $careNotes = trim((string) ($input['care_notes'] ?? ''));

        if (! in_array($reason, CompletedExtraVisitRequest::reasonCodes(), true)) {
            throw ValidationException::withMessages(['reportReason' => 'Choose why this visit was not scheduled in advance.']);
        }
        if (mb_strlen($explanation) < 8 || mb_strlen($explanation) > 2000) {
            throw ValidationException::withMessages(['reportExplanation' => 'Explain what happened in 8 to 2,000 characters.']);
        }
        if (mb_strlen($careNotes) > 2000) {
            throw ValidationException::withMessages(['reportCareNotes' => 'Care notes cannot exceed 2,000 characters.']);
        }
        if (! filter_var($input['attested'] ?? false, FILTER_VALIDATE_BOOL)) {
            throw ValidationException::withMessages(['reportAttested' => 'Confirm that you personally provided this care and that the hours are accurate.']);
        }

        $request = DB::transaction(function () use (
            $plan,
            $caregiver,
            $clientRequestId,
            $supersedesId,
            $reason,
            $explanation,
            $careNotes,
            $preview,
        ): CompletedExtraVisitRequest {
            $lockedPlan = CarePlan::query()->with(['family', 'caregiver.caregiverProfile'])->lockForUpdate()->findOrFail($plan->id);
            $this->assertCaregiverCanReport($lockedPlan, $caregiver);
            $this->assertNoOverlap($lockedPlan, $preview['start'], $preview['end'], $supersedesId);

            $supersedes = null;
            if ($supersedesId) {
                $supersedes = CompletedExtraVisitRequest::query()->lockForUpdate()->findOrFail($supersedesId);
                abort_unless(
                    (int) $supersedes->care_plan_id === (int) $lockedPlan->id
                        && (int) $supersedes->caregiver_user_id === (int) $caregiver->id,
                    403
                );
                if ($supersedes->status !== CompletedExtraVisitRequest::STATUS_CHANGES_REQUESTED) {
                    throw ValidationException::withMessages(['reportSubmit' => 'This report is no longer waiting for an update. Refresh and try again.']);
                }
                $supersedes->forceFill(['status' => CompletedExtraVisitRequest::STATUS_SUPERSEDED])->save();
            }

            $version = ((int) CompletedExtraVisitRequest::query()
                ->where('care_plan_id', $lockedPlan->id)
                ->max('version')) + 1;

            $created = CompletedExtraVisitRequest::query()->create([
                'client_request_id' => $clientRequestId,
                'care_plan_id' => $lockedPlan->id,
                'family_account_id' => $lockedPlan->family_account_id,
                'family_user_id' => $lockedPlan->family_user_id,
                'caregiver_user_id' => $lockedPlan->caregiver_user_id,
                'supersedes_id' => $supersedes?->id,
                'version' => $version,
                'status' => CompletedExtraVisitRequest::STATUS_PENDING_FAMILY,
                'reason_code' => $reason,
                'explanation' => $explanation,
                'care_notes' => $careNotes !== '' ? $careNotes : null,
                'timezone' => $preview['timezone'],
                'proposed_started_at' => $preview['start'],
                'proposed_completed_at' => $preview['end'],
                'proposed_break_minutes' => $preview['break_minutes'],
                'proposed_worked_minutes' => $preview['worked_minutes'],
                'financial_preview' => $preview['financial'],
                'submitted_at' => now(),
            ]);

            $this->recordPlanEvent(
                $lockedPlan,
                $caregiver,
                $supersedes ? 'completed_extra_visit_resubmitted' : 'completed_extra_visit_submitted',
                $explanation,
                $created
            );

            return $created;
        });

        $this->notifySubmitted($request, $supersedesId !== null);

        return $request->fresh(['plan', 'family', 'caregiver']);
    }

    public function requestChanges(CompletedExtraVisitRequest $request, User $family, string $note): CompletedExtraVisitRequest
    {
        $this->assertFamily($request, $family);
        $note = $this->validatedResponseNote($note, 'Explain what Charles should change in at least 8 characters.');

        $updated = DB::transaction(function () use ($request, $family, $note): CompletedExtraVisitRequest {
            $locked = CompletedExtraVisitRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->assertLatest($locked);
            if ($locked->status !== CompletedExtraVisitRequest::STATUS_PENDING_FAMILY) {
                throw ValidationException::withMessages(['extraVisitResponse' => 'This visit report is no longer waiting for your review.']);
            }
            $locked->forceFill([
                'status' => CompletedExtraVisitRequest::STATUS_CHANGES_REQUESTED,
                'family_response_note' => $note,
                'changes_requested_at' => now(),
            ])->save();
            $this->recordPlanEvent($locked->plan, $family, 'completed_extra_visit_changes_requested', $note, $locked);

            return $locked;
        });

        $this->notifyCaregiver(
            $updated,
            MarketplaceEvent::COMPLETED_EXTRA_VISIT_CHANGES_REQUESTED,
            'Please update the extra visit report',
            $family->name.' requested changes to the reported visit.',
            'changes-requested'
        );

        return $updated->fresh();
    }

    public function approve(CompletedExtraVisitRequest $request, User $family): CompletedExtraVisitRequest
    {
        $this->assertFamily($request, $family);

        $approved = DB::transaction(function () use ($request, $family): CompletedExtraVisitRequest {
            $locked = CompletedExtraVisitRequest::query()
                ->with(['plan.family', 'plan.relationship', 'caregiver.caregiverProfile'])
                ->lockForUpdate()
                ->findOrFail($request->id);
            $this->assertLatest($locked);
            if ($locked->status === CompletedExtraVisitRequest::STATUS_APPLIED) {
                return $locked;
            }
            if ($locked->status !== CompletedExtraVisitRequest::STATUS_PENDING_FAMILY) {
                throw ValidationException::withMessages(['extraVisitResponse' => 'This visit report is no longer waiting for approval.']);
            }

            $this->assertCaregiverCanReport($locked->plan, $locked->caregiver);
            $this->assertNoOverlap($locked->plan, $locked->proposed_started_at, $locked->proposed_completed_at, $locked->id);
            $finalQuote = $this->financialPreview($locked->plan, (int) $locked->proposed_worked_minutes);

            $locked->forceFill([
                'status' => CompletedExtraVisitRequest::STATUS_APPROVED_PROCESSING,
                'approved_by_user_id' => $family->id,
                'approved_at' => now(),
                'processing_started_at' => now(),
                'processing_attempts' => $locked->processing_attempts + 1,
                'final_financial_preview' => $finalQuote,
                'last_error' => null,
            ])->save();
            $this->recordPlanEvent($locked->plan, $family, 'completed_extra_visit_approved', 'The family approved the completed extra visit.', $locked);

            return $locked;
        });

        if ($approved->status === CompletedExtraVisitRequest::STATUS_APPLIED) {
            return $approved;
        }

        $this->notifyCaregiver(
            $approved,
            MarketplaceEvent::COMPLETED_EXTRA_VISIT_APPROVED,
            'Extra visit approved',
            $family->name.' approved your reported visit. Payment is being processed.',
            'approved'
        );

        return $this->finalizeApprovedSafely($approved);
    }

    public function resumePayment(CompletedExtraVisitRequest $request, User $family): CompletedExtraVisitRequest
    {
        $this->assertFamily($request, $family);
        $request = DB::transaction(function () use ($request): CompletedExtraVisitRequest {
            $locked = CompletedExtraVisitRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($locked->status !== CompletedExtraVisitRequest::STATUS_PAYMENT_ACTION_REQUIRED) {
                throw ValidationException::withMessages(['extraVisitResponse' => 'This report is not waiting for payment confirmation.']);
            }
            $locked->forceFill([
                'status' => CompletedExtraVisitRequest::STATUS_APPROVED_PROCESSING,
                'processing_attempts' => $locked->processing_attempts + 1,
                'processing_started_at' => now(),
                'last_error' => null,
            ])->save();

            return $locked;
        });

        return $this->finalizeApprovedSafely($request);
    }

    public function retryProcessing(CompletedExtraVisitRequest $request): CompletedExtraVisitRequest
    {
        $request = DB::transaction(function () use ($request): CompletedExtraVisitRequest {
            $locked = CompletedExtraVisitRequest::query()->lockForUpdate()->findOrFail($request->id);
            if (! in_array($locked->status, [CompletedExtraVisitRequest::STATUS_APPROVED_PROCESSING, CompletedExtraVisitRequest::STATUS_FAILED], true)) {
                return $locked;
            }
            if ($locked->processing_attempts >= 3) {
                return $this->escalateLocked($locked, null, 'Automatic processing could not finish after three safe attempts.');
            }
            $locked->forceFill([
                'status' => CompletedExtraVisitRequest::STATUS_APPROVED_PROCESSING,
                'processing_attempts' => $locked->processing_attempts + 1,
                'processing_started_at' => now(),
                'last_error' => null,
            ])->save();

            return $locked;
        });

        if ($request->status === CompletedExtraVisitRequest::STATUS_ESCALATED) {
            $this->notifyEscalated($request);

            return $request;
        }

        return $this->finalizeApprovedSafely($request);
    }

    public function dispute(CompletedExtraVisitRequest $request, User $family, string $reason): CompletedExtraVisitRequest
    {
        $this->assertFamily($request, $family);
        $reason = $this->validatedResponseNote($reason, 'Explain why this visit did not happen in at least 8 characters.');

        $updated = DB::transaction(function () use ($request, $family, $reason): CompletedExtraVisitRequest {
            $locked = CompletedExtraVisitRequest::query()->with('plan')->lockForUpdate()->findOrFail($request->id);
            $this->assertLatest($locked);
            if ($locked->status !== CompletedExtraVisitRequest::STATUS_PENDING_FAMILY) {
                throw ValidationException::withMessages(['extraVisitResponse' => 'This visit report can no longer be disputed from this screen.']);
            }
            $ticket = $this->supportTicket($locked, $family, $reason);
            $locked->forceFill([
                'status' => CompletedExtraVisitRequest::STATUS_DISPUTED,
                'family_response_note' => $reason,
                'support_ticket_id' => $ticket->id,
                'disputed_at' => now(),
            ])->save();
            $this->recordPlanEvent($locked->plan, $family, 'completed_extra_visit_disputed', $reason, $locked);

            return $locked;
        });

        $this->notifyCaregiver(
            $updated,
            MarketplaceEvent::COMPLETED_EXTRA_VISIT_DISPUTED,
            'Extra visit disputed',
            $family->name.' said the reported visit did not happen. LoLo Care will review it.',
            'disputed'
        );

        return $updated->fresh();
    }

    public function withdraw(CompletedExtraVisitRequest $request, User $caregiver): CompletedExtraVisitRequest
    {
        abort_unless((int) $request->caregiver_user_id === (int) $caregiver->id && $caregiver->role === 'caregiver', 403);

        $updated = DB::transaction(function () use ($request, $caregiver): CompletedExtraVisitRequest {
            $locked = CompletedExtraVisitRequest::query()->with('plan')->lockForUpdate()->findOrFail($request->id);
            $this->assertLatest($locked);
            if (! in_array($locked->status, [CompletedExtraVisitRequest::STATUS_PENDING_FAMILY, CompletedExtraVisitRequest::STATUS_CHANGES_REQUESTED], true)) {
                throw ValidationException::withMessages(['reportSubmit' => 'This report can no longer be withdrawn.']);
            }
            $locked->forceFill(['status' => CompletedExtraVisitRequest::STATUS_WITHDRAWN, 'withdrawn_at' => now()])->save();
            $this->recordPlanEvent($locked->plan, $caregiver, 'completed_extra_visit_withdrawn', 'The caregiver withdrew the report.', $locked);

            return $locked;
        });

        $this->notifyFamily(
            $updated,
            MarketplaceEvent::COMPLETED_EXTRA_VISIT_WITHDRAWN,
            'Extra visit report withdrawn',
            $caregiver->name.' withdrew the reported visit. No payment was made.',
            'withdrawn'
        );

        return $updated->fresh();
    }

    public function escalate(CompletedExtraVisitRequest $request, User $actor, string $reason): CompletedExtraVisitRequest
    {
        abort_unless(
            ($actor->role === 'family' && app(FamilyAccountContext::class)->canAccessRecord($actor, $request))
                || (int) $actor->id === (int) $request->caregiver_user_id,
            403
        );
        $reason = $this->validatedResponseNote($reason, 'Explain why you need LoLo Care help in at least 8 characters.');

        $updated = DB::transaction(function () use ($request, $actor, $reason): CompletedExtraVisitRequest {
            $locked = CompletedExtraVisitRequest::query()->with('plan')->lockForUpdate()->findOrFail($request->id);
            $this->assertLatest($locked);
            if (! in_array($locked->status, [CompletedExtraVisitRequest::STATUS_PENDING_FAMILY, CompletedExtraVisitRequest::STATUS_CHANGES_REQUESTED, CompletedExtraVisitRequest::STATUS_FAILED], true)) {
                throw ValidationException::withMessages(['extraVisitResponse' => 'This report cannot be escalated in its current state.']);
            }

            return $this->escalateLocked($locked, $actor, $reason);
        });

        $this->notifyEscalated($updated);

        return $updated->fresh();
    }

    /** @return array<string, mixed> */
    public function currentFinancialPreview(CompletedExtraVisitRequest $request): array
    {
        return $this->financialPreview($request->plan, (int) $request->proposed_worked_minutes);
    }

    public function timezoneFor(CarePlan|CompletedExtraVisitRequest $subject): string
    {
        if ($subject instanceof CompletedExtraVisitRequest) {
            return $subject->timezone ?: ($subject->plan?->timezone ?: (string) config('app.timezone'));
        }

        return $subject->timezone ?: (string) config('app.timezone');
    }

    private function finalizeApprovedSafely(CompletedExtraVisitRequest $request): CompletedExtraVisitRequest
    {
        try {
            return $this->finalizeApproved($request->fresh(['plan.family', 'plan.caregiver.caregiverProfile', 'booking.payment']));
        } catch (PaymentException $exception) {
            $updated = $this->markPaymentActionRequired($request, $exception->userMessage);
            $this->notifyFamily(
                $updated,
                MarketplaceEvent::COMPLETED_EXTRA_VISIT_PAYMENT_ACTION_REQUIRED,
                'Payment confirmation needed for the extra visit',
                'The visit is approved, but payment needs your attention before it can be finalized.',
                'payment-action-'.$updated->processing_attempts
            );

            return $updated;
        } catch (Throwable $exception) {
            report($exception);
            $updated = CompletedExtraVisitRequest::query()->findOrFail($request->id);
            if ($updated->status === CompletedExtraVisitRequest::STATUS_APPROVED_PROCESSING) {
                $updated->forceFill([
                    'status' => CompletedExtraVisitRequest::STATUS_FAILED,
                    'last_error' => 'Automatic processing paused safely. LoLo Care will retry or review this report.',
                ])->save();
            }

            return $updated->fresh();
        }
    }

    private function finalizeApproved(CompletedExtraVisitRequest $request): CompletedExtraVisitRequest
    {
        if ($request->status !== CompletedExtraVisitRequest::STATUS_APPROVED_PROCESSING) {
            return $request;
        }

        $booking = $request->booking;
        if (! $booking) {
            $booking = $this->occurrences->createExtraVisit(
                $request->plan,
                $request->proposed_started_at,
                $request->proposed_completed_at,
            );
            $request->forceFill(['care_booking_id' => $booking->id])->save();
        }

        $booking->forceFill([
            'plan_visit_kind' => 'completed_extra',
            'status' => CareBooking::STATUS_REVIEWED,
            'started_at' => $request->proposed_started_at,
            'completed_at' => $request->proposed_completed_at,
            'total_paused_seconds' => ((int) $request->proposed_break_minutes) * 60,
            'worked_minutes' => $request->proposed_worked_minutes,
            'timesheet_submitted_at' => $request->submitted_at,
            'family_confirmed_at' => $request->approved_at,
            'reviewed_at' => $request->approved_at,
            'check_in_source' => 'manual_family_approved_extra',
            'check_out_source' => 'manual_family_approved_extra',
            'check_in_lat' => null,
            'check_in_lng' => null,
            'check_out_lat' => null,
            'check_out_lng' => null,
        ])->save();
        $this->trust->recordEvent($booking, $request->caregiver_user_id, 'caregiver', 'completed_extra_visit_reported', [
            'completed_extra_visit_request_id' => $request->id,
            'reported_manually' => true,
            'location_verified' => false,
            'family_approved_at' => $request->approved_at?->toIso8601String(),
        ]);

        $this->payments->authorizeForBooking($booking->fresh(), notify: false);
        $payment = $this->payments->captureForBooking($booking->fresh(), notify: false);
        if ((int) $payment->overage_pending_cents > 0) {
            throw new PaymentException('Confirm the additional visit amount in Billing & Payments to finish this approved visit.');
        }

        $updated = DB::transaction(function () use ($request, $payment): CompletedExtraVisitRequest {
            $locked = CompletedExtraVisitRequest::query()->with('plan')->lockForUpdate()->findOrFail($request->id);
            if ($locked->status === CompletedExtraVisitRequest::STATUS_APPLIED) {
                return $locked;
            }
            if ($locked->status !== CompletedExtraVisitRequest::STATUS_APPROVED_PROCESSING) {
                return $locked;
            }
            $locked->forceFill([
                'status' => CompletedExtraVisitRequest::STATUS_APPLIED,
                'final_financial_preview' => array_merge($locked->final_financial_preview ?? [], [
                    'amount_captured_cents' => (int) $payment->amount_captured_cents,
                    'caregiver_amount_cents' => (int) $payment->caregiver_amount_cents,
                    'currency' => (string) $payment->currency,
                    'payment_status' => (string) $payment->status,
                ]),
                'finalized_at' => now(),
                'last_error' => $payment->status === CareBookingPayment::STATUS_TRANSFER_FAILED
                    ? 'The family payment was captured; caregiver payout transfer will be retried.'
                    : null,
            ])->save();
            $this->recordPlanEvent($locked->plan, null, 'completed_extra_visit_applied', 'The family-approved extra visit became booking #'.$locked->care_booking_id.'.', $locked);

            return $locked;
        });

        $this->trust->recordEvent($booking, $request->family_user_id, 'family', 'completed_extra_visit_approved_and_paid', [
            'completed_extra_visit_request_id' => $request->id,
            'payment_status' => $payment->status,
            'amount_captured_cents' => $payment->amount_captured_cents,
            'caregiver_amount_cents' => $payment->caregiver_amount_cents,
        ]);
        $this->trust->recomputeReliabilityForBooking($booking->fresh());
        $this->notifyApplied($updated);

        return $updated->fresh(['booking.payment']);
    }

    private function markPaymentActionRequired(CompletedExtraVisitRequest $request, string $message): CompletedExtraVisitRequest
    {
        $updated = CompletedExtraVisitRequest::query()->findOrFail($request->id);
        if ($updated->status === CompletedExtraVisitRequest::STATUS_APPLIED) {
            return $updated;
        }
        $updated->forceFill([
            'status' => CompletedExtraVisitRequest::STATUS_PAYMENT_ACTION_REQUIRED,
            'payment_action_required_at' => now(),
            'last_error' => $message,
        ])->save();

        return $updated->fresh();
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function validatedPeriod(CarePlan $plan, array $input): array
    {
        $timezone = $this->timezoneFor($plan);
        $date = trim((string) ($input['date'] ?? ''));
        $startTime = trim((string) ($input['start_time'] ?? ''));
        $endTime = trim((string) ($input['end_time'] ?? ''));
        $breakMinutes = filter_var($input['break_minutes'] ?? null, FILTER_VALIDATE_INT);

        try {
            $startLocal = Carbon::createFromFormat('!Y-m-d H:i', $date.' '.$startTime, $timezone);
            $endLocal = Carbon::createFromFormat('!Y-m-d H:i', $date.' '.$endTime, $timezone);
        } catch (Throwable) {
            throw ValidationException::withMessages(['reportDate' => 'Enter a valid visit date and start/end time.']);
        }
        if (! $startLocal || ! $endLocal || $startLocal->format('Y-m-d H:i') !== $date.' '.$startTime || $endLocal->format('Y-m-d H:i') !== $date.' '.$endTime) {
            throw ValidationException::withMessages(['reportDate' => 'Enter a valid visit date and start/end time.']);
        }
        if ($endLocal->lte($startLocal)) {
            $endLocal->addDay();
        }

        $breakMinutes = $breakMinutes === false ? -1 : (int) $breakMinutes;
        $elapsed = (int) $startLocal->diffInMinutes($endLocal, false);
        $worked = $elapsed - $breakMinutes;
        $minimum = max(1, (int) config('marketplace.completed_extra_visits.minimum_duration_minutes', 15));
        $maximum = max($minimum, (int) config('marketplace.completed_extra_visits.maximum_duration_minutes', 960));
        if ($breakMinutes < 0 || $breakMinutes >= $elapsed) {
            throw ValidationException::withMessages(['reportBreakMinutes' => 'Break time must be zero or shorter than the visit.']);
        }
        if ($worked < $minimum || $worked > $maximum) {
            throw ValidationException::withMessages(['reportEndTime' => 'Worked time must be between '.$minimum.' and '.$maximum.' minutes.']);
        }

        $futureSkew = max(0, (int) config('marketplace.completed_extra_visits.future_clock_skew_minutes', 5));
        if ($endLocal->gt(now($timezone)->addMinutes($futureSkew))) {
            throw ValidationException::withMessages(['reportEndTime' => 'A completed visit cannot end in the future.']);
        }
        $historyDays = max(1, (int) config('marketplace.completed_extra_visits.history_window_days', 30));
        if ($startLocal->lt(now($timezone)->subDays($historyDays))) {
            throw ValidationException::withMessages(['reportDate' => 'This visit is outside the '.$historyDays.'-day reporting window. Contact LoLo Care support for help.']);
        }
        // The plan record may be activated or migrated after care actually began.
        // Eligibility, the reporting window, overlap checks, caregiver attestation,
        // and explicit family approval are the authoritative safeguards here.
        if ($plan->ended_at && $startLocal->gt($plan->ended_at->copy()->setTimezone($timezone)->endOfDay())) {
            throw ValidationException::withMessages(['reportDate' => 'The visit happened after this recurring care relationship ended.']);
        }

        return [
            'timezone' => $timezone,
            'start' => $startLocal->copy()->setTimezone((string) config('app.timezone')),
            'end' => $endLocal->copy()->setTimezone((string) config('app.timezone')),
            'break_minutes' => $breakMinutes,
            'worked_minutes' => $worked,
        ];
    }

    private function assertCaregiverCanReport(CarePlan $plan, User $caregiver): void
    {
        abort_unless($this->enabled(), 404);
        abort_unless($caregiver->role === 'caregiver' && (int) $plan->caregiver_user_id === (int) $caregiver->id, 403);
        abort_unless($plan->family_user_id && $plan->care_relationship_id, 403);
        $plan->loadMissing('relationship');
        abort_unless(
            $plan->relationship
                && (int) $plan->relationship->family_user_id === (int) $plan->family_user_id
                && (int) $plan->relationship->caregiver_user_id === (int) $plan->caregiver_user_id,
            403
        );
        abort_unless(
            User::query()->whereKey($plan->family_user_id)->where('role', 'family')->exists(),
            403
        );
        $caregiver->loadMissing('caregiverProfile');
        abort_unless($caregiver->caregiverProfile?->status === 'active', 403);
        $this->assertPlanStillProcessable($plan);
    }

    private function assertPlanStillProcessable(CarePlan $plan): void
    {
        if (in_array($plan->status, [CarePlan::STATUS_ACTIVE, CarePlan::STATUS_PAYMENT_ATTENTION, CarePlan::STATUS_PAUSED], true)) {
            return;
        }
        $grace = max(0, (int) config('marketplace.completed_extra_visits.ended_plan_grace_days', 30));
        if ($plan->status === CarePlan::STATUS_ENDED && $plan->ended_at && $plan->ended_at->gte(now()->subDays($grace))) {
            return;
        }

        throw ValidationException::withMessages(['reportSubmit' => 'Completed extra visits are only available for an established live plan or a recently ended plan.']);
    }

    private function assertNoOverlap(CarePlan $plan, Carbon $start, Carbon $end, ?int $excludeRequestId = null): void
    {
        $bookingExists = CareBooking::query()
            ->forFamilyAccount($plan->family_account_id)
            ->where('caregiver_user_id', $plan->caregiver_user_id)
            ->where('scheduled_start_at', '<', $end)
            ->where('scheduled_end_at', '>', $start)
            ->exists();
        if ($bookingExists) {
            throw ValidationException::withMessages(['reportDate' => 'These hours overlap an existing visit. Open that visit if its recorded time needs correction.']);
        }

        if ($this->occurrences->overlapsScheduledRegularOccurrence($plan, $start, $end)) {
            throw ValidationException::withMessages(['reportDate' => 'These hours belong to the regular schedule. Open that visit if its recorded time needs correction.']);
        }

        $reportExists = CompletedExtraVisitRequest::query()
            ->where('care_plan_id', $plan->id)
            ->whereNotIn('status', [CompletedExtraVisitRequest::STATUS_WITHDRAWN, CompletedExtraVisitRequest::STATUS_SUPERSEDED])
            ->when($excludeRequestId, fn ($query) => $query->where('id', '!=', $excludeRequestId))
            ->where('proposed_started_at', '<', $end)
            ->where('proposed_completed_at', '>', $start)
            ->exists();
        if ($reportExists) {
            throw ValidationException::withMessages(['reportDate' => 'These hours overlap another extra-visit report. Open the existing report instead.']);
        }
    }

    /** @return array<string,mixed> */
    private function financialPreview(CarePlan $plan, int $workedMinutes): array
    {
        return array_merge($this->pricing->currentQuoteForMinutes($workedMinutes), ['currency' => 'usd']);
    }

    private function assertFamily(CompletedExtraVisitRequest $request, User $family): void
    {
        abort_unless($family->role === 'family' && app(FamilyAccountContext::class)->canAccessRecord($family, $request), 403);
    }

    private function assertLatest(CompletedExtraVisitRequest $request): void
    {
        $newerExists = CompletedExtraVisitRequest::query()->where('supersedes_id', $request->id)->exists();
        if ($newerExists || $request->status === CompletedExtraVisitRequest::STATUS_SUPERSEDED) {
            throw ValidationException::withMessages(['extraVisitResponse' => 'A newer version of this report exists. Refresh and review that version.']);
        }
    }

    private function validatedResponseNote(string $note, string $message): string
    {
        $note = trim($note);
        if (mb_strlen($note) < 8 || mb_strlen($note) > 2000) {
            throw ValidationException::withMessages(['extraVisitResponse' => $message]);
        }

        return $note;
    }

    private function escalateLocked(CompletedExtraVisitRequest $request, ?User $actor, string $reason): CompletedExtraVisitRequest
    {
        $ticket = $request->support_ticket_id
            ? SupportTicket::query()->find($request->support_ticket_id)
            : $this->supportTicket($request, $actor ?: $request->family, $reason);
        $request->forceFill([
            'status' => CompletedExtraVisitRequest::STATUS_ESCALATED,
            'support_ticket_id' => $ticket?->id,
            'escalated_at' => now(),
            'last_error' => $reason,
        ])->save();
        $this->recordPlanEvent($request->plan, $actor, 'completed_extra_visit_escalated', $reason, $request);

        return $request;
    }

    private function supportTicket(CompletedExtraVisitRequest $request, User $opener, string $reason): SupportTicket
    {
        $request->loadMissing('plan');

        return SupportTicket::query()->create([
            'family_account_id' => $request->family_account_id,
            'family_visibility' => 'shared_care',
            'opener_user_id' => $opener->id,
            'counterparty_user_id' => $opener->role === 'family'
                ? $request->caregiver_user_id
                : $request->family_user_id,
            'care_request_id' => $request->booking?->care_request_id ?: $request->plan?->source_care_request_id,
            'care_booking_id' => $request->care_booking_id,
            'category' => 'completed_extra_visit',
            'status' => SupportTicket::STATUS_OPEN,
            'priority' => 'high',
            'subject' => 'Completed extra visit #'.$request->id.' needs review',
            'description' => $reason,
        ]);
    }

    private function recordPlanEvent(CarePlan $plan, ?User $actor, string $type, string $reason, CompletedExtraVisitRequest $request): void
    {
        CarePlanEvent::query()->create([
            'care_plan_id' => $plan->id,
            'actor_user_id' => $actor?->id,
            'event_type' => $type,
            'reason' => $reason,
            'payload' => [
                'completed_extra_visit_request_id' => $request->id,
                'version' => $request->version,
                'status' => $request->status,
                'care_booking_id' => $request->care_booking_id,
                'proposed_started_at' => $request->proposed_started_at?->toIso8601String(),
                'proposed_completed_at' => $request->proposed_completed_at?->toIso8601String(),
                'proposed_worked_minutes' => $request->proposed_worked_minutes,
            ],
        ]);
    }

    private function notifySubmitted(CompletedExtraVisitRequest $request, bool $resubmitted): void
    {
        $request->loadMissing(['family', 'caregiver', 'plan']);
        $local = $request->proposed_started_at->copy()->setTimezone($request->timezone);
        $this->notifyFamily(
            $request,
            $resubmitted ? MarketplaceEvent::COMPLETED_EXTRA_VISIT_RESUBMITTED : MarketplaceEvent::COMPLETED_EXTRA_VISIT_SUBMITTED,
            $resubmitted ? 'Updated extra visit ready to review' : $request->caregiver->name.' reported an extra visit',
            $request->caregiver->name.' reported care on '.$local->format('l, F j').'. Review the hours before any payment is made.',
            $resubmitted ? 'resubmitted' : 'submitted'
        );
    }

    private function notifyApplied(CompletedExtraVisitRequest $request): void
    {
        $request->loadMissing(['family', 'caregiver']);
        $familyBody = 'The family-approved extra visit is now in care history.';
        $caregiverBody = 'The family-approved extra visit is complete and has been added to your earnings.';
        $this->notifyFamily($request, MarketplaceEvent::COMPLETED_EXTRA_VISIT_APPLIED, 'Extra visit approved and recorded', $familyBody, 'applied-family');
        $this->notifyCaregiver($request, MarketplaceEvent::COMPLETED_EXTRA_VISIT_APPLIED, 'Extra visit approved and recorded', $caregiverBody, 'applied-caregiver');
    }

    private function notifyEscalated(CompletedExtraVisitRequest $request): void
    {
        $this->notifyFamily($request, MarketplaceEvent::COMPLETED_EXTRA_VISIT_ESCALATED, 'LoLo Care is reviewing the extra visit', 'The visit report was sent to LoLo Care support. No new payment will be made while it is reviewed.', 'escalated-family');
        $this->notifyCaregiver($request, MarketplaceEvent::COMPLETED_EXTRA_VISIT_ESCALATED, 'LoLo Care is reviewing the extra visit', 'The visit report was sent to LoLo Care support for review.', 'escalated-caregiver');
    }

    private function notifyFamily(CompletedExtraVisitRequest $request, string $event, string $title, string $body, string $key): void
    {
        $request->loadMissing(['family', 'caregiver', 'plan']);
        if (! $request->family) {
            return;
        }
        $this->notifications->notify(
            recipients: $request->family,
            eventKey: $event,
            title: $title,
            body: $body,
            url: route('family.care.show', ['carePlan' => $request->care_plan_id, 'extra_visit' => $request->id])
                .'#completed-extra-visit-'.$request->id,
            payload: $this->notificationPayload($request, 'family'),
            subject: $request,
            dedupeKey: 'completed-extra-visit:'.$key.':request-'.$request->id.':version-'.$request->version.':user-'.$request->family_user_id,
        );
    }

    private function notifyCaregiver(CompletedExtraVisitRequest $request, string $event, string $title, string $body, string $key): void
    {
        $request->loadMissing(['family', 'caregiver', 'plan']);
        if (! $request->caregiver) {
            return;
        }
        $this->notifications->notify(
            recipients: $request->caregiver,
            eventKey: $event,
            title: $title,
            body: $body,
            url: route('caregiver.regular-clients.index', ['extra_visit' => $request->id]),
            payload: $this->notificationPayload($request, 'caregiver'),
            subject: $request,
            dedupeKey: 'completed-extra-visit:'.$key.':request-'.$request->id.':version-'.$request->version.':user-'.$request->caregiver_user_id,
        );
    }

    /** @return array<string,mixed> */
    private function notificationPayload(CompletedExtraVisitRequest $request, string $role): array
    {
        $start = $request->proposed_started_at->copy()->setTimezone($request->timezone);
        $end = $request->proposed_completed_at->copy()->setTimezone($request->timezone);
        $financial = $request->final_financial_preview ?: $request->financial_preview;
        $amountLabel = $role === 'family' ? 'Estimated family charge' : 'Estimated caregiver payout';
        $amount = $role === 'family'
            ? (int) data_get($financial, 'total_charge_cents', data_get($financial, 'amount_captured_cents', 0))
            : (int) data_get($financial, 'caregiver_amount_cents', 0);

        return [
            'care_plan_id' => $request->care_plan_id,
            'completed_extra_visit_request_id' => $request->id,
            'status' => $request->status,
            'preheader' => $request->caregiver?->name.' · '.$start->format('M j').' · '.$request->durationLabel(),
            'email_details' => [
                ['label' => $role === 'family' ? 'Caregiver' : 'Family', 'value' => $role === 'family' ? $request->caregiver?->name : $request->family?->name],
                ['label' => 'Visit', 'value' => $start->format('l, F j, Y')],
                ['label' => 'Time', 'value' => $start->format('g:i A').'–'.$end->format('g:i A').' '.$start->format('T')],
                ['label' => 'Worked time', 'value' => $request->durationLabel()],
                ['label' => $amountLabel, 'value' => '$'.number_format($amount / 100, 2)],
                ['label' => 'Status', 'value' => $request->statusLabel()],
            ],
        ];
    }
}
