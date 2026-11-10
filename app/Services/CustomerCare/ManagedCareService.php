<?php

namespace App\Services\CustomerCare;

use App\Models\CareBooking;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareRequestMessage;
use App\Models\FamilyAccount;
use App\Models\User;
use App\Services\Booking\BookingTrustService;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\PrepaidBookingFundingService;
use App\Services\CareHours\PrepaidBookingSettlementService;
use App\Services\CareRequests\CareRequestLifecycleService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Marketplace\CareRequestHiringService;
use App\Services\Messaging\CareRequestChatService;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Services\RegularCare\CarePlanService;
use App\Support\MarketplaceEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManagedCareService
{
    public function save(User $actor, User $customer, array $input, ?CareRequest $request = null): CareRequest
    {
        CustomerCareAccess::authorize($actor);
        abort_unless($customer->role === 'family' && $customer->access_revoked_at === null, 422);
        $data = Validator::make($input, [
            'request_key' => [$request ? 'nullable' : 'required', 'uuid'],
            'title' => ['required', 'string', 'max:180'],
            'scope_of_work' => ['required', 'string', 'max:5000'],
            'home_access_notes' => ['nullable', 'string', 'max:2000'],
            'recipient_name' => ['required', 'string', 'max:160'],
            'care_notes' => ['nullable', 'string', 'max:5000'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'size:2'],
            'zip' => ['required', 'regex:/^\d{5}(-\d{4})?$/'],
            'request_type' => ['required', Rule::in(['one_time', 'recurring'])],
            'requested_start_at' => ['required_if:request_type,one_time', 'nullable', 'date', 'after:now'],
            'requested_end_at' => ['required_if:request_type,one_time', 'nullable', 'date', 'after:requested_start_at'],
            'recurring_days' => ['required_if:request_type,recurring', 'array'],
            'recurring_days.*' => ['integer', 'between:0,6', 'distinct'],
            'recurring_start_time' => ['required_if:request_type,recurring', 'nullable', 'date_format:H:i'],
            'recurring_end_time' => ['required_if:request_type,recurring', 'nullable', 'date_format:H:i', 'after:recurring_start_time'],
            'recurring_starts_on' => ['required_if:request_type,recurring', 'nullable', 'date', 'after_or_equal:today'],
            'recurring_ends_on' => ['nullable', 'date', 'after_or_equal:recurring_starts_on'],
            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*' => ['integer', 'distinct', Rule::exists('care_tasks', 'id')],
            'status' => ['required', Rule::in(['draft', 'open'])],
        ])->validate();

        return DB::transaction(function () use ($actor, $customer, $data, $request): CareRequest {
            $ownership = app(FamilyAccountContext::class)->ownershipAttributes($customer);
            FamilyAccount::whereKey($ownership['family_account_id'])->lockForUpdate()->firstOrFail();
            if (! $request && ($existing = CareRequest::where('staff_request_key', $data['request_key'])->first())) {
                abort_unless((int) $existing->family_account_id === $ownership['family_account_id'] && $existing->staff_managed, 409);

                return $existing;
            }
            if ($request) {
                $request = $this->locked($actor, $request);
                abort_unless((int) $request->family_account_id === $ownership['family_account_id'], 403);
                $this->require(in_array($request->status, ['draft', 'open'], true) && ! $request->booking()->exists(), 'Edit the visit or care plan after hiring.');
            }
            $recurring = $data['request_type'] === 'recurring';
            $attributes = array_intersect_key($data, array_flip(['title', 'scope_of_work', 'home_access_notes', 'address_line1', 'address_line2', 'city', 'state', 'zip', 'request_type', 'status']));
            $attributes += [
                'staff_managed' => true, 'is_private' => false,
                'requested_start_at' => $recurring ? null : $data['requested_start_at'],
                'requested_end_at' => $recurring ? null : $data['requested_end_at'],
                'recurring_days' => $recurring ? array_map('intval', $data['recurring_days']) : null,
                'recurring_start_time' => $recurring ? $data['recurring_start_time'] : null,
                'recurring_end_time' => $recurring ? $data['recurring_end_time'] : null,
                'recurring_starts_on' => $recurring ? $data['recurring_starts_on'] : null,
                'recurring_ends_on' => $recurring ? ($data['recurring_ends_on'] ?? null) : null,
                'recurring_schedule' => $recurring ? array_map(fn ($day) => ['day' => (int) $day, 'start_time' => $data['recurring_start_time'], 'end_time' => $data['recurring_end_time']], $data['recurring_days']) : null,
                'preferred_response_hours' => 24, 'time_expectations' => 'Coordinated by the LoLo Care team.',
            ];
            if ($request) {
                $request->update($attributes);
            } else {
                $request = CareRequest::create($ownership + $attributes + ['created_by_user_id' => $actor->id, 'staff_request_key' => $data['request_key']]);
            }
            $request->recipient()->updateOrCreate([], ['full_name' => trim($data['recipient_name']),
                'recipient_is_requester' => false, 'relationship_to_family' => 'other', 'care_notes' => $data['care_notes'] ?? null]);
            $request->tasks()->sync($data['tasks']);
            $this->audit($actor, $request, $data['status'] === 'open' ? 'request_published' : 'draft_saved');

            return $request->fresh();
        }, 3);
    }

    public function takeOver(User $actor, CareRequest $request, string $reason): void
    {
        CustomerCareAccess::authorize($actor);
        $this->reason($reason);
        DB::transaction(function () use ($actor, $request, $reason): void {
            $request = CareRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $request->update(['staff_managed' => true]);
            $this->audit($actor, $request, 'management_started', $reason);
        });
    }

    public function hire(User $actor, CareRequest $request, int $caregiverId, string $reason): void
    {
        CustomerCareAccess::authorize($actor);
        $this->reason($reason);
        DB::transaction(function () use ($actor, $request, $caregiverId, $reason): void {
            $request = $this->locked($actor, $request);
            $this->require($request->status === 'open' && $request->isAcceptingApplications(), 'This request is not open for hiring.');
            $caregiver = User::where('role', 'caregiver')->whereNull('access_revoked_at')->findOrFail($caregiverId);
            app(\App\Services\Screening\CaregiverWorkEligibility::class)->assertUserCanStartNewWork($caregiverId);
            $this->require($caregiver->caregiverProfile?->isMarketplaceReady() === true, 'This caregiver is not ready for marketplace work.');
            $family = $request->family;
            $wallet = app(CareHourIdentityService::class)->walletForFamily($request->family_account_id);
            $this->require($wallet !== null && $wallet->active, 'Add prepaid hours to this customer before hiring.');
            $application = $request->applications()->firstOrCreate(['caregiver_user_id' => $caregiverId], [
                'status' => CareRequestApplication::STATUS_APPLIED,
                'proposed_rate' => app(\App\Support\MarketplacePricing::class)->caregiverGrossHourlyCents() / 100,
                'cover_note' => 'Placement coordinated by the LoLo Care team.',
            ]);
            // Existing withdrawn/rejected offers must not be silently revived.
            $result = app(CareRequestHiringService::class)->hire($family, $application, $wallet, $actor);
            $booking = $result['booking'];
            $funding = $booking ? app(PrepaidBookingFundingService::class)->prepare($booking) : null;
            $this->require($funding?->status === 'ready' && (int) $funding->shortfall_minutes === 0,
                'There are not enough available prepaid hours for the first visit. Add hours, then hire again.');
            $this->audit($actor, $request, 'caregiver_hired', $reason, ['caregiver_user_id' => $caregiverId, 'booking_id' => $booking->id, 'care_plan_id' => $result['care_plan_id']]);
        }, 3);
    }

    public function application(User $actor, CareRequest $request, int $id, string $status): void
    {
        DB::transaction(function () use ($actor, $request, $id, $status): void {
            $request = $this->locked($actor, $request);
            $this->require($request->status === 'open' && in_array($status, ['shortlisted', 'rejected'], true), 'This application cannot be changed.');
            $application = $request->applications()->lockForUpdate()->findOrFail($id);
            $this->require(in_array($application->status, ['applied', 'shortlisted'], true), 'This application is no longer active.');
            $application->update(['status' => $status]);
            $this->audit($actor, $request, 'application_'.$status, metadata: ['application_id' => $id]);
        });
    }

    public function withdraw(User $actor, CareRequest $request, string $reason): void
    {
        $this->reason($reason);
        DB::transaction(function () use ($actor, $request, $reason): void {
            $request = $this->locked($actor, $request);
            app(CareRequestLifecycleService::class)->withdraw($request->family, $request);
            $this->audit($actor, $request, 'request_withdrawn', $reason);
        });
    }

    public function note(User $actor, CareRequest $request, string $note): void
    {
        $this->reason($note);
        DB::transaction(fn () => $this->audit($actor, $this->locked($actor, $request), 'follow_up_note', $note));
    }

    public function message(User $actor, CareRequest $request, int $caregiverId, string $body, ?int $conversationId = null): void
    {
        $body = trim($body);
        Validator::make(['body' => $body], ['body' => ['required', 'string', 'max:3000']])->validate();
        DB::transaction(function () use ($actor, $request, $caregiverId, $body, $conversationId): void {
            $request = $this->locked($actor, $request);
            $conversation = $conversationId ? CareRequestConversation::where('caregiver_user_id', $caregiverId)
                ->whereHas('careRequest', fn ($q) => $q->whereKey($request->id)
                    ->orWhereHas('carePlan', fn ($plan) => $plan->where('source_care_request_id', $request->id)))
                ->lockForUpdate()->findOrFail($conversationId) : null;
            $messageRequest = $conversation?->careRequest ?? $request;
            $this->require(app(CareRequestChatService::class)->closedReason($messageRequest, $caregiverId) === null, 'This caregiver conversation is closed.');
            $application = $messageRequest->applications()->where('caregiver_user_id', $caregiverId)->first();
            $conversation ??= CareRequestConversation::findOrCreateForRequest($messageRequest, $caregiverId, $actor->id, $application);
            $message = CareRequestMessage::create(['care_request_conversation_id' => $conversation->id, 'sender_user_id' => $actor->id, 'body' => $body]);
            $conversation->update(['last_message_at' => now(), 'last_message_sender_id' => $actor->id]);
            $this->audit($actor, $request, 'caregiver_message_sent', metadata: ['message_id' => $message->id, 'caregiver_user_id' => $caregiverId]);
            DB::afterCommit(fn () => app(MarketplaceNotificationService::class)->notify(
                recipients: $conversation->caregiver, eventKey: MarketplaceEvent::MESSAGE_RECEIVED,
                title: 'Message from the care team', body: $actor->name.' sent you a message about this visit.',
                url: route('messages.show', $conversation->id), payload: ['conversation_id' => $conversation->id],
                subject: $conversation, dedupeKey: 'staff-message:'.$message->id));
        });
    }

    public function visit(User $actor, CareRequest $request, int $bookingId, string $action, array $input): void
    {
        CustomerCareAccess::authorize($actor);
        $reason = trim((string) ($input['reason'] ?? ''));
        $this->reason($reason);
        $booking = $this->booking($request, $bookingId);
        $this->require(app(PrepaidBookingFundingService::class)->isPrepaid($booking), 'This existing card visit needs the payment operations workflow. New managed visits use prepaid hours.');
        if (in_array($action, ['dispute', 'resolve_dispute'], true)) {
            $opening = $action === 'dispute';
            $attributes = $opening
                ? ['status' => 'disputed', 'dispute_status' => 'open', 'dispute_opened_at' => now(), 'dispute_opened_by_user_id' => $actor->id, 'dispute_reason' => $reason]
                : ['status' => 'completed', 'dispute_status' => 'resolved'];
            $changed = app(PrepaidBookingSettlementService::class)->updateVisitState($booking, $attributes,
                $opening ? ['completed', 'reviewed'] : ['disputed']);
            $this->require($changed, 'The visit state changed. Refresh before resolving this dispute.');
            $this->audit($actor, $request, 'visit_'.$action, $reason, ['booking_id' => $bookingId]);
            if (! $opening && ($settlement = \App\Models\CareHourSettlement::where('care_booking_id', $bookingId)->first())) {
                app(PrepaidBookingSettlementService::class)->reconcile($settlement);
            }

            return;
        }
        if ($action === 'cancel') {
            $changed = app(PrepaidBookingSettlementService::class)->updateVisitState($booking, [
                'status' => CareBooking::STATUS_CANCELLED, 'cancelled_at' => now(), 'cancelled_by_user_id' => $actor->id,
                'cancellation_reason' => 'Customer Success: '.$reason,
            ], [CareBooking::STATUS_SCHEDULED]);
            $this->require($changed, 'Only a scheduled visit can be cancelled.');
            $this->audit($actor, $request, 'visit_cancelled', $reason, ['booking_id' => $bookingId]);
            $this->notifyCaregiver($booking, MarketplaceEvent::SHIFT_CANCELLED, 'Visit cancelled');

            return;
        }
        DB::transaction(function () use ($actor, $request, $bookingId, $action, $input, $reason): void {
            $request = $this->locked($actor, $request);
            $booking = $this->booking($request, $bookingId, true);
            $funding = app(PrepaidBookingFundingService::class);
            if ($action === 'coverage') {
                $funding->prepare($booking);
            } elseif ($action === 'reschedule') {
                $this->require($booking->status === 'scheduled', 'Only a scheduled visit can be rescheduled.');
                $data = Validator::make($input, ['start' => ['required', 'date', 'after:now'], 'end' => ['required', 'date', 'after:start']])->validate();
                $start = Carbon::parse($data['start']);
                $end = Carbon::parse($data['end']);
                $this->require(! CareBooking::where('caregiver_user_id', $booking->caregiver_user_id)->whereKeyNot($booking->id)
                    ->whereIn('status', ['scheduled', 'in_progress', 'paused'])->where('scheduled_start_at', '<', $end)->where('scheduled_end_at', '>', $start)->exists(), 'This caregiver already has an overlapping visit.');
                $booking->update(['scheduled_start_at' => $start, 'scheduled_end_at' => $end, 'expected_minutes' => (int) $start->diffInMinutes($end),
                    'last_rescheduled_at' => now(), 'last_reschedule_reason' => 'Customer Success: '.$reason]);
                $funding->protectScheduleChange($booking);
                $booking->careRequest->update(['requested_start_at' => $start, 'requested_end_at' => $end]);
            } elseif ($action === 'complete') {
                $this->require(in_array($booking->status, ['scheduled', 'in_progress', 'paused'], true), 'This visit is already closed.');
                $data = Validator::make($input, ['start' => ['required', 'date', 'before_or_equal:now'], 'end' => ['required', 'date', 'after:start', 'before_or_equal:now'], 'break_minutes' => ['required', 'integer', 'min:0']])->validate();
                $start = Carbon::parse($data['start']);
                $end = Carbon::parse($data['end']);
                $minutes = (int) $start->diffInMinutes($end) - (int) $data['break_minutes'];
                $this->require($minutes >= 0 && $minutes <= 1440, 'Check actual visit times and the unpaid break (maximum 24 hours).');
                $booking->update(['status' => 'completed', 'started_at' => $start, 'completed_at' => $end,
                    'worked_minutes' => $minutes, 'total_paused_seconds' => (int) $data['break_minutes'] * 60,
                    'paused_at' => null, 'timesheet_submitted_at' => now()]);
            } elseif ($action === 'approve') {
                $this->require(in_array($booking->status, ['completed', 'reviewed'], true) && $booking->worked_minutes !== null && $booking->dispute_status !== 'open', 'Review completed actual hours and resolve any dispute before approval.');
                $uncovered = (int) ($booking->careHourSettlement?->card_minutes ?? 0);
                if ($booking->family_confirmed_at && (! $funding->usesStaffPrepaidOnly($booking) || $uncovered === 0)) {
                    return;
                }
                $preview = $funding->approvalPreview($booking, $booking->worked_minutes);
                $this->require((int) $preview['card_cents'] === 0, 'Add prepaid hours to cover all approved time before approval.');
                $settlement = app(PrepaidBookingSettlementService::class)->settle($booking);
                $this->require((int) $settlement->card_minutes === 0, 'Available prepaid hours changed. Add coverage and retry approval.');
                if (! $booking->family_confirmed_at) {
                    $booking->update(['family_confirmed_at' => now(), 'family_confirmed_by_user_id' => $actor->id]);
                }
            } else {
                abort(422);
            }
            app(BookingTrustService::class)->recordEvent($booking, $actor->id, $actor->role, 'customer_care_'.$action, ['reason' => $reason]);
            $this->audit($actor, $request, 'visit_'.$action, $reason, ['booking_id' => $bookingId]);
            if (in_array($action, ['reschedule', 'complete'], true)) {
                DB::afterCommit(fn () => $this->notifyCaregiver($booking, $action === 'complete' ? MarketplaceEvent::SHIFT_COMPLETED : MarketplaceEvent::BOOKING_CHANGE_ACCEPTED, $action === 'complete' ? 'Visit completed by the care team' : 'Visit schedule updated'));
            }
        }, 3);
    }

    public function change(User $actor, CareRequest $request, int $bookingId, int $changeId, string $decision, string $reason): void
    {
        CustomerCareAccess::authorize($actor);
        $this->reason($reason);
        $this->require(in_array($decision, ['accept', 'reject'], true), 'Choose accept or reject.');
        DB::transaction(function () use ($actor, $request, $bookingId, $changeId, $decision, $reason): void {
            $booking = $this->booking($request, $bookingId, true);
            $change = $booking->changeRequests()->lockForUpdate()->findOrFail($changeId);
            $this->require($change->status === 'pending', 'This change has already been resolved.');
            if ($decision === 'accept') {
                if ($change->type === 'cancel' && ! $booking->care_plan_id
                    && (int) $change->requester_user_id === (int) $booking->caregiver_user_id) {
                    app(\App\Services\Booking\CaregiverCancellationService::class)->cancel($booking, $actor, $reason, $change->id);
                } else {
                    $this->visit($actor, $request, $bookingId, $change->type, [
                        'reason' => $reason, 'start' => $change->proposed_start_at?->toDateTimeString(), 'end' => $change->proposed_end_at?->toDateTimeString(),
                    ]);
                }
            }
            $change->update(['status' => $decision === 'accept' ? 'accepted' : 'rejected',
                'resolved_at' => now(), 'resolved_by_user_id' => $actor->id, 'resolution_note' => $reason]);
            $this->audit($actor, $request, 'change_'.$decision, $reason, ['change_id' => $changeId, 'booking_id' => $bookingId]);
            DB::afterCommit(fn () => $this->notifyCaregiver($booking,
                $decision === 'accept' ? MarketplaceEvent::BOOKING_CHANGE_ACCEPTED : MarketplaceEvent::BOOKING_CHANGE_DECLINED,
                $decision === 'accept' ? 'Visit change accepted' : 'Visit change declined'));
        });
    }

    public function correction(User $actor, CareRequest $request, int $bookingId, int $correctionId, string $decision, string $reason): void
    {
        CustomerCareAccess::authorize($actor);
        $this->reason($reason);
        $booking = $this->booking($request, $bookingId);
        $correction = $booking->timeCorrections()->findOrFail($correctionId);
        $service = app(\App\Services\Booking\CareBookingTimeCorrectionService::class);
        if ($decision === 'approve') {
            $funding = app(PrepaidBookingFundingService::class);
            $this->require($funding->usesStaffPrepaidOnly($booking), 'This correction requires the existing payment operations workflow.');
            $preview = $funding->approvalPreview($booking, $correction->proposed_worked_minutes);
            $this->require($preview['card_cents'] === 0, 'Add prepaid hours before approving the corrected care time.');
            $service->approve($correction, $actor);
        } elseif ($decision === 'changes') {
            $service->requestChanges($correction, $actor, $reason);
        } else {
            abort(422);
        }
        $this->audit($actor, $request, 'time_correction_'.$decision, $reason, ['time_correction_id' => $correctionId]);
    }

    public function plan(User $actor, CareRequest $request, string $action, string $reason, array $input = []): void
    {
        CustomerCareAccess::authorize($actor);
        $this->reason($reason);
        $request = $request->fresh();
        abort_unless($request->staff_managed, 403);
        $plan = CarePlan::where('source_care_request_id', $request->id)->firstOrFail();
        $service = app(CarePlanService::class);
        match ($action) {
            'pause' => $service->pausePlan($plan, $actor, now()),
            'resume' => $service->resumePlan($plan, $actor),
            'end' => $service->endPlan($plan, $actor, true),
            'schedule' => $service->requestScheduleChange($plan, $actor, $input + ['note' => $reason]),
            'extra' => $this->extraVisit($plan, $actor, $input, $reason),
            default => abort(422),
        };
        $this->audit($actor, $request, 'plan_'.$action, $reason, ['care_plan_id' => $plan->id]);
    }

    private function extraVisit(CarePlan $plan, User $actor, array $input, string $reason): void
    {
        $data = Validator::make($input, ['start' => ['required', 'date', 'after:now'], 'end' => ['required', 'date', 'after:start']])->validate();
        app(CarePlanService::class)->requestExtraVisit($plan, $actor, Carbon::parse($data['start']), Carbon::parse($data['end']), $reason);
    }

    public function audit(User $actor, CareRequest $request, string $action, ?string $note = null, array $metadata = []): void
    {
        CustomerCareAccess::authorize($actor);
        DB::table('customer_care_actions')->insert(['care_request_id' => $request->id, 'actor_user_id' => $actor->id,
            'action' => $action, 'note' => $note, 'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    private function locked(User $actor, CareRequest $request): CareRequest
    {
        CustomerCareAccess::authorize($actor);
        $request = CareRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
        abort_unless($request->staff_managed, 403);

        return $request;
    }

    private function booking(CareRequest $request, int $id, bool $lock = false): CareBooking
    {
        abort_unless($request->fresh()->staff_managed, 403);

        return CareBooking::where(fn ($q) => $q->where('care_request_id', $request->id)
            ->orWhereHas('carePlan', fn ($plan) => $plan->where('source_care_request_id', $request->id)))
            ->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail($id);
    }

    private function notifyCaregiver(CareBooking $booking, string $event, string $title): void
    {
        app(MarketplaceNotificationService::class)->notify(recipients: $booking->caregiver, eventKey: $event,
            title: $title, body: 'The LoLo Care team updated visit #'.$booking->id.'.',
            url: route('care-requests.apply', $booking->care_request_id), payload: ['care_booking_id' => $booking->id], subject: $booking);
    }

    private function reason(string $reason): void
    {
        Validator::make(['reason' => trim($reason)], ['reason' => ['required', 'string', 'min:5', 'max:2000']])->validate();
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['customer_care' => $message]);
        }
    }
}
