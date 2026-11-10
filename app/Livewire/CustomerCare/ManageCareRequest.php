<?php

namespace App\Livewire\CustomerCare;

use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\User;
use App\Services\CustomerCare\CustomerCareAccess;
use App\Services\CustomerCare\ManagedCareService;
use Illuminate\Validation\ValidationException;

/** The family screen, with separately authorized and audited staff actions. */
class ManageCareRequest extends \App\Livewire\Family\ManageCareRequest
{
    public string $actualStartAt = '';

    public string $actualEndAt = '';

    public int $actualBreakMinutes = 0;

    public function hydrate(): void
    {
        $this->authorizeWorkspace();
        $this->refreshRequestItem();
    }

    protected function authorizeWorkspace(): void
    {
        abort_unless(auth()->user(), 403);
        CustomerCareAccess::authorize(auth()->user());
        $request = CareRequest::findOrFail($this->requestItem->id);
        abort_unless($request->staff_managed && ! $request->staff_archived_at, 403);
    }

    protected function familyContextUser(): User
    {
        // Resolve account ownership without changing authentication or action authors.
        $this->authorizeWorkspace();

        $family = User::findOrFail($this->requestItem->family_user_id);
        abort_unless(app(\App\Services\FamilyAccounts\FamilyAccountContext::class)->canAccessRecord($family, $this->requestItem), 403);

        return $family;
    }

    protected function isStaffWorkspace(): bool
    {
        return true;
    }

    protected function invitationActor(): ?User
    {
        $this->authorizeWorkspace();

        return auth()->user();
    }

    protected function reconcileFilledStateFromBooking(): bool
    {
        // Staff reads must not rewrite application or visit state.
        return false;
    }

    public function mount(int $careRequest): void
    {
        abort_unless(auth()->user(), 403);
        CustomerCareAccess::authorize(auth()->user());
        parent::mount($careRequest);
        if (request('tab') === 'caregivers') {
            $this->setCaregiverView('search');
        }
    }

    public function workspaceUrl(string $name, mixed $parameters = []): string
    {
        return match ($name) {
            'family.requests.index', 'family.care.index', 'family.billing.show' => route('customer-care.customer', $this->requestItem->family_user_id),
            'family.requests.create' => route('customer-care.create', $this->requestItem->family_user_id),
            'family.care.journey' => route('customer-care.show', ['careRequest' => $this->requestItem->id, 'panel' => 'activity']),
            'messages.show' => route('customer-care.show', ['careRequest' => $this->requestItem->id, 'panel' => 'messages', 'conversation' => $parameters]),
            'family.care.show', 'family.shared-care.show' => route('customer-care.show', ['careRequest' => $this->requestItem->carePlan?->source_care_request_id ?: $this->requestItem->id, 'panel' => 'visits']),
            default => parent::workspaceUrl($name, $parameters),
        };
    }

    public function shortlist(int $applicationId): void
    {
        $this->care()->application(auth()->user(), $this->requestItem, $applicationId, 'shortlisted');
        $this->saved();
    }

    public function reject(int $applicationId): void
    {
        $this->care()->application(auth()->user(), $this->requestItem, $applicationId, 'rejected');
        $this->saved();
    }

    public function withdrawRequest(): void
    {
        $this->care()->withdraw(auth()->user(), $this->requestItem, 'Withdrawn by the care team from the request workspace.');
        $this->saved();
        $this->redirect(route('customer-care.show', $this->requestItem->id), navigate: true);
    }

    public function hire(int $applicationId): void
    {
        $this->authorizeWorkspace();
        $application = $this->findOwnedApplication($applicationId);
        if ($this->reviewingApplicationId !== $applicationId || $this->reviewedOfferVersion !== $application->offer_version) {
            throw ValidationException::withMessages(['hire' => 'Review the caregiver’s latest offer before confirming.']);
        }
        $fundingSource = $this->careHourAccountId === null ? 'card' : 'prepaid';
        parent::hire($applicationId);
        if ($application->fresh()->status === CareRequestApplication::STATUS_HIRED) {
            $this->care()->audit(auth()->user(), $this->requestItem, 'caregiver_hired', metadata: ['caregiver_user_id' => $application->caregiver_user_id, 'funding_source' => $fundingSource]);
            $this->dispatch('customer-care-request-hired');
        }
    }

    public function approveTimeCorrection(int $correctionId): void
    {
        if (! app(\App\Services\CareHours\PrepaidBookingFundingService::class)->isPrepaid($this->booking())) {
            parent::approveTimeCorrection($correctionId);
            $this->care()->audit(auth()->user(), $this->requestItem, 'time_correction_approve', metadata: ['time_correction_id' => $correctionId]);

            return;
        }
        abort_unless($this->confirmingTimeCorrectionId === $correctionId, 403);
        $this->care()->correction(auth()->user(), $this->requestItem, $this->booking()->id, $correctionId, 'approve', 'Corrected hours reviewed and approved by the care team.');
        $this->cancelTimeCorrectionApproval();
        $this->saved();
    }

    public function requestTimeCorrectionChanges(int $correctionId): void
    {
        $this->care()->correction(auth()->user(), $this->requestItem, $this->booking()->id, $correctionId, 'changes', $this->timeCorrectionResponseNote);
        $this->saved();
    }

