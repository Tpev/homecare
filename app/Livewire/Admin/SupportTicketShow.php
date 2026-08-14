<?php

namespace App\Livewire\Admin;

use App\Models\AiSupportConfirmedActionEvidence;
use App\Models\AiSupportInteractionEvent;
use App\Models\AiSupportMessageAction;
use App\Models\AiSupportRequestDraft;
use App\Models\CareBookingCorrection;
use App\Models\CareBookingTimeCorrection;
use App\Models\DataRetentionHold;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\AiSupport\AiSupportContractRegistry;
use App\Services\AiSupport\AiSupportHandoffService;
use App\Services\Booking\BookingCorrectionService;
use App\Services\Booking\CareBookingTimeCorrectionService;
use App\Services\Support\SupportChatService;
use App\Services\Support\SupportTicketMessagingService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class SupportTicketShow extends Component
{
    public int $ticketId;

    public string $messageBody = '';

    public string $messageKind = SupportTicketMessage::KIND_PUBLIC;

    public string $clientMessageId = '';

    public string $status = SupportTicket::STATUS_OPEN;

    public string $assignedAdminId = '';

    public string $returnToAutomationReason = '';

    public int $messagesLimit = 50;

    public string $correctionAction = CareBookingCorrection::ACTION_COMPLETE_AND_BILL;

    public string $correctionStartedAt = '';

    public string $correctionCompletedAt = '';

    public string $correctionBreakMinutes = '0';

    public string $correctionReason = '';

    public bool $correctionFamilyApproved = false;

    public bool $correctionImpactConfirmed = false;

    public string $correctionClientRequestId = '';

    public function mount(SupportTicket $ticket): void
    {
        $ticket->refresh();
        abort_unless(auth()->user()?->isAdministrator(), 403);
        $this->authorize('manage', $ticket);

        $this->ticketId = $ticket->id;
        $this->status = $ticket->status;
        $this->assignedAdminId = $ticket->assigned_admin_id ? (string) $ticket->assigned_admin_id : '';
        $this->clientMessageId = (string) Str::uuid();
        $this->correctionClientRequestId = (string) Str::uuid();
        $ticket->markReadForAdmin();
        $this->initializeCorrectionForm($ticket);
    }

    public function sendMessage(SupportTicketMessagingService $messaging): void
    {
        $validated = $this->validate([
            'messageBody' => ['required', 'string', 'min:1', 'max:3000', 'regex:/\S/'],
            'messageKind' => ['required', Rule::in([
                SupportTicketMessage::KIND_PUBLIC,
                SupportTicketMessage::KIND_INTERNAL_NOTE,
            ])],
            'clientMessageId' => ['required', 'uuid'],
        ]);

        $ticket = $this->ticket;

        if ($validated['messageKind'] === SupportTicketMessage::KIND_INTERNAL_NOTE) {
            $this->authorize('addInternalNote', $ticket);
            $messaging->addInternalNote(
                ticket: $ticket,
                admin: auth()->user(),
                body: $validated['messageBody'],
                clientMessageId: $validated['clientMessageId'],
            );
        } else {
            $this->authorize('replyAsAdmin', $ticket);
            $messaging->sendAdminReply(
                ticket: $ticket,
                admin: auth()->user(),
                body: $validated['messageBody'],
                clientMessageId: $validated['clientMessageId'],
            );
        }

        $this->messageBody = '';
        $this->clientMessageId = (string) Str::uuid();
        $this->syncControlsFromTicket();
        $this->resetValidation();
        $this->dispatch('support-message-sent');
    }

    public function updateStatus(): void
    {
        $validated = $this->validate([
            'status' => ['required', Rule::in([
                SupportTicket::STATUS_OPEN,
                SupportTicket::STATUS_IN_PROGRESS,
                SupportTicket::STATUS_RESOLVED,
                SupportTicket::STATUS_CLOSED,
            ])],
        ]);

        $ticket = $this->ticket;
        $this->authorize('manage', $ticket);

        $ticket->forceFill([
            'status' => $validated['status'],
            'assigned_admin_id' => $ticket->assigned_admin_id ?: auth()->id(),
            'claimed_at' => $ticket->claimed_at ?: now(),
            'resolved_at' => match ($validated['status']) {
                SupportTicket::STATUS_RESOLVED => $ticket->resolved_at ?: now(),
                SupportTicket::STATUS_CLOSED => $ticket->resolved_at,
                default => null,
            },
        ])->save();

        $this->syncControlsFromTicket();
        session()->flash('status', 'Ticket status updated.');
    }

    public function claimConversation(SupportChatService $chat): void
    {
        try {
            $chat->claim($this->ticket, auth()->user());
            $this->syncControlsFromTicket();
            $this->resetValidation('claim');
            session()->flash('status', 'Conversation claimed.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->addError('claim', collect($exception->errors())->flatten()->first());
        }
    }

    public function updateAssignment(): void
    {
        $validated = $this->validate([
            'assignedAdminId' => [
                'nullable',
                Rule::exists('users', 'id')->where(function ($query): void {
                    $query->where('role', 'admin');
                }),
            ],
        ]);

        $ticket = $this->ticket;
        $this->authorize('manage', $ticket);
        $ticket->forceFill([
            'assigned_admin_id' => filled($validated['assignedAdminId'])
                ? (int) $validated['assignedAdminId']
                : null,
            'claimed_at' => filled($validated['assignedAdminId']) ? now() : null,
        ])->save();

        $this->syncControlsFromTicket();
        session()->flash('status', 'Ticket assignment updated.');
    }

    public function returnToAutomation(AiSupportHandoffService $handoff): void
    {
        $validated = $this->validate([
            'returnToAutomationReason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        try {
            $handoff->returnToAutomation(
                auth()->user(),
                $this->ticket,
                $validated['returnToAutomationReason'],
            );
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->addError('returnToAutomationReason', collect($exception->errors())->flatten()->first());

            return;
        }

        $this->returnToAutomationReason = '';
        $this->syncControlsFromTicket();
        session()->flash('status', 'Conversation deliberately returned to the assistant.');
    }

    public function loadMore(): void
    {
        $this->messagesLimit = min(300, $this->messagesLimit + 40);
    }

    public function refreshThread(): void
    {
        $this->ticket->markReadForAdmin();
    }

    public function applyVisitCorrection(
        BookingCorrectionService $corrections,
        SupportTicketMessagingService $messaging,
    ): void {
        $validated = $this->validate([
            'correctionAction' => ['required', Rule::in([
                CareBookingCorrection::ACTION_REOPEN,
                CareBookingCorrection::ACTION_COMPLETE_AND_BILL,
            ])],
            'correctionStartedAt' => ['nullable', 'required_if:correctionAction,'.CareBookingCorrection::ACTION_COMPLETE_AND_BILL, 'date'],
            'correctionCompletedAt' => ['nullable', 'required_if:correctionAction,'.CareBookingCorrection::ACTION_COMPLETE_AND_BILL, 'date'],
            'correctionBreakMinutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'correctionReason' => ['required', 'string', 'min:10', 'max:2000'],
            'correctionFamilyApproved' => ['boolean'],
            'correctionImpactConfirmed' => ['accepted'],
            'correctionClientRequestId' => ['required', 'uuid'],
        ]);

        if ($validated['correctionAction'] === CareBookingCorrection::ACTION_COMPLETE_AND_BILL
            && ! $validated['correctionFamilyApproved']) {
            $this->addError('correctionFamilyApproved', 'Confirm that the family approved this correction.');

            return;
        }

        $ticket = $this->ticket;
        $this->authorize('manage', $ticket);
        $correction = $corrections->apply(
            $ticket,
            auth()->user(),
            [
                'action' => $validated['correctionAction'],
                'started_at' => $validated['correctionStartedAt'],
                'completed_at' => $validated['correctionCompletedAt'],
                'break_minutes' => (int) $validated['correctionBreakMinutes'],
                'reason' => $validated['correctionReason'],
                'family_approved' => (bool) $validated['correctionFamilyApproved'],
            ],
            $validated['correctionClientRequestId'],
        );

        $this->handleCorrectionOutcome($correction, $messaging);
    }

    public function retryVisitCorrection(
        int $correctionId,
        BookingCorrectionService $corrections,
        SupportTicketMessagingService $messaging,
    ): void {
        $ticket = $this->ticket;
        $this->authorize('manage', $ticket);
        $correction = CareBookingCorrection::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('care_booking_id', $ticket->care_booking_id)
            ->findOrFail($correctionId);

        $correction = $corrections->retry($correction, auth()->user());
        $this->handleCorrectionOutcome($correction, $messaging);
    }

    public function getTicketProperty(): SupportTicket
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);

        $ticket = SupportTicket::query()
            ->with([
                'opener:id,name,email,phone,role',
                'counterparty:id,name,email,phone,role',
                'assignedAdmin:id,name,email,role',
                'familyAccount.owner:id,name,email',
                'familyAccount.activeMemberships:id,family_account_id,user_id,access_level,status',
                'careRequest:id,title,status',
                'careBooking',
                'careBooking.payment',
                'careBooking.family:id,name,email',
                'careBooking.caregiver:id,name,email',
                'careBooking.caregiver.caregiverProfile:id,user_id,platform_hourly_rate,stripe_connect_account_id,stripe_charges_enabled,stripe_payouts_enabled',
                'careBooking.application:id,care_request_id,caregiver_user_id,proposed_rate',
                'careBooking.payoutItem.payout',
                'timeCorrection.requester:id,name,email',
                'timeCorrection.approvedBy:id,name,email',
                'timeCorrection.booking.timeCorrections' => fn ($query) => $query
                    ->with(['requester:id,name', 'approvedBy:id,name'])
                    ->orderBy('version'),
            ])
            ->findOrFail($this->ticketId);

        $this->authorize('manage', $ticket);

        return $ticket;
    }

    public function getMessagesProperty(): Collection
    {
        return SupportTicketMessage::query()
            ->with('sender:id,name,role')
            ->where('support_ticket_id', $this->ticketId)
            ->latest('created_at')
            ->latest('id')
            ->limit($this->messagesLimit)
            ->get()
            ->sortBy([['created_at', 'asc'], ['id', 'asc']])
            ->values();
    }

    public function getHasOlderMessagesProperty(): bool
    {
        return SupportTicketMessage::query()
            ->where('support_ticket_id', $this->ticketId)
            ->count() > $this->messagesLimit;
    }

    public function getAiEvidenceProperty(): Collection
    {
        return AiSupportInteractionEvent::query()
            ->with('actor:id,name')
            ->where('support_ticket_id', $this->ticketId)
            ->latest('occurred_at')
            ->get();
    }

    public function getAiDraftsProperty(): Collection
    {
        return AiSupportRequestDraft::query()
            ->with('actor:id,name')
            ->where('support_ticket_id', $this->ticketId)
            ->latest('updated_at')
            ->get();
    }

    public function getAiActionStateProperty(): array
    {
        return [
            'active_recaps' => AiSupportMessageAction::query()
                ->where('support_ticket_id', $this->ticketId)
                ->where('action_type', AiSupportMessageAction::TYPE_RECAP)
                ->whereNull('consumed_at')->whereNull('invalidated_at')
                ->where('expires_at', '>', now())->count(),
            'expired_recaps' => AiSupportMessageAction::query()
                ->where('support_ticket_id', $this->ticketId)
                ->where('action_type', AiSupportMessageAction::TYPE_RECAP)
                ->whereNull('consumed_at')->whereNull('invalidated_at')
                ->where('expires_at', '<=', now())->count(),
            'receipts' => AiSupportMessageAction::query()
                ->where('support_ticket_id', $this->ticketId)
                ->where('action_type', AiSupportMessageAction::TYPE_RECEIPT)->count(),
        ];
    }

    public function getAiConfirmedActionsProperty(): Collection
    {
        return AiSupportConfirmedActionEvidence::query()
            ->where('support_ticket_id', $this->ticketId)
            ->latest('committed_at')
            ->get();
    }

    public function getActiveRetentionHoldsProperty(): Collection
    {
        return DataRetentionHold::query()
            ->active()
            ->where('scope_type', SupportTicket::class)
            ->where('scope_id', (string) $this->ticketId)
            ->orderBy('review_at')
            ->get();
    }

    /** @return array<string, mixed> */
    public function getAiContractVersionsProperty(): array
    {
        return app(AiSupportContractRegistry::class)->versions();
    }

    /** @return array<string, mixed>|null */
    public function getCorrectionPreviewProperty(): ?array
    {
        $booking = $this->ticket->careBooking;
        if (! $booking) {
            return null;
        }

        try {
            return app(BookingCorrectionService::class)->preview($booking, [
                'action' => $this->correctionAction,
                'started_at' => $this->correctionStartedAt,
                'completed_at' => $this->correctionCompletedAt,
                'break_minutes' => (int) $this->correctionBreakMinutes,
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    public function getBookingCorrectionsProperty(): Collection
    {
        return CareBookingCorrection::query()
            ->with('actorAdmin:id,name')
            ->where('support_ticket_id', $this->ticketId)
            ->latest()
            ->limit(10)
            ->get();
    }

    /** @return array<int, string> */
    public function getAdminOptionsProperty(): array
    {
        return User::query()
            ->where(function (Builder $query): void {
                $query->where('role', 'admin');
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function render(): View
    {
        return view('livewire.admin.support-ticket-show');
    }

    private function syncControlsFromTicket(): void
    {
        $ticket = SupportTicket::query()->findOrFail($this->ticketId);
        $this->status = $ticket->status;
        $this->assignedAdminId = $ticket->assigned_admin_id ? (string) $ticket->assigned_admin_id : '';
    }

    private function initializeCorrectionForm(SupportTicket $ticket): void
    {
        $booking = $ticket->careBooking;
        if (! $booking) {
            return;
        }

        $timeCorrection = $ticket->timeCorrection()->first();
        $this->correctionStartedAt = ($timeCorrection?->proposed_started_at ?: $booking->started_at ?: $booking->scheduled_start_at)?->format('Y-m-d\TH:i') ?: '';
        $this->correctionCompletedAt = ($timeCorrection?->proposed_completed_at ?: $booking->completed_at ?: $booking->scheduled_end_at)?->format('Y-m-d\TH:i') ?: '';
        $this->correctionBreakMinutes = (string) ($timeCorrection?->proposed_break_minutes ?? max(0, (int) round(((int) $booking->total_paused_seconds) / 60)));
        if ($timeCorrection) {
            $this->correctionReason = 'Time correction #'.$timeCorrection->id.' (version '.$timeCorrection->version.'): '.$timeCorrection->explanation;
            $this->correctionFamilyApproved = (bool) $timeCorrection->approved_at;
        }
    }

    private function handleCorrectionOutcome(
        CareBookingCorrection $correction,
        SupportTicketMessagingService $messaging,
    ): void {
        $ticket = $this->ticket;
        $ticket->forceFill([
            'assigned_admin_id' => $ticket->assigned_admin_id ?: auth()->id(),
            'status' => $correction->succeeded()
                ? SupportTicket::STATUS_RESOLVED
                : SupportTicket::STATUS_IN_PROGRESS,
            'resolved_at' => $correction->succeeded() ? ($ticket->resolved_at ?: now()) : null,
        ])->save();

        if (! $correction->succeeded()) {
            $this->addError('correctionApply', $correction->last_error ?: 'The correction needs attention before it can finish.');
            $this->syncControlsFromTicket();

            return;
        }

        if ($correction->time_correction_request_id) {
            $timeCorrection = CareBookingTimeCorrection::query()->findOrFail($correction->time_correction_request_id);
            app(CareBookingTimeCorrectionService::class)->completeAdminApplication(
                $timeCorrection,
                $correction,
                auth()->user(),
            );
        }

        $preview = (array) $correction->preview;
        $workedMinutes = (int) ($preview['worked_minutes'] ?? 0);
        $duration = intdiv($workedMinutes, 60).'h '.($workedMinutes % 60).'m';
        $delta = (int) $correction->payment_delta_cents;
        $financialSummary = $delta > 0
            ? 'Additional family charge: $'.number_format($delta / 100, 2).'.'
            : ($delta < 0
                ? 'Family refund: $'.number_format(abs($delta) / 100, 2).'.'
                : 'No payment difference.');

        $messaging->addInternalNote(
            $ticket->fresh(),
            auth()->user(),
            "Booking correction #{$correction->id} completed by ".auth()->user()->name.".\n"
                .'Action: '.str_replace('_', ' ', $correction->action).".\n"
                .'Reason: '.$correction->reason."\n"
                .'Approved duration: '.$duration.".\n"
                .$financialSummary,
            $correction->internal_note_client_id,
        );

        $publicMessage = $correction->action === CareBookingCorrection::ACTION_REOPEN
            ? 'We reopened visit #'.$correction->care_booking_id.'. It is ready to be started and completed again.'
            : 'We corrected visit #'.$correction->care_booking_id.' to '.$duration.'. The visit, payment, and caregiver payout records are now updated.';
        $messaging->sendAdminReply(
            $ticket->fresh(),
            auth()->user(),
            $publicMessage,
            $correction->public_reply_client_id,
        );

        $ticket->fresh()->forceFill([
            'assigned_admin_id' => $ticket->assigned_admin_id ?: auth()->id(),
            'status' => SupportTicket::STATUS_RESOLVED,
            'resolved_at' => $ticket->resolved_at ?: now(),
        ])->save();

        $this->correctionClientRequestId = (string) Str::uuid();
        $this->correctionReason = '';
        $this->correctionFamilyApproved = false;
        $this->correctionImpactConfirmed = false;
        $this->resetValidation();
        $this->syncControlsFromTicket();
        session()->flash('status', 'Visit correction completed, payment reconciled, user notified, and ticket resolved.');
    }
}
