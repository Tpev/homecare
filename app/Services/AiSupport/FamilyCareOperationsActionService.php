<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportConfirmedActionEvidence;
use App\Models\AiSupportGuidedTask;
use App\Models\AiSupportMessageAction;
use App\Models\CareBooking;
use App\Models\CareBookingChangeRequest;
use App\Models\CareBookingTimeCorrection;
use App\Models\CarePlan;
use App\Models\CarePlanScheduleChange;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareRequestInvitation;
use App\Models\CareRequestMessage;
use App\Models\CareReview;
use App\Models\CompletedExtraVisitRequest;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\Booking\BookingTrustService;
use App\Services\Booking\CareBookingTimeCorrectionService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Marketplace\CareRequestHiringService;
use App\Services\Marketplace\CareRequestInvitationService;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Services\Payments\BookingPaymentService;
use App\Services\RegularCare\CarePlanService;
use App\Services\RegularCare\CompletedExtraVisitService;
use App\Support\FunnelTracker;
use App\Support\MarketplaceEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class FamilyCareOperationsActionService
{
    public const CAPABILITY = 'family_care_operations_v1';

    private const TOOL_PREFIXES = ['caregiver.', 'applicant.', 'visit.', 'regular-care.'];

    public function __construct(
        private readonly FamilyAccountContext $familyAccounts,
        private readonly AiSupportEligibilityService $eligibility,
        private readonly AiSupportActionEvidenceService $evidence,
        private readonly AiSupportEventRecorder $events,
        private readonly AiSupportGuidedTaskService $guidedTasks,
        private readonly AiSupportHandoffService $handoff,
        private readonly NavigationTargetRegistry $navigation,
        private readonly CareRequestInvitationService $invitations,
        private readonly CareRequestHiringService $hiring,
        private readonly MarketplaceNotificationService $notifications,
        private readonly BookingTrustService $trust,
        private readonly BookingPaymentService $payments,
        private readonly CareBookingTimeCorrectionService $timeCorrections,
        private readonly CarePlanService $plans,
        private readonly CompletedExtraVisitService $completedExtraVisits,
    ) {}

    /** @param array<string,mixed> $record */
    public function respond(User $actor, SupportTicket $ticket, array $record, string $message): bool
    {
        $intentId = (string) ($record['intent_id'] ?? '');
        if (! preg_match('/^FAM-(MATCH|VISIT|REGULAR)-\d{3}$/', $intentId, $matches)) {
            return false;
        }
        if (! $this->eligibility->evaluate($actor, self::CAPABILITY, $ticket)->allowed) {
            return false;
        }

        if (in_array($intentId, [
            'FAM-MATCH-024', 'FAM-MATCH-025', 'FAM-VISIT-015', 'FAM-VISIT-016',
            'FAM-VISIT-027', 'FAM-VISIT-028', 'FAM-VISIT-035',
            'FAM-REGULAR-016', 'FAM-REGULAR-018', 'FAM-REGULAR-026',
        ], true)) {
            $this->handoff->transfer($actor, $ticket, 'family_care_operation_'.$intentId);

            return true;
        }

        return match ($matches[1]) {
            'MATCH' => $this->respondMatch($actor, $ticket, $intentId, $message),
            'VISIT' => $this->respondVisit($actor, $ticket, $intentId, $message),
            'REGULAR' => $this->respondRegular($actor, $ticket, $intentId, $message),
        };
    }

    public function ownsTool(string $tool): bool
    {
        return collect(self::TOOL_PREFIXES)->contains(fn (string $prefix): bool => str_starts_with($tool, $prefix));
    }

    public function confirm(User $actor, SupportTicket $ticket, string $actionId): AiSupportConfirmedActionEvidence
    {
        $action = $this->domainAction($actor, $ticket, $actionId);
        $payload = (array) $action->payload;
        $tool = (string) ($payload['tool_id'] ?? '');
        if (! $this->ownsTool($tool)) {
            throw new AuthorizationException;
        }
        if ($action->consumed_at) {
            return AiSupportConfirmedActionEvidence::query()
                ->where('idempotency_key', (string) ($payload['idempotency_key'] ?? ''))
                ->where('actor_user_id', $actor->id)->firstOrFail();
        }
        if (! $action->isActive()) {
            throw ValidationException::withMessages(['confirmation' => 'This confirmation expired or changed. Review a fresh recap and confirm again.']);
        }

        $confirmed = $this->evidence->commitConfirmedAction(
            $actor,
            (string) $payload['confirmation_reference'],
            (string) $payload['idempotency_key'],
            (string) $payload['confirmation_action'],
            fn (array $preview): array => $this->commit($actor, $ticket, $tool, $preview),
        );

        DB::transaction(function () use ($actor, $ticket, $action, $payload, $confirmed): void {
            $locked = AiSupportMessageAction::query()->lockForUpdate()->findOrFail($action->id);
            if ($locked->consumed_at) {
                return;
            }
            $locked->forceFill([
                'payload' => [
                    'idempotency_key' => $confirmed->idempotency_key,
                    'tool_id' => $confirmed->tool_id,
                    'confirmed_action_evidence_id' => $confirmed->id,
                ],
                'consumed_at' => now(),
            ])->save();
            $message = $this->automatedMessage($ticket, $this->receiptText($confirmed));
            AiSupportMessageAction::query()->create([
                'id' => (string) Str::uuid(),
                'support_ticket_message_id' => $message->id,
                'support_ticket_id' => $ticket->id,
                'actor_user_id' => $actor->id,
                'action_type' => AiSupportMessageAction::TYPE_DOMAIN_RECEIPT,
                'payload' => [
                    'title' => 'Done and checked',
                    'receipt' => $confirmed->receipt_reference,
                    'url' => $this->receiptUrl($actor, $confirmed),
                    'label' => $this->receiptLabel($confirmed),
                ],
            ]);
            $this->events->record($ticket, 'intent_completed', [
                'support_ticket_message_id' => $message->id,
                'capability_id' => self::CAPABILITY,
                'tool_id' => $confirmed->tool_id,
                'tool_version' => $confirmed->tool_version,
                'result_code' => 'authoritative_receipt_created',
                'safe_metadata' => ['intent_id' => (string) ($payload['intent_id'] ?? '')],
            ], $actor);
        }, 3);

        return $confirmed;
    }

    public function renew(User $actor, SupportTicket $ticket, string $actionId): AiSupportMessageAction
    {
        $action = $this->domainAction($actor, $ticket, $actionId, false);
        if ($action->consumed_at) {
            throw ValidationException::withMessages(['confirmation' => 'This action was already completed.']);
        }
        $payload = (array) $action->payload;
        $tool = (string) ($payload['tool_id'] ?? '');
        if (! $this->ownsTool($tool)) {
            throw new AuthorizationException;
        }

        return $this->reissue($actor, $ticket, $payload);
    }

    private function respondMatch(User $actor, SupportTicket $ticket, string $intentId, string $message): bool
    {
        if (in_array($intentId, ['FAM-MATCH-001', 'FAM-MATCH-002', 'FAM-MATCH-003', 'FAM-MATCH-004', 'FAM-MATCH-005', 'FAM-MATCH-006', 'FAM-MATCH-007'], true)) {
            $request = $this->selectRequest($actor, $message, [CareRequest::STATUS_OPEN]);
            $body = 'I can help you browse and compare only the caregiver facts shown to your Family account. Search results and availability notes are not a promise that someone will accept.';
            $this->offerRead($actor, $ticket, $body, $intentId, $request ? 'family.request.applicants' : 'family.care_requests', $request);

            return true;
        }

        if (in_array($intentId, ['FAM-MATCH-013', 'FAM-MATCH-014', 'FAM-MATCH-017', 'FAM-MATCH-019', 'FAM-MATCH-021', 'FAM-MATCH-022', 'FAM-MATCH-023'], true)) {
            $request = $this->selectRequest($actor, $message);
            if (! $request) {
                $this->automatedMessage($ticket, 'I could not find an authorized care request with caregivers to review.');

                return true;
            }
            $applications = $request->applications()->with(['caregiver.caregiverProfile', 'conversation'])->latest()->get();
            if ($applications->isEmpty()) {
                $this->offerRead($actor, $ticket, 'No caregivers have replied to '.$this->requestLabel($request).' yet.', $intentId, 'family.request.applicants', $request);

                return true;
            }
            $active = $applications->whereIn('status', [CareRequestApplication::STATUS_APPLIED, CareRequestApplication::STATUS_SHORTLISTED, CareRequestApplication::STATUS_HIRED]);
            if ($intentId === 'FAM-MATCH-017') {
                $application = $this->selectApplication($actor, $message) ?: ($active->count() === 1 ? $active->first() : null);
                if ($application?->conversation) {
                    $this->offerRead($actor, $ticket, 'I found the conversation with '.$application->caregiver?->name.'.', $intentId, 'family.message', $application->conversation);

                    return true;
                }
                if ($application && $this->available($actor, $ticket, 'applicant.conversation')) {
                    $this->issueAction($actor, $ticket, 'applicant.conversation', [
                        'application_id' => (int) $application->id,
                        'expected_status' => (string) $application->status,
                        'expected_updated_at' => $this->stamp($application->updated_at),
                        'expected_request_status' => (string) $application->careRequest->status,
                        'expected_request_updated_at' => $this->stamp($application->careRequest->updated_at),
                    ], $intentId, 'Start a conversation with '.$application->caregiver?->name.'?',
                        'This saves an applied caregiver for follow-up and opens the authorized conversation. It does not hire them.', [
                            ['label' => 'Caregiver', 'value' => (string) $application->caregiver?->name],
                            ['label' => 'Request', 'value' => $this->requestLabel($application->careRequest)],
                        ], 'Confirm conversation');

                    return true;
                }
            }
            $lines = $active->take(4)->map(fn (CareRequestApplication $application): string => $this->applicationSummary($application))->implode("\n");
            $body = $active->isEmpty()
                ? 'There are no active applicants waiting for a decision on '.$this->requestLabel($request).'.'
                : 'Here are the active caregiver replies for '.$this->requestLabel($request).":\n".$lines;
            if ($intentId === 'FAM-MATCH-023') {
                $body .= "\nI can organize visible facts, but you make the final caregiver choice.";
            }
            if ($intentId === 'FAM-MATCH-019') {
                $body = 'LoLo can confirm when a message was sent. It does not claim the caregiver read it unless an authoritative read record is shown.';
            }
            $this->offerRead($actor, $ticket, $body, $intentId, 'family.request.applicants', $request);

            return true;
        }

        if (in_array($intentId, ['FAM-MATCH-008', 'FAM-MATCH-009', 'FAM-MATCH-010'], true)) {
            $request = $this->selectRequest($actor, $message, [CareRequest::STATUS_OPEN]);
            $caregiver = $this->selectCaregiver($message);
            if (! $request || ! $caregiver) {
                $this->automatedMessage($ticket, 'Tell me the caregiver name and the open request number. I will show a recap before sending anything.');
                if ($request) {
                    $this->offerRead($actor, $ticket, 'You can also choose the caregiver from this request.', $intentId, 'family.request.applicants', $request);
                }

                return true;
            }
            $reinvite = $intentId === 'FAM-MATCH-010';
            $tool = 'caregiver.invite';
            if (! $this->available($actor, $ticket, $tool)) {
                $this->offerRead($actor, $ticket, 'I can open the invitation area, but confirmed chat sending is not enabled for you yet.', $intentId, 'family.request.applicants', $request);

                return true;
            }
            $invitationMessage = $this->messageText($message);
            $preview = [
                'care_request_id' => (int) $request->id,
                'caregiver_user_id' => (int) $caregiver->id,
                'message' => $invitationMessage,
                'reinvite' => $reinvite,
                'expected_request_status' => (string) $request->status,
                'expected_request_updated_at' => $this->stamp($request->updated_at),
            ];
            $this->issueAction($actor, $ticket, $tool, $preview, $intentId,
                ($reinvite ? 'Invite ' : 'Send an invitation to ').$caregiver->name.'?',
                'This sends an invitation for '.$this->requestLabel($request).'. It does not hire the caregiver or promise a response.',
                [
                    ['label' => 'Caregiver', 'value' => (string) $caregiver->name],
                    ['label' => 'Request', 'value' => $this->requestLabel($request)],
                    ['label' => 'Message', 'value' => $invitationMessage ?: 'No optional message'],
                ],
                $reinvite ? 'Confirm and invite again' : 'Confirm and send invitation');

            return true;
        }

        if ($intentId === 'FAM-MATCH-011') {
            $request = $this->selectRequest($actor, $message);
            $invitation = $this->selectInvitation($actor, $message, $request);
            $body = $invitation
                ? ($invitation->caregiver?->name ?: 'The caregiver').' invitation is '.$this->invitationStatus($invitation).'.'
                : 'I could not find one authorized caregiver invitation to check.';
            $this->offerRead($actor, $ticket, $body, $intentId, 'family.request.applicants', $request);

            return true;
        }

        if ($intentId === 'FAM-MATCH-012') {
            $invitation = $this->selectInvitation($actor, $message);
            if (! $invitation || $invitation->status !== CareRequestInvitation::STATUS_PENDING) {
                $this->automatedMessage($ticket, 'I could not find one pending invitation that can be cancelled. Nothing was changed.');

                return true;
            }
            if (! $this->available($actor, $ticket, 'caregiver.invitation.cancel')) {
                $this->offerRead($actor, $ticket, 'Open the request to review this invitation.', $intentId, 'family.request.applicants', $invitation->careRequest);

                return true;
            }
            $this->issueAction($actor, $ticket, 'caregiver.invitation.cancel', [
                'invitation_id' => (int) $invitation->id,
                'expected_status' => (string) $invitation->status,
                'expected_updated_at' => $this->stamp($invitation->updated_at),
            ], $intentId, 'Cancel the invitation to '.($invitation->caregiver?->name ?: 'this caregiver').'?',
                'The invitation will be cancelled. The care request stays open.', [
                    ['label' => 'Caregiver', 'value' => (string) ($invitation->caregiver?->name ?: 'Caregiver')],
                    ['label' => 'Request', 'value' => $this->requestLabel($invitation->careRequest)],
                ], 'Confirm invitation cancellation');

            return true;
        }

        if (in_array($intentId, ['FAM-MATCH-015', 'FAM-MATCH-016', 'FAM-MATCH-020'], true)) {
            $application = $this->selectApplication($actor, $message);
            if (! $application) {
                $this->automatedMessage($ticket, 'I could not safely identify one active applicant. Say the caregiver name or open Caregiver responses.');

                return true;
            }
            $tool = match ($intentId) {
                'FAM-MATCH-015' => 'applicant.shortlist',
                'FAM-MATCH-016' => 'applicant.reject',
                default => 'caregiver.hire',
            };
            if (! $this->available($actor, $ticket, $tool)) {
                $this->offerRead($actor, $ticket, 'Open the exact caregiver response to continue.', $intentId, 'family.request.applicants', $application->careRequest);

                return true;
            }
            $isHire = $tool === 'caregiver.hire';
            $preview = [
                'application_id' => (int) $application->id,
                'expected_status' => (string) $application->status,
                'expected_updated_at' => $this->stamp($application->updated_at),
                'expected_request_status' => (string) $application->careRequest->status,
                'expected_request_updated_at' => $this->stamp($application->careRequest->updated_at),
            ];
            $summary = match ($tool) {
                'applicant.shortlist' => 'This saves the caregiver for follow-up. It does not hire them.',
                'applicant.reject' => 'This declines the caregiver for this request. It does not block their account.',
                default => 'This selects the caregiver, closes other active applications, creates the visit or recurring care plan, and starts the existing payment-authorization workflow.',
            };
            $fields = [
                ['label' => 'Caregiver', 'value' => (string) $application->caregiver?->name],
                ['label' => 'Request', 'value' => $this->requestLabel($application->careRequest)],
                ['label' => 'Current status', 'value' => str_replace('_', ' ', (string) $application->status)],
            ];
            if ($isHire) {
                $fields[] = ['label' => 'Schedule', 'value' => $this->requestSchedule($application->careRequest)];
                $fields[] = ['label' => 'Family price', 'value' => '$30/hour care* + $1/hour processing fee'];
            }
            $this->issueAction($actor, $ticket, $tool, $preview, $intentId,
                match ($tool) {
                    'applicant.shortlist' => 'Save '.$application->caregiver?->name.' for follow-up?',
                    'applicant.reject' => 'Decline '.$application->caregiver?->name.' for this request?',
                    default => 'Hire '.$application->caregiver?->name.'?',
                }, $summary, $fields,
                match ($tool) {
                    'applicant.shortlist' => 'Confirm save for later',
                    'applicant.reject' => 'Confirm decline',
                    default => 'Confirm hire',
                });

            return true;
        }

        if (in_array($intentId, ['FAM-MATCH-018'], true)) {
            return $this->prepareMessage($actor, $ticket, $intentId, $message);
        }

        return false;
    }

    private function respondVisit(User $actor, SupportTicket $ticket, string $intentId, string $message): bool
    {
        if (in_array($intentId, ['FAM-VISIT-001', 'FAM-VISIT-002', 'FAM-VISIT-003', 'FAM-VISIT-012', 'FAM-VISIT-013'], true)) {
            $booking = $this->selectCurrentOrUpcomingBooking($actor, $message);
            if (! $booking) {
                $this->offerRead($actor, $ticket, 'I did not find a current or upcoming visit on your Family account.', $intentId, 'family.care_requests');

                return true;
            }
            $body = $this->bookingSummary($booking, true);
            if ($intentId === 'FAM-VISIT-013') {
                $body .= ' If the caregiver is late, message them or ask for LoLo Support. No-show can be marked only 30 minutes after scheduled start while the visit is still scheduled.';
            }
            $this->offerRead($actor, $ticket, $body, $intentId, 'family.request.visit', $booking->careRequest);

            return true;
        }

        if ($intentId === 'FAM-VISIT-004') {
            return $this->prepareMessage($actor, $ticket, $intentId, $message, true);
        }

        if (in_array($intentId, ['FAM-VISIT-005', 'FAM-VISIT-006'], true)) {
            $booking = $this->selectBooking($actor, $message, [CareBooking::STATUS_SCHEDULED]);
            if (! $booking) {
                $this->automatedMessage($ticket, 'I could not find one scheduled visit that can receive a change request.');

                return true;
            }
            $type = $intentId === 'FAM-VISIT-005' ? CareBookingChangeRequest::TYPE_RESCHEDULE : CareBookingChangeRequest::TYPE_CANCEL;
            $reason = $this->reasonText($message);
            $range = $type === CareBookingChangeRequest::TYPE_RESCHEDULE ? $this->dateTimeRange($message) : null;
            if ($reason === '' || ($type === CareBookingChangeRequest::TYPE_RESCHEDULE && ! $range)) {
                $this->automatedMessage($ticket, $type === CareBookingChangeRequest::TYPE_RESCHEDULE
                    ? 'Tell me the reason and the new start and end in this format: 2026-08-28 09:00 to 2026-08-28 12:00.'
                    : 'Tell me why you want to request cancellation. I will show a recap before sending it.');

                return true;
            }
            if (! $this->available($actor, $ticket, 'visit.change-request')) {
                $this->offerRead($actor, $ticket, 'Open the visit to request this change.', $intentId, 'family.request.visit', $booking->careRequest);

                return true;
            }
            $preview = [
                'care_booking_id' => (int) $booking->id,
                'type' => $type,
                'reason' => $reason,
                'proposed_start_at' => $range ? $range[0]->toIso8601String() : null,
                'proposed_end_at' => $range ? $range[1]->toIso8601String() : null,
                'expected_status' => (string) $booking->status,
                'expected_updated_at' => $this->stamp($booking->updated_at),
            ];
            $fields = [
                ['label' => 'Current visit', 'value' => $this->bookingTime($booking)],
                ['label' => 'Request', 'value' => $type === CareBookingChangeRequest::TYPE_CANCEL ? 'Request cancellation' : 'Request reschedule'],
                ['label' => 'Reason', 'value' => $reason],
            ];
            if ($range) {
                $fields[] = ['label' => 'Proposed visit', 'value' => $range[0]->format('D, M j, Y g:i A').'–'.$range[1]->format('g:i A')];
            }
            $this->issueAction($actor, $ticket, 'visit.change-request', $preview, $intentId,
                $type === CareBookingChangeRequest::TYPE_CANCEL ? 'Send this cancellation request?' : 'Send this reschedule request?',
                'The current visit stays unchanged until the caregiver accepts.', $fields, 'Confirm and send request');

            return true;
        }

        if (in_array($intentId, ['FAM-VISIT-007', 'FAM-VISIT-008'], true)) {
            $booking = $this->selectBooking($actor, $message, [CareBooking::STATUS_SCHEDULED]);
            $reason = $this->reasonText($message);
            if (! $booking || $reason === '') {
                $this->automatedMessage($ticket, 'Tell me which scheduled visit and the cancellation reason. I will show whether it is inside the late-cancellation window before anything changes.');

                return true;
            }
            $late = $booking->scheduled_start_at && now()->greaterThanOrEqualTo($booking->scheduled_start_at->copy()->subHours(24));
            if ($intentId === 'FAM-VISIT-008' && ! str_contains(mb_strtolower($message), 'cancel')) {
                $this->offerRead($actor, $ticket,
                    $late ? 'This scheduled visit is currently inside the 24-hour late-cancellation window.' : 'This scheduled visit is currently outside the 24-hour late-cancellation window.',
                    $intentId, 'family.request.visit', $booking->careRequest);

                return true;
            }
            if (! $this->available($actor, $ticket, 'visit.cancel')) {
                $this->offerRead($actor, $ticket, 'Open the visit to cancel it.', $intentId, 'family.request.visit', $booking->careRequest);

                return true;
            }
            $this->issueAction($actor, $ticket, 'visit.cancel', [
                'care_booking_id' => (int) $booking->id,
                'reason' => $reason,
                'expected_status' => (string) $booking->status,
                'expected_updated_at' => $this->stamp($booking->updated_at),
            ], $intentId, 'Cancel this scheduled visit?',
                $late ? 'This is inside the 24-hour late-cancellation window. The existing booking and payment services will preserve the exact result.' : 'Payment authorization will be released or reconciled by the existing booking service.',
                [
                    ['label' => 'Visit', 'value' => $this->bookingTime($booking)],
                    ['label' => 'Caregiver', 'value' => (string) ($booking->caregiver?->name ?: 'Caregiver')],
                    ['label' => 'Reason', 'value' => $reason],
                    ['label' => 'Late-cancellation window', 'value' => $late ? 'Inside' : 'Outside'],
                ], 'Confirm visit cancellation');

            return true;
        }

        if (in_array($intentId, ['FAM-VISIT-009', 'FAM-VISIT-010', 'FAM-VISIT-011'], true)) {
            $change = $this->selectPendingBookingChange($actor, $message);
            if (! $change) {
                $this->automatedMessage($ticket, 'I did not find one pending caregiver visit-change request to review.');

                return true;
            }
            $booking = $change->booking;
            $body = 'Current visit: '.$this->bookingTime($booking).'. Requested change: '.$this->bookingChangeSummary($change).'. Reason: '.$change->reason.'.';
            if ($intentId === 'FAM-VISIT-009') {
                $this->offerRead($actor, $ticket, $body, $intentId, 'family.request.visit_issue', $booking->careRequest);

                return true;
            }
            if (! $this->available($actor, $ticket, 'visit.change-request.resolve')) {
                $this->offerRead($actor, $ticket, $body, $intentId, 'family.request.visit_issue', $booking->careRequest);

                return true;
            }
            $decision = $intentId === 'FAM-VISIT-010' ? 'accept' : 'reject';
            $this->issueAction($actor, $ticket, 'visit.change-request.resolve', [
                'change_request_id' => (int) $change->id,
                'decision' => $decision,
                'expected_status' => (string) $change->status,
                'expected_updated_at' => $this->stamp($change->updated_at),
                'expected_booking_updated_at' => $this->stamp($booking->updated_at),
            ], $intentId, Str::headline($decision).' this caregiver change?',
                $decision === 'accept' ? 'The booking will be updated to the accepted schedule or cancellation.' : 'The current booking stays unchanged.', [
                    ['label' => 'Current visit', 'value' => $this->bookingTime($booking)],
                    ['label' => 'Requested change', 'value' => $this->bookingChangeSummary($change)],
                    ['label' => 'Decision', 'value' => Str::headline($decision)],
                ], 'Confirm '.($decision === 'accept' ? 'acceptance' : 'rejection'));

            return true;
        }

        if ($intentId === 'FAM-VISIT-014') {
            $booking = $this->selectBooking($actor, $message, [CareBooking::STATUS_SCHEDULED]);
            if (! $booking || ! $booking->scheduled_start_at || now()->lt($booking->scheduled_start_at->copy()->addMinutes(30))) {
                $this->automatedMessage($ticket, 'No-show can be marked only when a visit is still scheduled and at least 30 minutes have passed since scheduled start. Nothing was changed.');

                return true;
            }
            $this->issueAction($actor, $ticket, 'visit.no-show', [
                'care_booking_id' => (int) $booking->id,
                'expected_status' => (string) $booking->status,
                'expected_updated_at' => $this->stamp($booking->updated_at),
            ], $intentId, 'Mark this caregiver as a no-show?',
                'This cancels the visit, releases or reconciles payment authorization, and preserves the no-show reliability record.', [
                    ['label' => 'Visit', 'value' => $this->bookingTime($booking)],
                    ['label' => 'Caregiver', 'value' => (string) ($booking->caregiver?->name ?: 'Caregiver')],
                ], 'Confirm no-show');

            return true;
        }

        if ($intentId === 'FAM-VISIT-017') {
            $booking = $this->selectBooking($actor, $message, [CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED]);
            if (! $booking) {
                $this->automatedMessage($ticket, 'I could not find one in-progress or paused visit to mark complete.');

                return true;
            }
            $this->issueAction($actor, $ticket, 'visit.complete', [
                'care_booking_id' => (int) $booking->id,
                'expected_status' => (string) $booking->status,
                'expected_updated_at' => $this->stamp($booking->updated_at),
            ], $intentId, 'Mark this visit complete?', 'This records the visit as completed. It does not submit a review.', [
                ['label' => 'Visit', 'value' => $this->bookingTime($booking)],
                ['label' => 'Current status', 'value' => str_replace('_', ' ', $booking->status)],
            ], 'Confirm visit completed');

            return true;
        }

        if (in_array($intentId, ['FAM-VISIT-018', 'FAM-VISIT-019', 'FAM-VISIT-023', 'FAM-VISIT-026', 'FAM-VISIT-029', 'FAM-VISIT-034'], true)) {
            $booking = $this->selectBooking($actor, $message);
            if (! $booking) {
                $this->automatedMessage($ticket, 'I could not find an authorized visit record to check.');

                return true;
            }
            $body = $this->hoursSummary($booking);
            if ($intentId === 'FAM-VISIT-029') {
                $body = $booking->status === CareBooking::STATUS_DISPUTED
                    ? 'This visit dispute is '.str_replace('_', ' ', (string) ($booking->dispute_status ?: 'open')).'. LoLo Support owns the outcome.'
                    : 'I did not find an open dispute on this visit.';
            }
            $this->offerRead($actor, $ticket, $body, $intentId,
                in_array($intentId, ['FAM-VISIT-026'], true) ? 'family.request.payment_attention' : 'family.request.timesheet',
                $booking->careRequest);

            return true;
        }

        if ($intentId === 'FAM-VISIT-020') {
            $booking = $this->selectUnapprovedSubmittedHours($actor, $message);
            if (! $booking) {
                $this->automatedMessage($ticket, 'I could not find one unapproved submitted-hours record. Nothing was charged.');

                return true;
            }
            $this->issueAction($actor, $ticket, 'visit.hours.approve', [
                'care_booking_id' => (int) $booking->id,
                'expected_status' => (string) $booking->status,
                'expected_updated_at' => $this->stamp($booking->updated_at),
                'expected_worked_minutes' => (int) $booking->worked_minutes,
            ], $intentId, 'Approve these submitted hours and payment?',
                'This confirms the worked time and lets the existing payment service capture the authorized amount or request secure card action.', [
                    ['label' => 'Visit', 'value' => $this->bookingTime($booking)],
                    ['label' => 'Submitted time', 'value' => $this->duration((int) $booking->worked_minutes)],
                    ['label' => 'Family price', 'value' => '$30/hour care* + $1/hour processing fee'],
                    ['label' => 'Estimated care amount', 'value' => '$'.number_format(((int) $booking->worked_minutes / 60) * 30, 2)],
                ], 'Confirm hours and payment');

            return true;
        }

        if (in_array($intentId, ['FAM-VISIT-021', 'FAM-VISIT-022', 'FAM-VISIT-025'], true)) {
            $correction = $this->selectTimeCorrection($actor, $message);
            $reason = $this->reasonText($message);
            if (! $correction || $correction->status !== CareBookingTimeCorrection::STATUS_PENDING_FAMILY || $reason === '') {
                $this->handoff->transfer($actor, $ticket, 'submitted_hours_correction_requires_review');

                return true;
            }
            $this->issueAction($actor, $ticket, 'visit.time-correction.request-changes', [
                'correction_id' => (int) $correction->id,
                'reason' => $reason,
                'expected_status' => (string) $correction->status,
                'expected_updated_at' => $this->stamp($correction->updated_at),
            ], $intentId, 'Ask the caregiver to change this time correction?',
                'This sends your reason and does not approve hours or payment.', [
                    ['label' => 'Proposed time', 'value' => $correction->durationLabel()],
                    ['label' => 'Requested change', 'value' => $reason],
                ], 'Confirm request for changes');

            return true;
        }

        if ($intentId === 'FAM-VISIT-024') {
            $correction = $this->selectTimeCorrection($actor, $message);
            if (! $correction || $correction->status !== CareBookingTimeCorrection::STATUS_PENDING_FAMILY) {
                $this->automatedMessage($ticket, 'I could not find one pending time correction that can be approved.');

                return true;
            }
            $this->issueAction($actor, $ticket, 'visit.time-correction.approve', [
                'correction_id' => (int) $correction->id,
                'expected_status' => (string) $correction->status,
                'expected_updated_at' => $this->stamp($correction->updated_at),
                'expected_financial_preview' => hash('sha256', json_encode($correction->financial_preview)),
            ], $intentId, 'Approve this time correction and payment?',
                'This applies the proposed time through the existing audited correction service and can process the displayed payment.', [
                    ['label' => 'Proposed time', 'value' => $correction->proposed_started_at?->format('M j, g:i A').'–'.$correction->proposed_completed_at?->format('g:i A')],
                    ['label' => 'Duration', 'value' => $correction->durationLabel()],
                    ['label' => 'Family amount', 'value' => $correction->familyAmountLabel()],
                ], 'Confirm correction and payment');

            return true;
        }

        if ($intentId === 'FAM-VISIT-030') {
            $booking = $this->selectBooking($actor, $message, [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED]);
            $rating = $this->rating($message);
            if (! $booking || ! $rating) {
                $this->automatedMessage($ticket, 'Tell me the eligible visit and a rating from 1 to 5 stars. You can also add review text.');

                return true;
            }
            $comment = $this->reviewText($message);
            $this->issueAction($actor, $ticket, 'visit.review', [
                'care_booking_id' => (int) $booking->id,
                'rating' => $rating,
                'comment' => $comment,
                'expected_status' => (string) $booking->status,
                'expected_updated_at' => $this->stamp($booking->updated_at),
            ], $intentId, 'Submit this caregiver review?',
                'This saves the rating and optional text. If completed care still needs confirmation, the existing payment service may process it.', [
                    ['label' => 'Caregiver', 'value' => (string) ($booking->caregiver?->name ?: 'Caregiver')],
                    ['label' => 'Rating', 'value' => $rating.' of 5 stars'],
                    ['label' => 'Review', 'value' => $comment ?: 'No written review'],
                ], 'Confirm and submit review');

            return true;
        }

        if ($intentId === 'FAM-VISIT-031') {
            $this->handoff->transfer($actor, $ticket, 'submitted_review_change');

            return true;
        }

        if ($intentId === 'FAM-VISIT-032') {
            $booking = $this->selectBooking($actor, $message);
            $range = $this->dateTimeRange($message);
            if (! $booking || ! $range) {
                $this->automatedMessage($ticket, 'Tell me the new start and end in this format: 2026-08-30 09:00 to 2026-08-30 12:00. The prior request will stay unchanged.');

                return true;
            }
            $this->issueAction($actor, $ticket, 'visit.rebook', [
                'source_care_request_id' => (int) $booking->care_request_id,
                'caregiver_user_id' => (int) $booking->caregiver_user_id,
                'start_at' => $range[0]->toIso8601String(),
                'end_at' => $range[1]->toIso8601String(),
                'expected_booking_updated_at' => $this->stamp($booking->updated_at),
            ], $intentId, 'Create this new rebook request and invite '.$booking->caregiver?->name.'?',
                'This creates a separate open request with the new schedule and sends an invitation. It does not mean the caregiver accepted.', [
                    ['label' => 'Caregiver', 'value' => (string) ($booking->caregiver?->name ?: 'Caregiver')],
                    ['label' => 'New visit', 'value' => $range[0]->format('D, M j, Y g:i A').'–'.$range[1]->format('g:i A')],
                ], 'Confirm rebook and invitation');

            return true;
        }

        if ($intentId === 'FAM-VISIT-033') {
            $request = $this->selectRequest($actor, $message);
            if ($request) {
                return $this->prepareRegularOffer($actor, $ticket, $intentId, $message, $request);
            }
        }

        return false;
    }

    private function respondRegular(User $actor, SupportTicket $ticket, string $intentId, string $message): bool
    {
        if (in_array($intentId, ['FAM-REGULAR-001', 'FAM-REGULAR-009', 'FAM-REGULAR-010', 'FAM-REGULAR-024'], true)) {
            $plan = $this->selectPlan($actor, $message);
            if (! $plan) {
                $this->offerRead($actor, $ticket, 'I did not find a recurring care plan on your Family account.', $intentId, 'family.regular_care');

                return true;
            }
            $body = $this->planSummary($plan);
            if ($intentId === 'FAM-REGULAR-024') {
                $body .= ' Care history contains the Family-visible completed visits and payments.';
            }
            $this->offerRead($actor, $ticket, $body, $intentId,
                $intentId === 'FAM-REGULAR-024' ? 'family.care_history' : 'family.regular_care.attention',
                $intentId === 'FAM-REGULAR-024' ? null : $plan);

            return true;
        }

        if (in_array($intentId, ['FAM-REGULAR-002', 'FAM-REGULAR-003', 'FAM-REGULAR-004', 'FAM-REGULAR-005'], true)) {
            $request = $this->selectRequest($actor, $message);
            if (! $request) {
                $this->automatedMessage($ticket, 'I could not find an eligible earlier request or hired caregiver for a recurring care offer.');

                return true;
            }

            return $this->prepareRegularOffer($actor, $ticket, $intentId, $message, $request);
        }

        if ($intentId === 'FAM-REGULAR-006') {
            $this->offerRead($actor, $ticket, 'A Family payment method is required so LoLo can authorize each generated visit safely. Family care is $30/hour plus a $1/hour processing fee. Caregiver gross earnings are $27/hour minus actual Stripe processing fees on successful Family charges.', $intentId, 'family.billing.payment_method');

            return true;
        }

        if (in_array($intentId, ['FAM-REGULAR-007', 'FAM-REGULAR-008'], true)) {
            $plan = $this->selectPlan($actor, $message, [CarePlan::STATUS_COUNTERED]);
            if (! $plan) {
                $this->automatedMessage($ticket, 'I did not find one current recurring care counteroffer.');

                return true;
            }
            $body = 'Original schedule: '.$this->plans->scheduleLabel($plan).'. Countered schedule: '.$this->plans->scheduleLabel($plan, true).'.';
            if ($plan->counter_note) {
                $body .= ' Caregiver note: '.$plan->counter_note.'.';
            }
            if ($intentId === 'FAM-REGULAR-007') {
                $this->offerRead($actor, $ticket, $body, $intentId, 'family.regular_care.attention', $plan);

                return true;
            }
            $this->issueAction($actor, $ticket, 'regular-care.accept-counter', [
                'care_plan_id' => (int) $plan->id,
                'expected_status' => (string) $plan->status,
                'expected_schedule_version' => (int) $plan->schedule_version,
                'expected_updated_at' => $this->stamp($plan->updated_at),
            ], $intentId, 'Accept this recurring care counteroffer?',
                'This replaces the proposed schedule with the caregiver’s counter and may create the next visit.', [
                    ['label' => 'Current offer', 'value' => $this->plans->scheduleLabel($plan)],
                    ['label' => 'Caregiver counter', 'value' => $this->plans->scheduleLabel($plan, true)],
                    ['label' => 'Caregiver', 'value' => (string) ($plan->caregiver?->name ?: 'Caregiver')],
                ], 'Confirm counteroffer');

            return true;
        }

        if ($intentId === 'FAM-REGULAR-011') {
            $plan = $this->selectPlan($actor, $message, [CarePlan::STATUS_ACTIVE, CarePlan::STATUS_PAYMENT_ATTENTION]);
            $booking = $plan ? $this->selectPlanBooking($plan, $message) : null;
            if (! $plan || ! $booking) {
                $this->automatedMessage($ticket, 'I could not find one upcoming scheduled recurring care visit to skip.');

                return true;
            }
            $late = $booking->scheduled_start_at && now()->gte($booking->scheduled_start_at->copy()->subHours(24));
            $this->issueAction($actor, $ticket, 'regular-care.skip-visit', [
                'care_plan_id' => (int) $plan->id,
                'care_booking_id' => (int) $booking->id,
                'expected_plan_updated_at' => $this->stamp($plan->updated_at),
                'expected_booking_status' => (string) $booking->status,
                'expected_booking_updated_at' => $this->stamp($booking->updated_at),
            ], $intentId, 'Skip this one recurring care visit?',
                ($late ? 'This is inside the 24-hour late-cancellation window. ' : '').'The recurring care plan and later schedule continue.', [
                    ['label' => 'Visit', 'value' => $this->bookingTime($booking)],
                    ['label' => 'Plan', 'value' => $plan->displayTitle()],
                    ['label' => 'Late-cancellation window', 'value' => $late ? 'Inside' : 'Outside'],
                ], 'Confirm skip visit');

            return true;
        }

        if ($intentId === 'FAM-REGULAR-012') {
            $plan = $this->selectPlan($actor, $message, [CarePlan::STATUS_ACTIVE, CarePlan::STATUS_PAYMENT_ATTENTION]);
            $range = $this->dateTimeRange($message);
            if (! $plan || ! $range) {
                $this->automatedMessage($ticket, 'Tell me the extra visit start and end in this format: 2026-08-30 14:00 to 2026-08-30 16:00.');

                return true;
            }
            $note = $this->messageText($message);
            $this->issueAction($actor, $ticket, 'regular-care.extra-visit', [
                'care_plan_id' => (int) $plan->id,
                'start_at' => $range[0]->toIso8601String(),
                'end_at' => $range[1]->toIso8601String(),
                'note' => $note,
                'expected_status' => (string) $plan->status,
                'expected_schedule_version' => (int) $plan->schedule_version,
                'expected_updated_at' => $this->stamp($plan->updated_at),
            ], $intentId, 'Send this extra-visit request?',
                'This is a request until the caregiver accepts. It does not change the weekly schedule.', [
                    ['label' => 'Extra visit', 'value' => $range[0]->format('D, M j, Y g:i A').'–'.$range[1]->format('g:i A')],
                    ['label' => 'Caregiver', 'value' => (string) ($plan->caregiver?->name ?: 'Caregiver')],
                    ['label' => 'Message', 'value' => $note ?: 'No optional message'],
                ], 'Confirm extra-visit request');

            return true;
        }

        if (in_array($intentId, ['FAM-REGULAR-013', 'FAM-REGULAR-017'], true)) {
            $extra = $this->selectCompletedExtraVisit($actor, $message);
            if (! $extra) {
                $this->automatedMessage($ticket, 'I did not find a current completed extra-visit report to review.');

                return true;
            }
            $body = $this->completedExtraVisitSummary($extra);
            $this->offerRead($actor, $ticket, $body, $intentId, 'family.regular_care.attention', $extra->plan);

            return true;
        }

        if ($intentId === 'FAM-REGULAR-014') {
            $extra = $this->selectCompletedExtraVisit($actor, $message, [CompletedExtraVisitRequest::STATUS_PENDING_FAMILY]);
            if (! $extra) {
                $this->automatedMessage($ticket, 'I could not find one pending completed extra visit that can be approved.');

                return true;
            }
            $this->issueAction($actor, $ticket, 'regular-care.extra-visit.approve', [
                'completed_extra_visit_id' => (int) $extra->id,
                'expected_status' => (string) $extra->status,
                'expected_updated_at' => $this->stamp($extra->updated_at),
                'expected_financial_preview' => hash('sha256', json_encode($extra->financial_preview)),
            ], $intentId, 'Approve and pay this completed extra visit?',
                'This records the extra visit and processes the current financial preview through the existing payment service.', [
                    ['label' => 'Visit', 'value' => $extra->proposed_started_at?->format('M j, g:i A').'–'.$extra->proposed_completed_at?->format('g:i A')],
                    ['label' => 'Duration', 'value' => $extra->durationLabel()],
                    ['label' => 'Family amount', 'value' => '$'.number_format((int) data_get($extra->financial_preview, 'target_charge_cents', 0) / 100, 2)],
                ], 'Confirm extra visit and payment');

            return true;
        }

        if ($intentId === 'FAM-REGULAR-015') {
            $extra = $this->selectCompletedExtraVisit($actor, $message, [CompletedExtraVisitRequest::STATUS_PENDING_FAMILY]);
            $reason = $this->reasonText($message);
            if (! $extra || $reason === '') {
                $this->automatedMessage($ticket, 'Tell me what the caregiver should correct in the pending extra-visit report.');

                return true;
            }
            $this->issueAction($actor, $ticket, 'regular-care.extra-visit.request-changes', [
                'completed_extra_visit_id' => (int) $extra->id,
                'reason' => $reason,
                'expected_status' => (string) $extra->status,
                'expected_updated_at' => $this->stamp($extra->updated_at),
            ], $intentId, 'Request these extra-visit changes?', 'No payment is approved. Your reason is sent to the caregiver.', [
                ['label' => 'Current report', 'value' => $extra->durationLabel()],
                ['label' => 'Requested change', 'value' => $reason],
            ], 'Confirm request for changes');

            return true;
        }

        if ($intentId === 'FAM-REGULAR-019') {
            $plan = $this->selectPlan($actor, $message, [CarePlan::STATUS_ACTIVE, CarePlan::STATUS_PAYMENT_ATTENTION]);
            $schedule = $this->weeklyScheduleFromMessage($message);
            if (! $plan || ! $schedule) {
                $this->automatedMessage($ticket, 'Tell me the weekdays, start and end time, and future effective date. Example: Mondays and Thursdays 09:00 to 12:00 starting 2026-09-01.');

                return true;
            }
            $this->issueAction($actor, $ticket, 'regular-care.schedule-change', [
                'care_plan_id' => (int) $plan->id,
                'schedule_days' => $schedule['days'],
                'schedule_slots' => $schedule['slots'],
                'starts_on' => $schedule['starts_on'],
                'note' => $this->reasonText($message),
                'expected_status' => (string) $plan->status,
                'expected_schedule_version' => (int) $plan->schedule_version,
                'expected_updated_at' => $this->stamp($plan->updated_at),
            ], $intentId, 'Send this recurring care schedule change?',
                'Current generated visits stay unchanged until the caregiver accepts.', [
                    ['label' => 'Current schedule', 'value' => $this->plans->scheduleLabel($plan)],
                    ['label' => 'Proposed schedule', 'value' => $schedule['label']],
                    ['label' => 'Effective', 'value' => Carbon::parse($schedule['starts_on'])->format('M j, Y')],
                ], 'Confirm schedule-change request');

            return true;
        }

        if ($intentId === 'FAM-REGULAR-020') {
            $plan = $this->selectPlan($actor, $message, [CarePlan::STATUS_ACTIVE, CarePlan::STATUS_PAYMENT_ATTENTION]);
            $dates = $this->dateValues($message);
            if (! $plan || $dates === []) {
                $this->automatedMessage($ticket, 'Tell me the pause date and optional return date, for example: pause 2026-09-01 and resume 2026-09-15.');

                return true;
            }
            $from = Carbon::parse($dates[0])->startOfDay();
            $resume = isset($dates[1]) ? Carbon::parse($dates[1])->startOfDay() : null;
            $this->issueAction($actor, $ticket, 'regular-care.pause', [
                'care_plan_id' => (int) $plan->id,
                'pause_from' => $from->toDateString(),
                'resume_on' => $resume?->toDateString(),
                'expected_status' => (string) $plan->status,
                'expected_schedule_version' => (int) $plan->schedule_version,
                'expected_updated_at' => $this->stamp($plan->updated_at),
            ], $intentId, 'Pause this recurring care plan?',
                'Affected future generated visits will be suppressed from the pause date. Completed care is unchanged.', [
                    ['label' => 'Plan', 'value' => $plan->displayTitle()],
                    ['label' => 'Pause from', 'value' => $from->format('M j, Y')],
                    ['label' => 'Return date', 'value' => $resume?->format('M j, Y') ?: 'No automatic return date'],
                ], 'Confirm pause');

            return true;
        }

        if ($intentId === 'FAM-REGULAR-021') {
            $plan = $this->selectPlan($actor, $message, [CarePlan::STATUS_PAUSED]);
            if (! $plan) {
                $this->automatedMessage($ticket, 'I could not find one paused recurring care plan to resume.');

                return true;
            }
            $this->issueAction($actor, $ticket, 'regular-care.resume', [
                'care_plan_id' => (int) $plan->id,
                'expected_status' => (string) $plan->status,
                'expected_schedule_version' => (int) $plan->schedule_version,
                'expected_updated_at' => $this->stamp($plan->updated_at),
            ], $intentId, 'Resume this recurring care plan?', 'Upcoming visits will be generated again from the current plan schedule.', [
                ['label' => 'Plan', 'value' => $plan->displayTitle()],
                ['label' => 'Schedule', 'value' => $this->plans->scheduleLabel($plan)],
            ], 'Confirm resume');

            return true;
        }

        if (in_array($intentId, ['FAM-REGULAR-022', 'FAM-REGULAR-023'], true)) {
            $plan = $this->selectPlan($actor, $message, [CarePlan::STATUS_ACTIVE, CarePlan::STATUS_PAYMENT_ATTENTION, CarePlan::STATUS_PAUSED]);
            if (! $plan) {
                $this->automatedMessage($ticket, 'I could not find one live recurring care plan to end.');

                return true;
            }
            $cancelNext = $intentId === 'FAM-REGULAR-023';
            $this->issueAction($actor, $ticket, 'regular-care.end', [
                'care_plan_id' => (int) $plan->id,
                'cancel_next_visit' => $cancelNext,
                'expected_status' => (string) $plan->status,
                'expected_schedule_version' => (int) $plan->schedule_version,
                'expected_updated_at' => $this->stamp($plan->updated_at),
            ], $intentId, 'End this recurring care plan?',
                $cancelNext ? 'Future plan generation stops and the next confirmed visit is also cancelled.' : 'Future plan generation stops. The next already-confirmed visit remains scheduled.', [
                    ['label' => 'Plan', 'value' => $plan->displayTitle()],
                    ['label' => 'Next confirmed visit', 'value' => $cancelNext ? 'Cancel it' : 'Keep it scheduled'],
                ], $cancelNext ? 'Confirm end and cancel next' : 'Confirm end plan');

            return true;
        }

        if ($intentId === 'FAM-REGULAR-025') {
            return $this->prepareMessage($actor, $ticket, $intentId, $message, true);
        }

        return false;
    }

    private function prepareRegularOffer(User $actor, SupportTicket $ticket, string $intentId, string $message, CareRequest $request): bool
    {
        if (! $this->plans->sourceIsEligible($request, $actor)) {
            $this->offerRead($actor, $ticket, 'This request is not currently eligible to start a recurring care offer. Open it to review the caregiver and care details.', $intentId, 'family.request.overview', $request);

            return true;
        }
        $defaults = $this->plans->defaultsFromRequest($request);
        $schedule = $this->weeklyScheduleFromMessage($message);
        $days = $schedule['days'] ?? (array) $defaults['schedule_days'];
        $slots = $schedule['slots'] ?? (array) $defaults['schedule_slots'];
        $startsOn = $schedule['starts_on'] ?? (string) $defaults['starts_on'];
        if ($days === [] || $slots === [] || trim($startsOn) === '') {
            $this->automatedMessage($ticket, 'Tell me the weekdays, start and end time, and start date. Example: Mondays and Thursdays 09:00 to 12:00 starting 2026-09-01.');

            return true;
        }
        if (! $this->available($actor, $ticket, 'regular-care.offer')) {
            $this->offerRead($actor, $ticket, 'Open the recurring care setup to review and send the offer.', $intentId, 'family.request.overview', $request);

            return true;
        }
        $hired = $this->plans->hiredApplicationFor($request);
        $payload = [
            'care_request_id' => (int) $request->id,
            'title' => (string) $defaults['title'],
            'schedule_days' => array_map('intval', $days),
            'schedule_slots' => array_values($slots),
            'starts_on' => $startsOn,
            'ends_on' => (string) ($defaults['ends_on'] ?? '') ?: null,
            'care_notes' => (string) ($defaults['care_notes'] ?? ''),
            'family_message' => $this->messageText($message) ?: (string) ($defaults['family_message'] ?? ''),
            'expected_request_updated_at' => $this->stamp($request->updated_at),
            'expected_application_id' => (int) $hired?->id,
            'expected_application_updated_at' => $this->stamp($hired?->updated_at),
        ];
        $this->issueAction($actor, $ticket, 'regular-care.offer', $payload, $intentId,
            'Send this recurring care offer to '.$hired?->caregiver?->name.'?',
            'The caregiver must accept. The offer does not claim all future visits are booked.', [
                ['label' => 'Caregiver', 'value' => (string) ($hired?->caregiver?->name ?: 'Caregiver')],
                ['label' => 'Schedule', 'value' => $schedule['label'] ?? $this->requestSchedule($request)],
                ['label' => 'Starts', 'value' => Carbon::parse($startsOn)->format('M j, Y')],
                ['label' => 'Family price', 'value' => '$30/hour care* + $1/hour processing fee'],
                ['label' => 'Message', 'value' => $payload['family_message'] ?: 'No optional message'],
            ], 'Confirm and send offer');

        return true;
    }

    private function prepareMessage(User $actor, SupportTicket $ticket, string $intentId, string $message, bool $preferHired = false): bool
    {
        $conversation = $this->selectConversation($actor, $message, $preferHired);
        $body = $this->messageText($message);
        if (! $conversation || $body === '') {
            $this->automatedMessage($ticket, 'Tell me the caregiver name and the exact message to send. I will show a recap before sending.');

            return true;
        }
        if (! $conversation->canSendMessages($actor)) {
            $this->offerRead($actor, $ticket, 'This conversation is read-only in its current application state.', $intentId, 'family.message', $conversation);

            return true;
        }
        if (! $this->available($actor, $ticket, 'caregiver.message')) {
            $this->offerRead($actor, $ticket, 'Open the conversation to send this message.', $intentId, 'family.message', $conversation);

            return true;
        }
        $this->issueAction($actor, $ticket, 'caregiver.message', [
            'conversation_id' => (int) $conversation->id,
            'message' => $body,
            'expected_updated_at' => $this->stamp($conversation->updated_at),
            'expected_application_status' => (string) $conversation->application?->status,
        ], $intentId, 'Send this message to '.$conversation->caregiver?->name.'?',
            'The exact message below will be sent only after confirmation. Sending does not mean it was read.', [
                ['label' => 'Caregiver', 'value' => (string) ($conversation->caregiver?->name ?: 'Caregiver')],
                ['label' => 'Message', 'value' => $body],
            ], 'Confirm and send message');

        return true;
    }

    /** @param array<string,mixed> $preview @param list<array{label:string,value:string}> $fields */
    private function issueAction(
        User $actor,
        SupportTicket $ticket,
        string $tool,
        array $preview,
        string $intentId,
        string $title,
        string $summary,
        array $fields,
        string $confirmLabel,
    ): AiSupportMessageAction {
        if (! $this->available($actor, $ticket, $tool)) {
            throw new AuthorizationException;
        }
        $created = $this->evidence->createPreview(
            $actor, $ticket, self::CAPABILITY, $tool, 'v1', $preview,
            now()->addMinutes((int) config('ai_support.confirmation_validity_minutes', 30)),
        );

        return DB::transaction(function () use ($actor, $ticket, $tool, $preview, $intentId, $title, $summary, $fields, $confirmLabel, $created): AiSupportMessageAction {
            AiSupportMessageAction::query()->where('support_ticket_id', $ticket->id)
                ->where('actor_user_id', $actor->id)
                ->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)
                ->whereNull('consumed_at')->whereNull('invalidated_at')
                ->update(['invalidated_at' => now(), 'invalidation_reason' => 'superseded_domain_recap']);
            $message = $this->automatedMessage($ticket, 'Please review this recap. Nothing changes until you press the confirmation button.');
            $action = AiSupportMessageAction::query()->create([
                'id' => (string) Str::uuid(),
                'support_ticket_message_id' => $message->id,
                'support_ticket_id' => $ticket->id,
                'actor_user_id' => $actor->id,
                'action_type' => AiSupportMessageAction::TYPE_DOMAIN_RECAP,
                'payload' => [
                    'tool_id' => $tool,
                    'intent_id' => $intentId,
                    'title' => $title,
                    'summary' => $summary,
                    'fields' => $fields,
                    'confirm_label' => $confirmLabel,
                    'confirmation_reference' => $created['confirmation_reference'],
                    'idempotency_key' => (string) Str::uuid(),
                    'confirmation_action' => str_replace(['.', '-'], '_', $tool),
                    'renew_payload' => $preview,
                ],
                'expires_at' => now()->addMinutes((int) config('ai_support.confirmation_validity_minutes', 30)),
            ]);
            $this->events->record($ticket, 'intent_action_offered', [
                'support_ticket_message_id' => $message->id,
                'capability_id' => self::CAPABILITY,
                'tool_id' => $tool,
                'tool_version' => 'v1',
                'result_code' => 'explicit_confirmation_issued',
                'safe_metadata' => ['intent_id' => $intentId],
            ], $actor);

            return $action;
        }, 3);
    }

    /** @param array<string,mixed> $payload */
    private function reissue(User $actor, SupportTicket $ticket, array $payload): AiSupportMessageAction
    {
        $tool = (string) ($payload['tool_id'] ?? '');
        $preview = $this->refreshPreview($actor, $tool, (array) ($payload['renew_payload'] ?? []));

        return $this->issueAction(
            $actor, $ticket, $tool, $preview, (string) ($payload['intent_id'] ?? ''),
            (string) ($payload['title'] ?? 'Review action'),
            (string) ($payload['summary'] ?? 'Review the current information before confirming.'),
            array_values((array) ($payload['fields'] ?? [])),
            (string) ($payload['confirm_label'] ?? 'Confirm'),
        );
    }

    /** @param array<string,mixed> $preview @return array<string,mixed> */
    private function refreshPreview(User $actor, string $tool, array $preview): array
    {
        if (isset($preview['application_id'])) {
            $application = $this->ownedApplication($actor, (int) $preview['application_id']);
            $preview['expected_status'] = (string) $application->status;
            $preview['expected_updated_at'] = $this->stamp($application->updated_at);
            $preview['expected_request_status'] = (string) $application->careRequest->status;
            $preview['expected_request_updated_at'] = $this->stamp($application->careRequest->updated_at);
        } elseif (isset($preview['care_booking_id'])) {
            $booking = $this->ownedBooking($actor, (int) $preview['care_booking_id']);
            $preview['expected_status'] = (string) $booking->status;
            $preview['expected_updated_at'] = $this->stamp($booking->updated_at);
            $preview['expected_booking_status'] = (string) $booking->status;
            $preview['expected_booking_updated_at'] = $this->stamp($booking->updated_at);
        } elseif (isset($preview['care_plan_id'])) {
            $plan = $this->ownedPlan($actor, (int) $preview['care_plan_id']);
            $preview['expected_status'] = (string) $plan->status;
            $preview['expected_schedule_version'] = (int) $plan->schedule_version;
            $preview['expected_updated_at'] = $this->stamp($plan->updated_at);
        } elseif (isset($preview['invitation_id'])) {
            $invitation = $this->ownedInvitation($actor, (int) $preview['invitation_id']);
            $preview['expected_status'] = (string) $invitation->status;
            $preview['expected_updated_at'] = $this->stamp($invitation->updated_at);
        } elseif (isset($preview['correction_id'])) {
            $correction = $this->ownedTimeCorrection($actor, (int) $preview['correction_id']);
            $preview['expected_status'] = (string) $correction->status;
            $preview['expected_updated_at'] = $this->stamp($correction->updated_at);
        } elseif (isset($preview['completed_extra_visit_id'])) {
            $extra = $this->ownedCompletedExtraVisit($actor, (int) $preview['completed_extra_visit_id']);
            $preview['expected_status'] = (string) $extra->status;
            $preview['expected_updated_at'] = $this->stamp($extra->updated_at);
        }

        return $preview;
    }

    /** @param array<string,mixed> $preview @return array{outcome_code:string,domain_reference_type:string,domain_reference_id:string,receipt_reference:string} */
    private function commit(User $actor, SupportTicket $ticket, string $tool, array $preview): array
    {
        return match ($tool) {
            'caregiver.invite' => $this->commitInvitation($actor, $preview),
            'caregiver.invitation.cancel' => $this->commitInvitationCancel($actor, $preview),
            'applicant.shortlist', 'applicant.reject', 'applicant.conversation' => $this->commitApplicant($actor, $tool, $preview),
            'caregiver.message' => $this->commitMessage($actor, $preview),
            'caregiver.hire' => $this->commitHire($actor, $preview),
            'visit.change-request' => $this->commitVisitChangeRequest($actor, $preview),
            'visit.change-request.resolve' => $this->commitVisitChangeResolution($actor, $preview),
            'visit.cancel' => $this->commitVisitCancel($actor, $preview),
            'visit.no-show' => $this->commitNoShow($actor, $preview),
            'visit.complete' => $this->commitVisitComplete($actor, $preview),
            'visit.hours.approve' => $this->commitHoursApproval($actor, $preview),
            'visit.time-correction.approve' => $this->commitTimeCorrectionApproval($actor, $preview),
            'visit.time-correction.request-changes' => $this->commitTimeCorrectionChanges($actor, $preview),
            'visit.review' => $this->commitReview($actor, $preview),
            'visit.rebook' => $this->commitRebook($actor, $preview),
            'regular-care.offer' => $this->commitRegularOffer($actor, $preview),
            'regular-care.accept-counter' => $this->commitCounter($actor, $preview),
            'regular-care.schedule-change' => $this->commitRegularScheduleChange($actor, $preview),
            'regular-care.extra-visit' => $this->commitRegularExtraVisit($actor, $preview),
            'regular-care.skip-visit' => $this->commitRegularSkip($actor, $preview),
            'regular-care.pause' => $this->commitRegularPause($actor, $preview),
            'regular-care.resume' => $this->commitRegularResume($actor, $preview),
            'regular-care.end' => $this->commitRegularEnd($actor, $preview),
            'regular-care.extra-visit.approve' => $this->commitExtraVisitApproval($actor, $preview),
            'regular-care.extra-visit.request-changes' => $this->commitExtraVisitChanges($actor, $preview),
            default => throw new AuthorizationException,
        };
    }

    /** @param array<string,mixed> $preview */
    private function commitInvitation(User $actor, array $preview): array
    {
        $request = $this->ownedRequest($actor, (int) ($preview['care_request_id'] ?? 0), true);
        $this->assertState($request, (string) ($preview['expected_request_status'] ?? ''), (string) ($preview['expected_request_updated_at'] ?? ''), 'This request changed. Review a fresh invitation recap.');
        $caregiver = User::query()->with('caregiverProfile')->where('role', 'caregiver')->findOrFail((int) ($preview['caregiver_user_id'] ?? 0));
        $result = $this->invitations->send(
            family: $actor,
            careRequest: $request,
            caregiver: $caregiver,
            message: filled($preview['message'] ?? null) ? (string) $preview['message'] : null,
            reinvite: (bool) ($preview['reinvite'] ?? false),
            source: 'ai_support_confirmed',
        );
        if (! $result->sentNow || ! $result->invitation) {
            throw ValidationException::withMessages(['confirmation' => $result->message]);
        }

        return $this->receipt('invitation_sent_verified', 'care_request_invitation', (int) $result->invitation->id, 'invitation-'.$result->invitation->id.'-'.$result->invitation->status);
    }

    /** @param array<string,mixed> $preview */
    private function commitInvitationCancel(User $actor, array $preview): array
    {
        $invitation = $this->ownedInvitation($actor, (int) ($preview['invitation_id'] ?? 0), true);
        $this->assertState($invitation, (string) ($preview['expected_status'] ?? ''), (string) ($preview['expected_updated_at'] ?? ''), 'This invitation changed. Review it again.');
        if ($invitation->status !== CareRequestInvitation::STATUS_PENDING) {
            throw ValidationException::withMessages(['confirmation' => 'Only a pending invitation can be cancelled.']);
        }
        $invitation->forceFill(['status' => CareRequestInvitation::STATUS_CANCELLED])->save();

        return $this->receipt('invitation_cancelled_verified', 'care_request_invitation', (int) $invitation->id, 'invitation-'.$invitation->id.'-cancelled');
    }

    /** @param array<string,mixed> $preview */
    private function commitApplicant(User $actor, string $tool, array $preview): array
    {
        $application = $this->ownedApplication($actor, (int) ($preview['application_id'] ?? 0), true);
        $this->assertState($application, (string) ($preview['expected_status'] ?? ''), (string) ($preview['expected_updated_at'] ?? ''), 'This applicant changed. Review the current response.');
        if (! in_array($application->status, [CareRequestApplication::STATUS_APPLIED, CareRequestApplication::STATUS_SHORTLISTED], true)) {
            throw ValidationException::withMessages(['confirmation' => 'This applicant can no longer be changed.']);
        }
        if ($tool === 'applicant.shortlist') {
            $application->update(['status' => CareRequestApplication::STATUS_SHORTLISTED]);
            if (! $application->careRequest->first_shortlist_at) {
                $application->careRequest->update(['first_shortlist_at' => now()]);
            }

            return $this->receipt('applicant_shortlisted_verified', 'care_request_application', (int) $application->id, 'application-'.$application->id.'-shortlisted');
        }
        if ($tool === 'applicant.reject') {
            $application->update(['status' => CareRequestApplication::STATUS_REJECTED]);

            return $this->receipt('applicant_rejected_verified', 'care_request_application', (int) $application->id, 'application-'.$application->id.'-rejected');
        }
        if ($application->status === CareRequestApplication::STATUS_APPLIED) {
            $application->update(['status' => CareRequestApplication::STATUS_SHORTLISTED]);
            if (! $application->careRequest->first_shortlist_at) {
                $application->careRequest->update(['first_shortlist_at' => now()]);
            }
        }
        $conversation = CareRequestConversation::findOrCreateForApplication($application->loadMissing('careRequest'), $actor->id);

        return $this->receipt('conversation_started_verified', 'conversation', (int) $conversation->id, 'conversation-'.$conversation->id.'-started');
    }

    /** @param array<string,mixed> $preview */
    private function commitMessage(User $actor, array $preview): array
    {
        $conversation = $this->ownedConversation($actor, (int) ($preview['conversation_id'] ?? 0), true);
        $this->assertUpdated($conversation, (string) ($preview['expected_updated_at'] ?? ''), 'This conversation changed. Review the recipient and message again.');
        if (! $conversation->canSendMessages($actor)) {
            throw ValidationException::withMessages(['confirmation' => 'This conversation is no longer open for messages.']);
        }
        $body = trim((string) ($preview['message'] ?? ''));
        if ($body === '' || mb_strlen($body) > 3000) {
            throw ValidationException::withMessages(['message' => 'The prepared message is empty or too long.']);
        }
        $message = CareRequestMessage::query()->create([
            'care_request_conversation_id' => $conversation->id,
            'sender_user_id' => $actor->id,
            'body' => $body,
        ]);
        $conversation->forceFill(['last_message_at' => now(), 'last_message_sender_id' => $actor->id])->save();
        $conversation->markRead($actor);
        if ($conversation->caregiver) {
            $this->notifications->notify(
                recipients: $conversation->caregiver,
                eventKey: MarketplaceEvent::MESSAGE_RECEIVED,
                title: 'New message',
                body: $actor->name.' sent you a new message.',
                url: route('messages.show', $conversation->id),
                payload: ['conversation_id' => $conversation->id],
                subject: $conversation,
                dedupeKey: 'message:conversation-'.$conversation->id.'-message-'.$message->id,
            );
        }
        FunnelTracker::track('message_sent', $actor, $conversation, ['conversation_id' => $conversation->id, 'source' => 'ai_support_confirmed']);

        return $this->receipt('message_sent_verified', 'conversation', (int) $conversation->id, 'conversation-'.$conversation->id.'-message-'.$message->id);
    }

    /** @param array<string,mixed> $preview */
    private function commitHire(User $actor, array $preview): array
    {
        $application = $this->ownedApplication($actor, (int) ($preview['application_id'] ?? 0), true);
        $this->assertState($application, (string) ($preview['expected_status'] ?? ''), (string) ($preview['expected_updated_at'] ?? ''), 'This applicant changed. Review the hire recap again.');
        $this->assertState($application->careRequest, (string) ($preview['expected_request_status'] ?? ''), (string) ($preview['expected_request_updated_at'] ?? ''), 'This request changed. Review the hire recap again.');
        $result = $this->hiring->hire($actor, $application);
        $id = $result['care_plan_id'] ?: $result['booking']?->id;
        $type = $result['care_plan_id'] ? 'care_plan' : 'care_booking';

        return $this->receipt('caregiver_hired_verified', $type, (int) $id, $type.'-'.$id.'-hired');
    }

    /** @param array<string,mixed> $preview */
    private function commitVisitChangeRequest(User $actor, array $preview): array
    {
        $booking = $this->ownedBooking($actor, (int) ($preview['care_booking_id'] ?? 0), true);
        $this->assertState($booking, (string) ($preview['expected_status'] ?? ''), (string) ($preview['expected_updated_at'] ?? ''), 'This visit changed. Review a fresh change request.');
        if ($booking->status !== CareBooking::STATUS_SCHEDULED) {
            throw ValidationException::withMessages(['confirmation' => 'Visit changes are available only before check-in.']);
        }
        $type = (string) ($preview['type'] ?? '');
        if (! in_array($type, [CareBookingChangeRequest::TYPE_CANCEL, CareBookingChangeRequest::TYPE_RESCHEDULE], true)) {
            throw ValidationException::withMessages(['confirmation' => 'The visit-change type is invalid.']);
        }
        $reason = trim((string) ($preview['reason'] ?? ''));
        if (mb_strlen($reason) < 8) {
            throw ValidationException::withMessages(['confirmation' => 'A clear change reason is required.']);
        }
        $start = filled($preview['proposed_start_at'] ?? null) ? Carbon::parse((string) $preview['proposed_start_at']) : null;
        $end = filled($preview['proposed_end_at'] ?? null) ? Carbon::parse((string) $preview['proposed_end_at']) : null;
        if ($type === CareBookingChangeRequest::TYPE_RESCHEDULE && (! $start || ! $end || ! $start->isFuture() || ! $end->gt($start))) {
            throw ValidationException::withMessages(['confirmation' => 'The proposed visit time is no longer valid.']);
        }
        $change = CareBookingChangeRequest::query()->create([
            'care_booking_id' => $booking->id,
            'requester_user_id' => $actor->id,
            'type' => $type,
            'status' => CareBookingChangeRequest::STATUS_PENDING,
            'reason' => $reason,
            'proposed_start_at' => $start,
            'proposed_end_at' => $end,
        ]);
        $this->trust->recordEvent($booking, $actor->id, 'family', 'booking_change_requested', ['type' => $type, 'reason' => $reason, 'source' => 'ai_support_confirmed']);
        if ($booking->caregiver) {
            $this->notifications->notify(
                recipients: $booking->caregiver,
                eventKey: MarketplaceEvent::BOOKING_CHANGE_REQUESTED,
                title: 'Visit change request',
                body: $actor->name.' requested to '.$type.' this visit.',
                url: route('care-requests.apply', $booking->care_request_id),
                payload: ['care_booking_id' => $booking->id, 'change_type' => $type],
                subject: $change,
            );
        }

        return $this->receipt('visit_change_requested_verified', 'booking_change_request', (int) $change->id, 'booking-change-'.$change->id.'-pending');
    }

    /** @param array<string,mixed> $preview */
    private function commitVisitChangeResolution(User $actor, array $preview): array
    {
        $change = CareBookingChangeRequest::query()->with(['booking.careRequest', 'requester'])
            ->whereKey((int) ($preview['change_request_id'] ?? 0))->lockForUpdate()->firstOrFail();
        $booking = $this->ownedBooking($actor, (int) $change->care_booking_id, true);
        $this->assertState($change, (string) ($preview['expected_status'] ?? ''), (string) ($preview['expected_updated_at'] ?? ''), 'This change request changed. Review it again.');
        $this->assertUpdated($booking, (string) ($preview['expected_booking_updated_at'] ?? ''), 'This visit changed. Review the request again.');
        if ($change->status !== CareBookingChangeRequest::STATUS_PENDING || (int) $change->requester_user_id === (int) $actor->id) {
            throw ValidationException::withMessages(['confirmation' => 'This caregiver change request can no longer be decided here.']);
        }
        $decision = (string) ($preview['decision'] ?? '');
        if ($decision === 'reject') {
            $change->update(['status' => CareBookingChangeRequest::STATUS_REJECTED, 'resolved_at' => now(), 'resolved_by_user_id' => $actor->id]);
            $this->trust->recordEvent($booking, $actor->id, 'family', 'booking_change_rejected', ['change_request_id' => $change->id, 'source' => 'ai_support_confirmed']);

            return $this->receipt('visit_change_rejected_verified', 'booking_change_request', (int) $change->id, 'booking-change-'.$change->id.'-rejected');
        }
        if ($decision !== 'accept') {
            throw ValidationException::withMessages(['confirmation' => 'The change decision is invalid.']);
        }
        $change->update(['status' => CareBookingChangeRequest::STATUS_ACCEPTED, 'resolved_at' => now(), 'resolved_by_user_id' => $actor->id]);
        if ($change->type === CareBookingChangeRequest::TYPE_CANCEL) {
            $late = $this->trust->markLateCancelFlag($booking);
            $booking->update([
                'status' => CareBooking::STATUS_CANCELLED, 'cancelled_at' => now(),
                'cancelled_by_user_id' => $change->requester_user_id,
                'cancellation_reason' => $change->reason, 'late_cancel_flag' => $late,
            ]);
            $this->payments->cancelForBooking($booking);
        } else {
            $booking->update([
                'status' => CareBooking::STATUS_SCHEDULED,
                'scheduled_start_at' => $change->proposed_start_at,
                'scheduled_end_at' => $change->proposed_end_at,
                'last_rescheduled_at' => now(), 'last_reschedule_reason' => $change->reason,
            ]);
        }
        $this->trust->recordEvent($booking, $actor->id, 'family', $change->type === CareBookingChangeRequest::TYPE_CANCEL ? 'booking_cancelled_by_change_request' : 'booking_rescheduled_by_change_request', ['change_request_id' => $change->id, 'source' => 'ai_support_confirmed']);
        $this->trust->recomputeReliabilityForBooking($booking);

        return $this->receipt('visit_change_accepted_verified', 'care_booking', (int) $booking->id, 'booking-'.$booking->id.'-'.$booking->fresh()->status);
    }

    /** @param array<string,mixed> $preview */
    private function commitVisitCancel(User $actor, array $preview): array
    {
        $booking = $this->ownedBooking($actor, (int) ($preview['care_booking_id'] ?? 0), true);
        $this->assertState($booking, (string) ($preview['expected_status'] ?? ''), (string) ($preview['expected_updated_at'] ?? ''), 'This visit changed. Review a fresh cancellation recap.');
        if ($booking->status !== CareBooking::STATUS_SCHEDULED) {
            throw ValidationException::withMessages(['confirmation' => 'Only a scheduled visit can be cancelled.']);
        }
        $reason = trim((string) ($preview['reason'] ?? ''));
        if (mb_strlen($reason) < 8) {
            throw ValidationException::withMessages(['confirmation' => 'A clear cancellation reason is required.']);
        }
        $late = $this->trust->markLateCancelFlag($booking);
        $booking->update([
            'status' => CareBooking::STATUS_CANCELLED, 'cancelled_at' => now(),
            'cancelled_by_user_id' => $actor->id, 'cancellation_reason' => $reason,
            'late_cancel_flag' => $late,
        ]);
        $this->payments->cancelForBooking($booking);
        $this->trust->recordEvent($booking, $actor->id, 'family', 'booking_cancelled_by_family', ['reason' => $reason, 'late_cancel' => $late, 'source' => 'ai_support_confirmed']);
        $this->trust->recomputeReliabilityForBooking($booking);
        if ($booking->caregiver) {
            $this->notifications->notify(
                recipients: $booking->caregiver, eventKey: MarketplaceEvent::SHIFT_CANCELLED,
                title: 'Visit cancelled', body: $actor->name.' cancelled a scheduled visit.',
                url: route('care-requests.apply', $booking->care_request_id),
                payload: ['care_booking_id' => $booking->id, 'care_request_id' => $booking->care_request_id, 'late_cancel' => $late],
                subject: $booking, dedupeKey: 'shift-cancelled:booking-'.$booking->id.'-user-'.$booking->caregiver_user_id,
            );
        }

        return $this->receipt('visit_cancelled_verified', 'care_booking', (int) $booking->id, 'booking-'.$booking->id.'-cancelled');
    }

    /** @param array<string,mixed> $preview */
    private function commitNoShow(User $actor, array $preview): array
    {
        $booking = $this->ownedBooking($actor, (int) ($preview['care_booking_id'] ?? 0), true);
        $this->assertState($booking, (string) ($preview['expected_status'] ?? ''), (string) ($preview['expected_updated_at'] ?? ''), 'This visit changed. Review no-show eligibility again.');
        if ($booking->status !== CareBooking::STATUS_SCHEDULED || ! $booking->scheduled_start_at || now()->lt($booking->scheduled_start_at->copy()->addMinutes(30))) {
            throw ValidationException::withMessages(['confirmation' => 'No-show is not currently available for this visit.']);
        }
        $booking->update([
            'status' => CareBooking::STATUS_CANCELLED, 'cancelled_at' => now(),
            'cancelled_by_user_id' => $actor->id,
            'cancellation_reason' => 'Marked as caregiver no-show by family.', 'no_show_flag' => true,
        ]);
        $this->payments->cancelForBooking($booking);
        $this->trust->recordEvent($booking, $actor->id, 'family', 'caregiver_no_show_marked', ['source' => 'ai_support_confirmed']);
        $this->trust->recomputeReliabilityForBooking($booking);

        return $this->receipt('caregiver_no_show_verified', 'care_booking', (int) $booking->id, 'booking-'.$booking->id.'-no-show');
    }

    /** @param array<string,mixed> $preview */
    private function commitVisitComplete(User $actor, array $preview): array
    {
        $booking = $this->ownedBooking($actor, (int) ($preview['care_booking_id'] ?? 0), true);
        $this->assertState($booking, (string) ($preview['expected_status'] ?? ''), (string) ($preview['expected_updated_at'] ?? ''), 'This visit changed. Review completion again.');
        if (! in_array($booking->status, [CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED], true)) {
            throw ValidationException::withMessages(['confirmation' => 'Only an in-progress or paused visit can be marked complete.']);
        }
        $booking->update([
            'status' => CareBooking::STATUS_COMPLETED, 'completed_at' => now(),
            'timesheet_submitted_at' => $booking->timesheet_submitted_at ?: now(), 'paused_at' => null,
        ]);
        $this->trust->recordEvent($booking, $actor->id, 'family', 'booking_completed_by_family', ['source' => 'ai_support_confirmed']);
        $this->trust->recomputeReliabilityForBooking($booking);

        return $this->receipt('visit_completed_verified', 'care_booking', (int) $booking->id, 'booking-'.$booking->id.'-completed');
    }

    /** @param array<string,mixed> $preview */
    private function commitHoursApproval(User $actor, array $preview): array
    {
        $booking = $this->ownedBooking($actor, (int) ($preview['care_booking_id'] ?? 0), true);
        $this->assertState($booking, (string) ($preview['expected_status'] ?? ''), (string) ($preview['expected_updated_at'] ?? ''), 'The submitted hours changed. Review a fresh recap.');
        if (! in_array($booking->status, [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED], true)
            || ! $booking->timesheet_submitted_at || $booking->family_confirmed_at
            || (int) $booking->worked_minutes !== (int) ($preview['expected_worked_minutes'] ?? -1)) {
            throw ValidationException::withMessages(['confirmation' => 'These submitted hours can no longer be approved from this recap.']);
        }
        $payment = $this->payments->captureForBooking($booking);
        $booking->update(['family_confirmed_at' => now(), 'family_confirmed_by_user_id' => $actor->id]);
        $this->trust->recordEvent($booking, $actor->id, 'family', 'timesheet_confirmed_by_family', ['source' => 'ai_support_confirmed']);
        $this->trust->recomputeReliabilityForBooking($booking);

        return $this->receipt('submitted_hours_approved_verified', 'care_booking', (int) $booking->id, 'booking-'.$booking->id.'-payment-'.$payment->status);
    }

    /** @param array<string,mixed> $preview */
    private function commitTimeCorrectionApproval(User $actor, array $preview): array
    {
        $correction = $this->ownedTimeCorrection($actor, (int) ($preview['correction_id'] ?? 0), true);
        $this->assertState($correction, (string) ($preview['expected_status'] ?? ''), (string) ($preview['expected_updated_at'] ?? ''), 'This correction changed. Review the latest version.');
        if (! hash_equals((string) ($preview['expected_financial_preview'] ?? ''), hash('sha256', json_encode($correction->financial_preview)))) {
            throw ValidationException::withMessages(['confirmation' => 'The financial preview changed. Review the latest correction.']);
        }
        $result = $this->timeCorrections->approve($correction, $actor);

        return $this->receipt('time_correction_approved_verified', 'time_correction', (int) $result->id, 'time-correction-'.$result->id.'-'.$result->status);
    }

    /** @param array<string,mixed> $preview */
    private function commitTimeCorrectionChanges(User $actor, array $preview): array
    {
        $correction = $this->ownedTimeCorrection($actor, (int) ($preview['correction_id'] ?? 0), true);
        $this->assertState($correction, (string) ($preview['expected_status'] ?? ''), (string) ($preview['expected_updated_at'] ?? ''), 'This correction changed. Review the latest version.');
        $result = $this->timeCorrections->requestChanges($correction, $actor, (string) ($preview['reason'] ?? ''));

        return $this->receipt('time_correction_changes_requested_verified', 'time_correction', (int) $result->id, 'time-correction-'.$result->id.'-changes-requested');
    }

    /** @param array<string,mixed> $preview */
    private function commitReview(User $actor, array $preview): array
    {
        $booking = $this->ownedBooking($actor, (int) ($preview['care_booking_id'] ?? 0), true);
        $this->assertState($booking, (string) ($preview['expected_status'] ?? ''), (string) ($preview['expected_updated_at'] ?? ''), 'This visit changed. Review the rating again.');
        if (! in_array($booking->status, [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED], true)) {
            throw ValidationException::withMessages(['confirmation' => 'This visit is not eligible for a review.']);
        }
        $rating = (int) ($preview['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            throw ValidationException::withMessages(['confirmation' => 'Rating must be from 1 to 5.']);
        }
        if (! $booking->family_confirmed_at) {
            $this->payments->captureForBooking($booking);
        }
        $review = CareReview::query()->updateOrCreate(
            ['care_booking_id' => $booking->id, 'reviewer_user_id' => $actor->id],
            [
                'care_request_id' => $booking->care_request_id,
                'reviewee_user_id' => $booking->caregiver_user_id,
                'rating' => $rating,
                'comment' => filled($preview['comment'] ?? null) ? trim((string) $preview['comment']) : null,
            ],
        );
        $profile = $booking->caregiver?->caregiverProfile;
        if ($profile) {
            $profile->update([
                'average_rating' => round((float) CareReview::query()->where('reviewee_user_id', $booking->caregiver_user_id)->avg('rating'), 2),
                'reviews_count' => CareReview::query()->where('reviewee_user_id', $booking->caregiver_user_id)->count(),
            ]);
        }
        $booking->update([
            'status' => CareBooking::STATUS_REVIEWED, 'reviewed_at' => now(),
            'family_confirmed_at' => $booking->family_confirmed_at ?: now(),
            'family_confirmed_by_user_id' => $booking->family_confirmed_by_user_id ?: $actor->id,
        ]);
        $this->trust->recordEvent($booking, $actor->id, 'family', 'review_submitted_by_family', ['rating' => $rating, 'source' => 'ai_support_confirmed']);
        $this->trust->recomputeReliabilityForBooking($booking);

        return $this->receipt('care_review_submitted_verified', 'care_review', (int) $review->id, 'care-review-'.$review->id.'-'.$rating.'-stars');
    }

    /** @param array<string,mixed> $preview */
    private function commitRebook(User $actor, array $preview): array
    {
        $source = $this->ownedRequest($actor, (int) ($preview['source_care_request_id'] ?? 0), true);
        $booking = $source->booking()->lockForUpdate()->firstOrFail();
        $this->assertUpdated($booking, (string) ($preview['expected_booking_updated_at'] ?? ''), 'The earlier visit changed. Review the rebook recap.');
        if ((int) $booking->caregiver_user_id !== (int) ($preview['caregiver_user_id'] ?? 0)) {
            throw ValidationException::withMessages(['confirmation' => 'The hired caregiver changed. Review the current visit.']);
        }
        $start = Carbon::parse((string) ($preview['start_at'] ?? ''));
        $end = Carbon::parse((string) ($preview['end_at'] ?? ''));
        if (! $start->isFuture() || ! $end->gt($start)) {
            throw ValidationException::withMessages(['confirmation' => 'The new visit schedule is no longer valid.']);
        }
        $source->loadMissing(['recipient', 'thirdPartyContact', 'tasks']);
        $attributes = $source->only([
            'title', 'additional_info', 'scope_of_work', 'time_expectations', 'home_access_notes',
            'preferred_response_hours', 'budget_min', 'budget_max', 'address_line1', 'address_line2',
            'city', 'state', 'zip', 'lat', 'lng',
        ]);
        $attributes = array_merge($attributes, [
            ...$this->familyAccounts->ownershipAttributes($actor),
            'created_by_user_id' => $actor->id,
            'title' => 'Rebook: '.$source->title,
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => $start,
            'requested_end_at' => $end,
        ]);
        $newRequest = CareRequest::query()->create($attributes);
        if ($source->recipient) {
            $newRequest->recipient()->create($source->recipient->only(['recipient_is_requester', 'full_name', 'date_of_birth', 'gender', 'mobility_level', 'relationship_to_family', 'care_notes']));
        }
        if ($source->thirdPartyContact) {
            $newRequest->thirdPartyContact()->create($source->thirdPartyContact->only(['full_name', 'relationship_to_recipient', 'phone', 'email']));
        }
        $newRequest->tasks()->sync($source->tasks->mapWithKeys(fn ($task) => [$task->id => ['task_note' => $task->pivot?->task_note]])->all());
        $caregiver = User::query()->with('caregiverProfile')->findOrFail((int) $preview['caregiver_user_id']);
        $result = $this->invitations->send($actor, $newRequest, $caregiver, 'Rebooking request based on previous completed care.', false, 'ai_support_rebook');
        if (! $result->sentNow) {
            throw ValidationException::withMessages(['confirmation' => $result->message]);
        }

        return $this->receipt('rebook_request_created_verified', 'care_request', (int) $newRequest->id, 'care-request-'.$newRequest->id.'-rebook-invited');
    }

    /** @param array<string,mixed> $preview */
    private function commitRegularOffer(User $actor, array $preview): array
    {
        $request = $this->ownedRequest($actor, (int) ($preview['care_request_id'] ?? 0), true);
        $this->assertUpdated($request, (string) ($preview['expected_request_updated_at'] ?? ''), 'This request changed. Review the recurring care offer.');
        $application = $this->plans->hiredApplicationFor($request);
        if (! $application || (int) $application->id !== (int) ($preview['expected_application_id'] ?? 0)
            || ! hash_equals($this->stamp($application->updated_at), (string) ($preview['expected_application_updated_at'] ?? ''))) {
            throw ValidationException::withMessages(['confirmation' => 'The hired caregiver changed. Review the recurring care offer again.']);
        }
        $slots = array_values((array) ($preview['schedule_slots'] ?? []));
        $plan = $this->plans->sendOfferFromRequest($request, $actor, [
            'title' => (string) ($preview['title'] ?? ''),
            'schedule_days' => array_map('intval', (array) ($preview['schedule_days'] ?? [])),
            'schedule_start_time' => (string) data_get($slots, '0.start_time', ''),
            'schedule_end_time' => (string) data_get($slots, '0.end_time', ''),
            'schedule_slots' => $slots,
            'starts_on' => (string) ($preview['starts_on'] ?? ''),
            'ends_on' => filled($preview['ends_on'] ?? null) ? (string) $preview['ends_on'] : null,
            'care_notes' => (string) ($preview['care_notes'] ?? ''),
            'family_message' => (string) ($preview['family_message'] ?? ''),
        ]);

        return $this->receipt('regular_care_offer_sent_verified', 'care_plan', (int) $plan->id, 'care-plan-'.$plan->id.'-offer-sent');
    }

    /** @param array<string,mixed> $preview */
    private function commitCounter(User $actor, array $preview): array
    {
        $plan = $this->ownedPlan($actor, (int) ($preview['care_plan_id'] ?? 0), true);
        $this->assertPlanState($plan, $preview, 'This counteroffer changed. Review it again.');
        $result = $this->plans->acceptCounter($plan, $actor);

        return $this->receipt('regular_counter_accepted_verified', 'care_plan', (int) $result->id, 'care-plan-'.$result->id.'-'.$result->status);
    }

    /** @param array<string,mixed> $preview */
    private function commitRegularScheduleChange(User $actor, array $preview): array
    {
        $plan = $this->ownedPlan($actor, (int) ($preview['care_plan_id'] ?? 0), true);
        $this->assertPlanState($plan, $preview, 'This recurring care plan changed. Review the schedule again.');
        $slots = array_values((array) ($preview['schedule_slots'] ?? []));
        $change = $this->plans->requestScheduleChange($plan, $actor, [
            'schedule_days' => array_map('intval', (array) ($preview['schedule_days'] ?? [])),
            'schedule_start_time' => (string) data_get($slots, '0.start_time', ''),
            'schedule_end_time' => (string) data_get($slots, '0.end_time', ''),
            'schedule_slots' => $slots,
            'starts_on' => (string) ($preview['starts_on'] ?? ''),
            'effective_on' => (string) ($preview['starts_on'] ?? ''),
            'ends_on' => $plan->ends_on?->toDateString(),
            'note' => (string) ($preview['note'] ?? ''),
        ]);

        return $this->receipt('regular_schedule_change_sent_verified', 'care_plan_schedule_change', (int) $change->id, 'care-plan-change-'.$change->id.'-pending');
    }

    /** @param array<string,mixed> $preview */
    private function commitRegularExtraVisit(User $actor, array $preview): array
    {
        $plan = $this->ownedPlan($actor, (int) ($preview['care_plan_id'] ?? 0), true);
        $this->assertPlanState($plan, $preview, 'This recurring care plan changed. Review the extra visit again.');
        $change = $this->plans->requestExtraVisit(
            $plan, $actor, Carbon::parse((string) $preview['start_at']), Carbon::parse((string) $preview['end_at']),
            filled($preview['note'] ?? null) ? (string) $preview['note'] : null,
        );

        return $this->receipt('regular_extra_visit_requested_verified', 'care_plan_schedule_change', (int) $change->id, 'care-plan-extra-'.$change->id.'-pending');
    }

    /** @param array<string,mixed> $preview */
    private function commitRegularSkip(User $actor, array $preview): array
    {
        $plan = $this->ownedPlan($actor, (int) ($preview['care_plan_id'] ?? 0), true);
        $booking = $this->ownedBooking($actor, (int) ($preview['care_booking_id'] ?? 0), true);
        if ((int) $booking->care_plan_id !== (int) $plan->id
            || ! hash_equals($this->stamp($booking->updated_at), (string) ($preview['expected_booking_updated_at'] ?? ''))
            || ! hash_equals((string) $booking->status, (string) ($preview['expected_booking_status'] ?? ''))) {
            throw ValidationException::withMessages(['confirmation' => 'This recurring care visit changed. Review it again.']);
        }
        $result = $this->plans->skipVisit($plan, $booking, $actor);

        return $this->receipt('regular_visit_skipped_verified', 'care_booking', (int) $result->id, 'booking-'.$result->id.'-skipped');
    }

    /** @param array<string,mixed> $preview */
    private function commitRegularPause(User $actor, array $preview): array
    {
        $plan = $this->ownedPlan($actor, (int) ($preview['care_plan_id'] ?? 0), true);
        $this->assertPlanState($plan, $preview, 'This recurring care plan changed. Review the pause again.');
        $result = $this->plans->pausePlan(
            $plan, $actor, Carbon::parse((string) $preview['pause_from'])->startOfDay(),
            filled($preview['resume_on'] ?? null) ? Carbon::parse((string) $preview['resume_on'])->startOfDay() : null,
        );

        return $this->receipt('regular_care_paused_verified', 'care_plan', (int) $result->id, 'care-plan-'.$result->id.'-paused');
    }

    /** @param array<string,mixed> $preview */
    private function commitRegularResume(User $actor, array $preview): array
    {
        $plan = $this->ownedPlan($actor, (int) ($preview['care_plan_id'] ?? 0), true);
        $this->assertPlanState($plan, $preview, 'This recurring care plan changed. Review resume again.');
        $result = $this->plans->resumePlan($plan, $actor);

        return $this->receipt('regular_care_resumed_verified', 'care_plan', (int) $result->id, 'care-plan-'.$result->id.'-'.$result->status);
    }

    /** @param array<string,mixed> $preview */
    private function commitRegularEnd(User $actor, array $preview): array
    {
        $plan = $this->ownedPlan($actor, (int) ($preview['care_plan_id'] ?? 0), true);
        $this->assertPlanState($plan, $preview, 'This recurring care plan changed. Review ending it again.');
        $result = $this->plans->endPlan($plan, $actor, (bool) ($preview['cancel_next_visit'] ?? false));

        return $this->receipt('regular_care_ended_verified', 'care_plan', (int) $result->id, 'care-plan-'.$result->id.'-ended');
    }

    /** @param array<string,mixed> $preview */
    private function commitExtraVisitApproval(User $actor, array $preview): array
    {
        $extra = $this->ownedCompletedExtraVisit($actor, (int) ($preview['completed_extra_visit_id'] ?? 0), true);
        $this->assertState($extra, (string) ($preview['expected_status'] ?? ''), (string) ($preview['expected_updated_at'] ?? ''), 'This extra-visit report changed. Review it again.');
        if (! hash_equals((string) ($preview['expected_financial_preview'] ?? ''), hash('sha256', json_encode($extra->financial_preview)))) {
            throw ValidationException::withMessages(['confirmation' => 'The extra-visit financial preview changed. Review it again.']);
        }
        $result = $this->completedExtraVisits->approve($extra, $actor);

        return $this->receipt('completed_extra_visit_approved_verified', 'completed_extra_visit', (int) $result->id, 'completed-extra-'.$result->id.'-'.$result->status);
    }

    /** @param array<string,mixed> $preview */
    private function commitExtraVisitChanges(User $actor, array $preview): array
    {
        $extra = $this->ownedCompletedExtraVisit($actor, (int) ($preview['completed_extra_visit_id'] ?? 0), true);
        $this->assertState($extra, (string) ($preview['expected_status'] ?? ''), (string) ($preview['expected_updated_at'] ?? ''), 'This extra-visit report changed. Review it again.');
        $result = $this->completedExtraVisits->requestChanges($extra, $actor, (string) ($preview['reason'] ?? ''));

        return $this->receipt('completed_extra_visit_changes_requested_verified', 'completed_extra_visit', (int) $result->id, 'completed-extra-'.$result->id.'-changes-requested');
    }

    /** @param list<string>|null $statuses */
    private function selectRequest(User $actor, string $message, ?array $statuses = null): ?CareRequest
    {
        $query = CareRequest::query()->forFamilyAccount($this->familyAccounts->account($actor))
            ->with(['recipient', 'tasks', 'booking.caregiver', 'applications.caregiver.caregiverProfile']);
        if ($statuses) {
            $query->whereIn('status', $statuses);
        }
        if ($id = $this->namedId($message, 'request')) {
            return $query->whereKey($id)->first();
        }

        return $query->orderByRaw("CASE WHEN status = 'open' THEN 0 WHEN status = 'filled' THEN 1 ELSE 2 END")
            ->latest('updated_at')->first();
    }

    private function selectCaregiver(string $message): ?User
    {
        $lower = mb_strtolower($message);
        $matches = User::query()->where('role', 'caregiver')->with('caregiverProfile')->orderByDesc('updated_at')->limit(200)->get()
            ->filter(function (User $user) use ($lower): bool {
                $name = mb_strtolower(trim((string) $user->name));
                $first = trim((string) Str::of($name)->before(' '));

                return $name !== '' && (str_contains($lower, $name) || (mb_strlen($first) >= 3 && preg_match('/\b'.preg_quote($first, '/').'\b/u', $lower) === 1));
            });

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function selectApplication(User $actor, string $message): ?CareRequestApplication
    {
        $account = $this->familyAccounts->account($actor);
        $query = CareRequestApplication::query()->whereHas('careRequest', fn ($q) => $q->forFamilyAccount($account))
            ->with(['careRequest', 'caregiver.caregiverProfile', 'conversation']);
        if ($id = $this->namedId($message, 'application')) {
            return $query->whereKey($id)->first();
        }
        if ($requestId = $this->namedId($message, 'request')) {
            $query->where('care_request_id', $requestId);
        }
        $candidates = $query->whereIn('status', [CareRequestApplication::STATUS_APPLIED, CareRequestApplication::STATUS_SHORTLISTED])
            ->latest('updated_at')->limit(30)->get();
        $named = $this->filterByCaregiverName($candidates, $message);

        return $named->count() === 1 ? $named->first() : ($candidates->count() === 1 ? $candidates->first() : null);
    }

    private function selectInvitation(User $actor, string $message, ?CareRequest $request = null): ?CareRequestInvitation
    {
        $query = CareRequestInvitation::query()->forFamilyAccount($this->familyAccounts->account($actor))
            ->with(['careRequest', 'caregiver']);
        if ($id = $this->namedId($message, 'invitation')) {
            return $query->whereKey($id)->first();
        }
        if ($request) {
            $query->where('care_request_id', $request->id);
        }
        $candidates = $query->latest('updated_at')->limit(30)->get();
        $named = $this->filterByCaregiverName($candidates, $message);

        return $named->count() === 1 ? $named->first() : ($candidates->count() === 1 ? $candidates->first() : null);
    }

    private function selectConversation(User $actor, string $message, bool $preferHired = false): ?CareRequestConversation
    {
        $query = CareRequestConversation::query()->forUser($actor)
            ->with(['careRequest', 'caregiver', 'application']);
        if ($id = $this->namedId($message, 'conversation')) {
            return $query->whereKey($id)->first();
        }
        $candidates = $query->when($preferHired, fn ($q) => $q->whereHas('application', fn ($application) => $application->where('status', CareRequestApplication::STATUS_HIRED)))
            ->orderByDesc('last_message_at')->latest('updated_at')->limit(30)->get();
        $named = $this->filterByCaregiverName($candidates, $message);

        return $named->count() === 1 ? $named->first() : ($candidates->count() === 1 ? $candidates->first() : null);
    }

    /** @param list<string>|null $statuses */
    private function selectBooking(User $actor, string $message, ?array $statuses = null): ?CareBooking
    {
        $query = CareBooking::query()->forFamilyAccount($this->familyAccounts->account($actor))
            ->with(['careRequest.recipient', 'careRequest.tasks', 'caregiver.caregiverProfile', 'payment', 'latestTimeCorrection', 'taskChecks']);
        if ($statuses) {
            $query->whereIn('status', $statuses);
        }
        if ($id = $this->namedId($message, 'booking')) {
            return $query->whereKey($id)->first();
        }
        if ($requestId = $this->namedId($message, 'request')) {
            return $query->where('care_request_id', $requestId)->first();
        }
        $candidates = $query->orderByRaw("CASE WHEN status = 'in_progress' THEN 0 WHEN status = 'paused' THEN 1 WHEN status = 'scheduled' THEN 2 ELSE 3 END")
            ->orderByRaw('CASE WHEN scheduled_start_at >= ? THEN 0 ELSE 1 END', [now()])
            ->orderBy('scheduled_start_at')->limit(30)->get();
        $named = $this->filterByCaregiverName($candidates, $message);

        return $named->count() === 1 ? $named->first() : $candidates->first();
    }

    private function selectCurrentOrUpcomingBooking(User $actor, string $message): ?CareBooking
    {
        $query = CareBooking::query()->forFamilyAccount($this->familyAccounts->account($actor))
            ->with(['careRequest.recipient', 'careRequest.tasks', 'caregiver.caregiverProfile', 'payment', 'latestTimeCorrection', 'taskChecks'])
            ->where(function ($candidate): void {
                $candidate->whereIn('status', [CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED])
                    ->orWhere(function ($scheduled): void {
                        $scheduled->where('status', CareBooking::STATUS_SCHEDULED)
                            ->where('scheduled_start_at', '>=', now()->subHours(2));
                    });
            });
        if ($id = $this->namedId($message, 'booking')) {
            return $query->whereKey($id)->first();
        }
        if ($requestId = $this->namedId($message, 'request')) {
            return $query->where('care_request_id', $requestId)->orderBy('scheduled_start_at')->first();
        }

        $candidates = $query
            ->orderByRaw("CASE WHEN status = 'in_progress' THEN 0 WHEN status = 'paused' THEN 1 ELSE 2 END")
            ->orderBy('scheduled_start_at')
            ->limit(30)
            ->get();
        $named = $this->filterByCaregiverName($candidates, $message);

        return $named->count() === 1 ? $named->first() : $candidates->first();
    }

    private function selectUnapprovedSubmittedHours(User $actor, string $message): ?CareBooking
    {
        $query = CareBooking::query()->forFamilyAccount($this->familyAccounts->account($actor))
            ->with(['careRequest.recipient', 'careRequest.tasks', 'caregiver.caregiverProfile', 'payment', 'latestTimeCorrection', 'taskChecks'])
            ->whereIn('status', [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED])
            ->whereNotNull('timesheet_submitted_at')
            ->whereNull('family_confirmed_at');
        if ($id = $this->namedId($message, 'booking')) {
            return $query->whereKey($id)->first();
        }
        if ($requestId = $this->namedId($message, 'request')) {
            return $query->where('care_request_id', $requestId)->latest('timesheet_submitted_at')->first();
        }

        $candidates = $query->latest('timesheet_submitted_at')->limit(30)->get();
        $named = $this->filterByCaregiverName($candidates, $message);

        return $named->count() === 1 ? $named->first() : $candidates->first();
    }

    private function selectPendingBookingChange(User $actor, string $message): ?CareBookingChangeRequest
    {
        $account = $this->familyAccounts->account($actor);
        $query = CareBookingChangeRequest::query()->where('status', CareBookingChangeRequest::STATUS_PENDING)
            ->whereHas('booking', fn ($q) => $q->forFamilyAccount($account))
            ->where('requester_user_id', '!=', $actor->id)
            ->with(['booking.careRequest', 'booking.caregiver', 'requester']);
        if ($id = $this->namedId($message, 'change')) {
            return $query->whereKey($id)->first();
        }

        return $query->latest()->first();
    }

    private function selectTimeCorrection(User $actor, string $message): ?CareBookingTimeCorrection
    {
        $query = CareBookingTimeCorrection::query()->forFamilyAccount($this->familyAccounts->account($actor))
            ->with(['booking.careRequest', 'booking.caregiver']);
        if ($id = $this->namedId($message, 'correction')) {
            return $query->whereKey($id)->first();
        }

        return $query->whereIn('status', CareBookingTimeCorrection::activeStatuses())->latest('version')->first();
    }

    /** @param list<string>|null $statuses */
    private function selectPlan(User $actor, string $message, ?array $statuses = null): ?CarePlan
    {
        $query = CarePlan::query()->forFamilyAccount($this->familyAccounts->account($actor))
            ->with(['caregiver', 'nextBooking.careRequest', 'generatedBookings.careRequest', 'pendingScheduleChanges']);
        if ($statuses) {
            $query->whereIn('status', $statuses);
        }
        if ($id = $this->namedId($message, 'plan')) {
            return $query->whereKey($id)->first();
        }
        $candidates = $query->orderByRaw("CASE WHEN status = 'countered' THEN 0 WHEN status = 'payment_attention' THEN 1 WHEN status = 'active' THEN 2 WHEN status = 'paused' THEN 3 ELSE 4 END")
            ->latest('updated_at')->limit(30)->get();
        $named = $this->filterByCaregiverName($candidates, $message);

        return $named->count() === 1 ? $named->first() : $candidates->first();
    }

    private function selectPlanBooking(CarePlan $plan, string $message): ?CareBooking
    {
        $query = $plan->generatedBookings()->where('status', CareBooking::STATUS_SCHEDULED)->where('scheduled_start_at', '>=', now());
        if ($id = $this->namedId($message, 'booking')) {
            return $query->whereKey($id)->first();
        }

        return $query->orderBy('scheduled_start_at')->first();
    }

    /** @param list<string>|null $statuses */
    private function selectCompletedExtraVisit(User $actor, string $message, ?array $statuses = null): ?CompletedExtraVisitRequest
    {
        $query = CompletedExtraVisitRequest::query()->forFamilyAccount($this->familyAccounts->account($actor))->with(['plan.caregiver']);
        if ($statuses) {
            $query->whereIn('status', $statuses);
        }
        if ($id = $this->namedId($message, 'extra')) {
            return $query->whereKey($id)->first();
        }

        return $query->current()->latest('version')->first();
    }

    private function ownedRequest(User $actor, int $id, bool $lock = false): CareRequest
    {
        return CareRequest::query()->forFamilyAccount($this->familyAccounts->account($actor))
            ->when($lock, fn ($q) => $q->lockForUpdate())->with(['booking', 'recipient', 'thirdPartyContact', 'tasks'])->findOrFail($id);
    }

    private function ownedApplication(User $actor, int $id, bool $lock = false): CareRequestApplication
    {
        return CareRequestApplication::query()->whereHas('careRequest', fn ($q) => $q->forFamilyAccount($this->familyAccounts->account($actor)))
            ->when($lock, fn ($q) => $q->lockForUpdate())->with(['careRequest', 'caregiver.caregiverProfile'])->findOrFail($id);
    }

    private function ownedInvitation(User $actor, int $id, bool $lock = false): CareRequestInvitation
    {
        return CareRequestInvitation::query()->forFamilyAccount($this->familyAccounts->account($actor))
            ->when($lock, fn ($q) => $q->lockForUpdate())->with(['careRequest', 'caregiver'])->findOrFail($id);
    }

    private function ownedConversation(User $actor, int $id, bool $lock = false): CareRequestConversation
    {
        return CareRequestConversation::query()->forUser($actor)->when($lock, fn ($q) => $q->lockForUpdate())
            ->with(['careRequest', 'caregiver', 'application'])->findOrFail($id);
    }

    private function ownedBooking(User $actor, int $id, bool $lock = false): CareBooking
    {
        return CareBooking::query()->forFamilyAccount($this->familyAccounts->account($actor))
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->with(['careRequest.recipient', 'careRequest.tasks', 'caregiver.caregiverProfile', 'payment', 'latestTimeCorrection', 'taskChecks'])->findOrFail($id);
    }

    private function ownedTimeCorrection(User $actor, int $id, bool $lock = false): CareBookingTimeCorrection
    {
        return CareBookingTimeCorrection::query()->forFamilyAccount($this->familyAccounts->account($actor))
            ->when($lock, fn ($q) => $q->lockForUpdate())->with(['booking.careRequest'])->findOrFail($id);
    }

    private function ownedPlan(User $actor, int $id, bool $lock = false): CarePlan
    {
        return CarePlan::query()->forFamilyAccount($this->familyAccounts->account($actor))
            ->when($lock, fn ($q) => $q->lockForUpdate())->with(['caregiver', 'nextBooking', 'generatedBookings'])->findOrFail($id);
    }

    private function ownedCompletedExtraVisit(User $actor, int $id, bool $lock = false): CompletedExtraVisitRequest
    {
        return CompletedExtraVisitRequest::query()->forFamilyAccount($this->familyAccounts->account($actor))
            ->when($lock, fn ($q) => $q->lockForUpdate())->with(['plan.caregiver'])->findOrFail($id);
    }

    /** @param iterable<int,mixed> $records */
    private function filterByCaregiverName(iterable $records, string $message)
    {
        $lower = mb_strtolower($message);

        return collect($records)->filter(function ($record) use ($lower): bool {
            $name = mb_strtolower(trim((string) ($record->caregiver?->name ?? $record->booking?->caregiver?->name ?? $record->plan?->caregiver?->name ?? '')));
            if ($name === '') {
                return false;
            }
            $first = trim((string) Str::of($name)->before(' '));

            return str_contains($lower, $name) || (mb_strlen($first) >= 3 && preg_match('/\b'.preg_quote($first, '/').'\b/u', $lower) === 1);
        })->values();
    }

    private function applicationSummary(CareRequestApplication $application): string
    {
        $profile = $application->caregiver?->caregiverProfile;
        $facts = [
            $application->caregiver?->name ?: 'Caregiver',
            str_replace('_', ' ', (string) $application->status),
        ];
        if ($profile?->years_experience !== null) {
            $facts[] = $profile->years_experience.' years experience';
        }
        if ((int) $profile?->reviews_count > 0) {
            $facts[] = number_format((float) $profile->average_rating, 1).'/5 from '.$profile->reviews_count.' reviews';
        }
        if ($profile?->hasIdentityVerifiedBadge()) {
            $facts[] = 'identity verified';
        }
        if ($profile?->hasBackgroundCheckBadge()) {
            $facts[] = 'background check badge';
        }
        if ($profile?->reliability_score !== null) {
            $facts[] = 'reliability '.number_format((float) $profile->reliability_score, 0).'%';
        }

        return '- '.implode(' · ', $facts);
    }

    private function bookingSummary(CareBooking $booking, bool $details = false): string
    {
        $body = ($booking->caregiver?->name ?: 'Your caregiver').' has a '.str_replace('_', ' ', $booking->status).' visit '.$this->bookingTime($booking).'.';
        if ($details) {
            $recipient = $booking->careRequest?->recipient?->full_name;
            if ($recipient) {
                $body .= ' Care receiver: '.$recipient.'.';
            }
            $location = collect([$booking->careRequest?->city, $booking->careRequest?->state])->filter()->implode(', ');
            if ($location !== '') {
                $body .= ' Location: '.$location.'.';
            }
            $tasks = $booking->careRequest?->tasks?->pluck('name')->filter()->take(5)->implode(', ');
            if ($tasks) {
                $body .= ' Requested care: '.$tasks.'.';
            }
            if ($booking->status === CareBooking::STATUS_SCHEDULED) {
                $body .= $booking->started_at ? ' Caregiver check-in is recorded.' : ' Caregiver check-in is not recorded yet.';
            }
        }

        return $body;
    }

    private function hoursSummary(CareBooking $booking): string
    {
        $body = $this->bookingSummary($booking).($booking->timesheet_submitted_at ? ' Submitted hours are recorded.' : ' No submitted hours are recorded yet.');
        if ($booking->started_at && $booking->completed_at) {
            $body .= ' Recorded time: '.$booking->started_at->format('M j, g:i A').'–'.$booking->completed_at->format('g:i A').'.';
        }
        if ((int) $booking->worked_minutes > 0) {
            $body .= ' Worked duration: '.$this->duration((int) $booking->worked_minutes).'.';
        }
        if ($booking->latestTimeCorrection) {
            $body .= ' Latest correction: '.$booking->latestTimeCorrection->statusLabel().', '.$booking->latestTimeCorrection->durationLabel().'.';
        }
        if ($booking->payment) {
            $body .= ' Payment state: '.str_replace('_', ' ', $booking->payment->status).'.';
        }

        return $body;
    }

    private function planSummary(CarePlan $plan): string
    {
        $body = $plan->displayTitle().' with '.($plan->caregiver?->name ?: 'the caregiver').' is '.str_replace('_', ' ', $plan->status).'. Schedule: '.$this->plans->scheduleLabel($plan).'.';
        $next = $plan->nextBooking ?: $plan->generatedBookings->where('status', CareBooking::STATUS_SCHEDULED)->sortBy('scheduled_start_at')->first();
        if ($next) {
            $body .= ' Next generated visit: '.$this->bookingTime($next).'.';
        }
        if ($plan->status === CarePlan::STATUS_COUNTERED) {
            $body .= ' Caregiver counter: '.$this->plans->scheduleLabel($plan, true).'.';
        }

        return $body;
    }

    private function completedExtraVisitSummary(CompletedExtraVisitRequest $extra): string
    {
        $body = 'Completed extra visit with '.($extra->caregiver?->name ?: $extra->plan?->caregiver?->name ?: 'the caregiver').': ';
        $body .= $extra->proposed_started_at?->format('M j, g:i A').'–'.$extra->proposed_completed_at?->format('g:i A').', '.$extra->durationLabel().'. ';
        $body .= 'Status: '.$extra->statusLabel().'. Family amount: $'.number_format((int) data_get($extra->financial_preview, 'target_charge_cents', 0) / 100, 2).'.';

        return $body;
    }

    private function bookingChangeSummary(CareBookingChangeRequest $change): string
    {
        if ($change->type === CareBookingChangeRequest::TYPE_CANCEL) {
            return 'cancel the visit';
        }

        return 'reschedule to '.$change->proposed_start_at?->format('D, M j, Y g:i A').'–'.$change->proposed_end_at?->format('g:i A');
    }

    private function requestLabel(CareRequest $request): string
    {
        return ($request->title ?: 'Care request').' (#'.$request->id.')';
    }

    private function requestSchedule(CareRequest $request): string
    {
        if ($request->request_type === CareRequest::TYPE_ONE_TIME) {
            return $request->requested_start_at
                ? $request->requested_start_at->format('D, M j, Y g:i A').'–'.($request->requested_end_at?->format('g:i A') ?: 'end time not set')
                : 'One-time schedule not set';
        }

        return $request->recurringScheduleLabel() ?: 'Regular schedule';
    }

    private function bookingTime(CareBooking $booking): string
    {
        if (! $booking->scheduled_start_at) {
            return 'with no scheduled start recorded';
        }

        return 'on '.$booking->scheduled_start_at->format('D, M j, Y').' from '.$booking->scheduled_start_at->format('g:i A').' to '.($booking->scheduled_end_at?->format('g:i A') ?: 'an unrecorded end time');
    }

    private function invitationStatus(CareRequestInvitation $invitation): string
    {
        return $invitation->isExpired() ? CareRequestInvitation::STATUS_EXPIRED : str_replace('_', ' ', $invitation->status);
    }

    private function duration(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        return trim(($hours ? $hours.' hr'.($hours === 1 ? '' : 's') : '').($remainder ? ' '.$remainder.' min' : '')) ?: '0 min';
    }

    private function namedId(string $message, string $label): ?int
    {
        return preg_match('/\b'.preg_quote($label, '/').'\s*#?\s*(\d+)\b/i', $message, $matches) === 1
            ? (int) $matches[1]
            : null;
    }

    /** @return array{0:Carbon,1:Carbon}|null */
    private function dateTimeRange(string $message): ?array
    {
        if (preg_match_all('/\b(20\d{2}-\d{2}-\d{2})[ T](\d{1,2}:\d{2})\b/', $message, $matches, PREG_SET_ORDER) < 2) {
            return null;
        }
        try {
            $start = Carbon::createFromFormat('!Y-m-d H:i', $matches[0][1].' '.str_pad($matches[0][2], 5, '0', STR_PAD_LEFT));
            $end = Carbon::createFromFormat('!Y-m-d H:i', $matches[1][1].' '.str_pad($matches[1][2], 5, '0', STR_PAD_LEFT));
        } catch (Throwable) {
            return null;
        }

        return $start && $end && $start->isFuture() && $end->gt($start) ? [$start, $end] : null;
    }

    /** @return list<string> */
    private function dateValues(string $message): array
    {
        preg_match_all('/\b20\d{2}-\d{2}-\d{2}\b/', $message, $matches);

        return collect(array_values(array_unique((array) ($matches[0] ?? []))))
            ->filter(function (string $date): bool {
                try {
                    return Carbon::createFromFormat('!Y-m-d', $date)?->format('Y-m-d') === $date;
                } catch (Throwable) {
                    return false;
                }
            })->values()->all();
    }

    /** @return array{days:list<int>,slots:list<array{day:int,start_time:string,end_time:string}>,starts_on:string,label:string}|null */
    private function weeklyScheduleFromMessage(string $message): ?array
    {
        $lower = mb_strtolower($message);
        $dayMap = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];
        $days = collect($dayMap)->filter(fn (int $number, string $day): bool => str_contains($lower, $day))->values()->all();
        if ($days === [] || preg_match('/\b(\d{1,2}:\d{2})\s*(?:to|[-–])\s*(\d{1,2}:\d{2})\b/i', $message, $time) !== 1) {
            return null;
        }
        $dates = $this->dateValues($message);
        if ($dates === []) {
            return null;
        }
        try {
            $start = Carbon::createFromFormat('!H:i', str_pad($time[1], 5, '0', STR_PAD_LEFT));
            $end = Carbon::createFromFormat('!H:i', str_pad($time[2], 5, '0', STR_PAD_LEFT));
            $startsOn = Carbon::createFromFormat('!Y-m-d', $dates[0]);
        } catch (Throwable) {
            return null;
        }
        if (! $start || ! $end || ! $startsOn || ! $end->gt($start) || ! $startsOn->startOfDay()->isFuture()) {
            return null;
        }
        $startTime = $start->format('H:i');
        $endTime = $end->format('H:i');
        $slots = array_map(static fn (int $day): array => ['day' => $day, 'start_time' => $startTime, 'end_time' => $endTime], $days);
        $labels = collect($dayMap)->filter(fn (int $number): bool => in_array($number, $days, true))->keys()->map(fn (string $day): string => Str::headline($day))->implode(', ');

        return ['days' => $days, 'slots' => $slots, 'starts_on' => $dates[0], 'label' => $labels.' '.$startTime.'–'.$endTime];
    }

    private function reasonText(string $message): string
    {
        if (preg_match('/\b(?:because|reason(?:\s+is)?|reason:)\s+(.+)$/iu', trim($message), $matches) === 1) {
            return Str::limit(trim((string) $matches[1]), 2000, '');
        }

        return '';
    }

    private function messageText(string $message): string
    {
        $patterns = [
            '/\b(?:saying|say|that|message:)\s+["“]?(.+?)["”]?$/iu',
            '/\btell\s+[\p{L}\p{M}\'’ .-]{2,80}\s+["“]?(.+?)["”]?$/iu',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, trim($message), $matches) === 1) {
                return Str::limit(trim((string) $matches[1], " \t\n\r\0\x0B\"“”"), 3000, '');
            }
        }

        return '';
    }

    private function rating(string $message): ?int
    {
        if (preg_match('/\b([1-5])\s*(?:\/\s*5|star|stars)\b/i', $message, $matches) === 1) {
            return (int) $matches[1];
        }
        $words = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5];
        foreach ($words as $word => $rating) {
            if (preg_match('/\b'.$word.'\s+stars?\b/i', $message) === 1) {
                return $rating;
            }
        }

        return null;
    }

    private function reviewText(string $message): string
    {
        if (preg_match('/\b(?:say|review:)\s+["“]?(.+?)["”]?$/iu', $message, $matches) === 1) {
            return Str::limit(trim((string) $matches[1], " \t\n\r\0\x0B\"“”"), 1500, '');
        }

        return '';
    }

    private function offerRead(User $actor, SupportTicket $ticket, string $body, string $intentId, string $targetId, mixed $resource = null): void
    {
        $resourceType = match (true) {
            $resource instanceof CareRequest => 'care_request',
            $resource instanceof CarePlan => 'care_plan',
            $resource instanceof CareRequestConversation => 'conversation',
            default => null,
        };
        $resourceId = $resource?->id;
        $resourceContext = ['resource_type' => $resourceType, 'resource_id' => $resourceId];
        $guides = [];
        if ($this->navigation->allowedFor($actor, $targetId, $resourceContext)) {
            $definition = $this->navigation->definition($targetId) ?? [];
            $guides[] = [
                'task_type' => match (true) {
                    str_contains($targetId, 'timesheet'), str_contains($targetId, 'payment_attention') => AiSupportGuidedTask::TYPE_FAMILY_TIMESHEET,
                    str_contains($targetId, 'message') => AiSupportGuidedTask::TYPE_FAMILY_MESSAGE,
                    str_contains($targetId, 'history') => AiSupportGuidedTask::TYPE_FAMILY_HISTORY,
                    str_contains($targetId, 'regular') => AiSupportGuidedTask::TYPE_FAMILY_REQUEST,
                    str_contains($targetId, 'visit') => AiSupportGuidedTask::TYPE_FAMILY_VISIT,
                    default => AiSupportGuidedTask::TYPE_FAMILY_REQUEST,
                },
                'target_id' => $targetId,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'label' => (string) ($definition['label'] ?? 'Open the right page'),
                'verifier_id' => 'authoritative_family_state_v1',
            ];
        }
        $this->guidedTasks->offerFamilyReadResult($actor, $ticket, Str::limit($body, 1400, ''), $intentId, 'family_care_authoritative_read', $guides);
    }

    private function available(User $actor, SupportTicket $ticket, string $tool): bool
    {
        return $this->eligibility->evaluate($actor, self::CAPABILITY, $ticket, $tool)->allowed;
    }

    private function domainAction(User $actor, SupportTicket $ticket, string $actionId, bool $active = true): AiSupportMessageAction
    {
        return AiSupportMessageAction::query()->whereKey($actionId)
            ->where('support_ticket_id', $ticket->id)->where('actor_user_id', $actor->id)
            ->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)
            ->when($active, fn ($q) => $q->whereNull('invalidated_at'))->firstOrFail();
    }

    private function assertState(mixed $model, string $expectedStatus, string $expectedUpdatedAt, string $message): void
    {
        if (! hash_equals((string) $model->status, $expectedStatus)
            || ! hash_equals($this->stamp($model->updated_at), $expectedUpdatedAt)) {
            throw ValidationException::withMessages(['confirmation' => $message]);
        }
    }

    private function assertUpdated(mixed $model, string $expectedUpdatedAt, string $message): void
    {
        if (! hash_equals($this->stamp($model->updated_at), $expectedUpdatedAt)) {
            throw ValidationException::withMessages(['confirmation' => $message]);
        }
    }

    /** @param array<string,mixed> $preview */
    private function assertPlanState(CarePlan $plan, array $preview, string $message): void
    {
        if (! hash_equals((string) $plan->status, (string) ($preview['expected_status'] ?? ''))
            || (int) $plan->schedule_version !== (int) ($preview['expected_schedule_version'] ?? -1)
            || ! hash_equals($this->stamp($plan->updated_at), (string) ($preview['expected_updated_at'] ?? ''))) {
            throw ValidationException::withMessages(['confirmation' => $message]);
        }
    }

    private function stamp(mixed $value): string
    {
        return $value?->toIso8601String() ?? '';
    }

    /** @return array{outcome_code:string,domain_reference_type:string,domain_reference_id:string,receipt_reference:string} */
    private function receipt(string $outcome, string $type, int $id, string $reference): array
    {
        return ['outcome_code' => $outcome, 'domain_reference_type' => $type, 'domain_reference_id' => (string) $id, 'receipt_reference' => $reference];
    }

    private function receiptText(AiSupportConfirmedActionEvidence $evidence): string
    {
        return match ($evidence->outcome_code) {
            'invitation_sent_verified' => 'The caregiver invitation was sent and verified.',
            'invitation_cancelled_verified' => 'The invitation was cancelled and verified. The request remains unchanged.',
            'applicant_shortlisted_verified' => 'The caregiver was saved for follow-up.',
            'applicant_rejected_verified' => 'The caregiver was declined for this request.',
            'conversation_started_verified' => 'The caregiver conversation is ready.',
            'message_sent_verified' => 'The message was sent and recorded. LoLo does not claim it was read.',
            'caregiver_hired_verified' => 'The caregiver was hired and the resulting care record was verified. Secure payment confirmation may still need attention.',
            'visit_change_requested_verified' => 'The visit-change request was sent. The current schedule remains until it is accepted.',
            'visit_change_rejected_verified' => 'The caregiver change request was rejected. The current visit remains unchanged.',
            'visit_change_accepted_verified' => 'The caregiver change request was accepted and the visit record was verified.',
            'visit_cancelled_verified' => 'The scheduled visit was cancelled and verified.',
            'caregiver_no_show_verified' => 'The visit was marked caregiver no-show and verified.',
            'visit_completed_verified' => 'The visit was marked complete and verified.',
            'submitted_hours_approved_verified' => 'The submitted hours were approved and the current payment result was verified.',
            'time_correction_approved_verified' => 'The time correction was approved and its current processing state was verified.',
            'time_correction_changes_requested_verified' => 'The request for time-correction changes was sent. No hours were approved.',
            'care_review_submitted_verified' => 'The caregiver review was submitted and verified.',
            'rebook_request_created_verified' => 'A new care request was created and the caregiver invitation was sent.',
            'regular_care_offer_sent_verified' => 'The recurring care offer was sent. The caregiver still needs to accept it.',
            'regular_counter_accepted_verified' => 'The recurring care counteroffer was accepted and verified.',
            'regular_schedule_change_sent_verified' => 'The recurring care schedule request was sent. Current visits remain until acceptance.',
            'regular_extra_visit_requested_verified' => 'The extra-visit request was sent. It is not booked until accepted.',
            'regular_visit_skipped_verified' => 'The selected recurring care visit was skipped. The plan continues.',
            'regular_care_paused_verified' => 'Recurring care was paused and verified.',
            'regular_care_resumed_verified' => 'Recurring care was resumed and verified.',
            'regular_care_ended_verified' => 'Recurring care was ended and verified.',
            'completed_extra_visit_approved_verified' => 'The completed extra visit was approved and its current payment state was verified.',
            'completed_extra_visit_changes_requested_verified' => 'The extra-visit change request was sent. No payment was approved.',
            default => 'The requested action was completed and checked against the current record.',
        };
    }

    private function receiptLabel(AiSupportConfirmedActionEvidence $evidence): string
    {
        return match ($evidence->domain_reference_type) {
            'conversation' => 'Open conversation',
            'care_plan', 'care_plan_schedule_change', 'completed_extra_visit' => 'Open recurring care',
            'care_request_application', 'care_request_invitation' => 'Review caregivers',
            default => 'Open care record',
        };
    }

    private function receiptUrl(User $actor, AiSupportConfirmedActionEvidence $evidence): string
    {
        return match ($evidence->domain_reference_type) {
            'conversation' => route('messages.show', (int) $evidence->domain_reference_id),
            'care_plan' => route('family.care.show', (int) $evidence->domain_reference_id),
            'care_plan_schedule_change' => route('family.care.show', (int) CarePlanScheduleChange::query()->find($evidence->domain_reference_id)?->care_plan_id),
            'completed_extra_visit' => route('family.care.show', (int) CompletedExtraVisitRequest::query()->find($evidence->domain_reference_id)?->care_plan_id),
            'care_request' => route('family.requests.show', (int) $evidence->domain_reference_id),
            'care_booking' => route('family.requests.show', (int) CareBooking::query()->find($evidence->domain_reference_id)?->care_request_id),
            'care_request_application' => route('family.requests.show', ['careRequest' => (int) CareRequestApplication::query()->find($evidence->domain_reference_id)?->care_request_id, 'tab' => 'applicants']),
            'care_request_invitation' => route('family.requests.show', ['careRequest' => (int) CareRequestInvitation::query()->find($evidence->domain_reference_id)?->care_request_id, 'tab' => 'applicants']),
            'booking_change_request' => route('family.requests.show', ['careRequest' => (int) CareBookingChangeRequest::query()->find($evidence->domain_reference_id)?->booking?->care_request_id, 'tab' => 'shift']),
            'time_correction' => route('family.requests.show', ['careRequest' => (int) CareBookingTimeCorrection::query()->find($evidence->domain_reference_id)?->booking?->care_request_id, 'tab' => 'shift']),
            'care_review' => route('family.requests.show', ['careRequest' => (int) CareReview::query()->find($evidence->domain_reference_id)?->care_request_id, 'tab' => 'shift']),
            default => route('family.requests.index'),
        };
    }

    private function automatedMessage(SupportTicket $ticket, string $body): SupportTicketMessage
    {
        return DB::transaction(function () use ($ticket, $body): SupportTicketMessage {
            $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->responder_mode !== SupportTicket::RESPONDER_MODE_AUTOMATED) {
                throw new \RuntimeException('Automated ownership ended before delivery.');
            }
            $message = SupportTicketMessage::query()->create([
                'support_ticket_id' => $locked->id, 'sender_user_id' => null,
                'kind' => SupportTicketMessage::KIND_PUBLIC,
                'responder_type' => SupportTicketMessage::RESPONDER_AUTOMATED,
                'body' => Str::limit($body, 2000, ''), 'client_message_id' => (string) Str::uuid(),
            ]);
            $locked->forceFill([
                'last_public_message_at' => $message->created_at,
                'last_public_message_sender_id' => null,
                'opener_last_read_at' => null,
            ])->save();

            return $message;
        }, 3);
    }
}