    public function escalateTimeCorrection(int $correctionId): void
    {
        $this->authorizeWorkspace();
        parent::escalateTimeCorrection($correctionId);
        $this->care()->audit(auth()->user(), $this->requestItem, 'time_correction_escalated', metadata: ['time_correction_id' => $correctionId]);
    }

    public function reviewCompletion(): void
    {
        parent::reviewCompletion();
        $booking = $this->booking();
        $this->actualStartAt = $booking->started_at?->format('Y-m-d\TH:i') ?? '';
        $this->actualEndAt = now()->format('Y-m-d\TH:i');
        $this->actualBreakMinutes = (int) ceil(($booking->total_paused_seconds + ($booking->paused_at ? max(0, $booking->paused_at->diffInSeconds(now())) : 0)) / 60);
    }

    public function completeBooking(): void
    {
        $booking = $this->booking();
        if (in_array($booking->status, ['completed', 'reviewed'], true) && ! app(\App\Services\CareHours\PrepaidBookingFundingService::class)->isPrepaid($booking)) {
            $wasConfirmed = $booking->family_confirmed_at;
            parent::completeBooking();
            if (! $wasConfirmed && $booking->fresh()->family_confirmed_at) {
                $this->care()->audit(auth()->user(), $this->requestItem, 'visit_approve', metadata: ['booking_id' => $booking->id, 'funding_source' => 'card']);
            }

            return;
        }
        $action = in_array($booking->status, ['completed', 'reviewed'], true) ? 'approve' : 'complete';
        $this->care()->visit(auth()->user(), $this->requestItem, $booking->id, $action, [
            'reason' => trim($this->confirmationNote) ?: 'Actual visit times reviewed by the care team.',
            'start' => $this->actualStartAt, 'end' => $this->actualEndAt, 'break_minutes' => $this->actualBreakMinutes,
        ]);
        $this->saved();
    }

    public function confirmReviewedCompletion(): void
    {
        abort_unless($this->reviewingCompletion, 403);
        $this->completeBooking();
        $this->reviewingCompletion = false;
    }

    public function submitReview(): void
    {
        throw ValidationException::withMessages(['customer_care' => 'Use internal notes for team feedback. Customer reviews belong to the family.']);
    }

    public function submitChangeRequest(): void
    {
        $this->care()->visit(auth()->user(), $this->requestItem, $this->booking()->id, $this->changeType, [
            'reason' => $this->changeReason, 'start' => $this->proposedStartAt, 'end' => $this->proposedEndAt,
        ]);
        $this->saved();
    }

    public function resolveChangeRequest(int $changeRequestId, string $decision): void
    {
        $this->care()->change(auth()->user(), $this->requestItem, $this->booking()->id, $changeRequestId, $decision, 'Caregiver change reviewed by the care team.');
        $this->saved();
    }

    public function createSupportTicket(): void
    {
        $this->authorizeWorkspace();
        parent::createSupportTicket();
        $this->care()->audit(auth()->user(), $this->requestItem, 'support_ticket_opened');
    }

    public function openDispute(): void
    {
        $this->care()->visit(auth()->user(), $this->requestItem, $this->booking()->id, 'dispute', ['reason' => $this->disputeReason]);
        $this->saved();
    }

    public function markNoShow(): void
    {
        $this->care()->visit(auth()->user(), $this->requestItem, $this->booking()->id, 'no_show', ['reason' => 'Caregiver no-show confirmed by the care team.']);
        $this->saved();
    }

    public function cancelScheduledBooking(): void
    {
        $this->care()->visit(auth()->user(), $this->requestItem, $this->booking()->id, 'cancel', ['reason' => $this->directCancelReason]);
        $this->saved();
    }

    public function reportIncident(): void
    {
        $this->authorizeWorkspace();
        parent::reportIncident();
        $this->care()->audit(auth()->user(), $this->requestItem, 'incident_reported');
    }

    public function rebookHiredCaregiver(): void
    {
        $this->authorizeWorkspace();
        $this->redirect(route('customer-care.create', $this->requestItem->family_user_id), navigate: true);
    }

    public function startConversation(int $applicationId): void
    {
        $application = $this->findOwnedApplication($applicationId);
        $this->messageCaregiver($application->caregiver_user_id);
    }

    public function messageCaregiver(int $caregiverId): void
    {
        $this->authorizeWorkspace();
        $application = $this->requestItem->applications()->where('caregiver_user_id', $caregiverId)->first();
        abort_unless($application || $this->requestItem->invitations()->where('caregiver_user_id', $caregiverId)->exists(), 404);
        $conversation = CareRequestConversation::findOrCreateForRequest($this->requestItem, $caregiverId, auth()->id(), $application);
        $this->redirect($this->workspaceUrl('messages.show', $conversation->id), navigate: true);
    }

    public function render()
    {
        $this->authorizeWorkspace();

        return parent::render();
    }

    private function booking(): CareBooking
    {
        $this->authorizeWorkspace();

        return $this->requestItem->booking()->firstOrFail();
    }

    private function care(): ManagedCareService
    {
        $this->authorizeWorkspace();

        return app(ManagedCareService::class);
    }

    private function saved(): void
    {
        $this->refreshRequestItem();
        session()->flash('status', 'Saved by the care team. No customer notification was sent.');
    }
}
