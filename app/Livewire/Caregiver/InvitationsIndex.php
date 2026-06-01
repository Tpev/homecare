<?php

namespace App\Livewire\Caregiver;

use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareRequestInvitation;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Support\CaregiverPrelaunch;
use App\Support\CaregiverResponseMetrics;
use App\Support\FunnelTracker;
use App\Support\MarketplaceEvent;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class InvitationsIndex extends Component
{
    public string $status = 'pending';

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'caregiver', 403);
    }

    public function accept(int $invitationId): void
    {
        if (CaregiverPrelaunch::enabled()) {
            session()->flash('status', CaregiverPrelaunch::message());

            return;
        }

        $invitation = $this->findOwnInvitation($invitationId);
        $caregiverProfile = auth()->user()?->caregiverProfile;

        if (! $caregiverProfile || ! $caregiverProfile->isMarketplaceReady()) {
            session()->flash('status', 'Complete your caregiver profile before accepting invitations.');
            return;
        }

        if ($invitation->isExpired()) {
            $invitation->update(['status' => CareRequestInvitation::STATUS_EXPIRED]);
            session()->flash('status', 'Invitation expired.');
            return;
        }

        if ($invitation->status !== CareRequestInvitation::STATUS_PENDING) {
            return;
        }

        $conversation = DB::transaction(function () use ($invitation) {
            $application = CareRequestApplication::query()->firstOrNew([
                'care_request_id' => $invitation->care_request_id,
                'caregiver_user_id' => auth()->id(),
            ]);

            $existingStatus = $application->exists ? $application->status : null;
            $nextStatus = in_array($existingStatus, [
                CareRequestApplication::STATUS_HIRED,
                CareRequestApplication::STATUS_SHORTLISTED,
            ], true)
                ? $existingStatus
                : CareRequestApplication::STATUS_SHORTLISTED;

            $application->fill([
                'status' => $nextStatus,
                'cover_note' => $application->cover_note ?: ($invitation->message ?: 'Accepted invitation from family.'),
            ])->save();

            $invitation->update([
                'status' => CareRequestInvitation::STATUS_ACCEPTED,
                'responded_at' => now(),
                'care_request_application_id' => $application->id,
            ]);

            if ($invitation->careRequest) {
                $invitation->careRequest->forceFill([
                    'first_applicant_at' => $invitation->careRequest->first_applicant_at ?: now(),
                    'first_shortlist_at' => $invitation->careRequest->first_shortlist_at ?: now(),
                ])->save();
            }

            return CareRequestConversation::findOrCreateForApplication($application->loadMissing('careRequest'), auth()->id());
        });

        CaregiverResponseMetrics::recomputeForCaregiver((int) auth()->id());

        if ($invitation->family) {
            app(MarketplaceNotificationService::class)->notify(
                recipients: $invitation->family,
                eventKey: MarketplaceEvent::INVITE_ACCEPTED,
                title: 'Invitation accepted',
                body: auth()->user()->name.' accepted your invitation.',
                url: route('family.requests.show', $invitation->care_request_id),
                payload: ['care_request_id' => $invitation->care_request_id],
                subject: $invitation
            );
        }

        FunnelTracker::track('care_request_invitation_accepted', auth()->user(), $invitation, [
            'care_request_id' => $invitation->care_request_id,
        ]);

        session()->flash('status', 'Invitation accepted. Conversation opened.');
        $this->redirect(route('messages.show', $conversation->id, false), navigate: true);
    }

    public function decline(int $invitationId): void
    {
        $invitation = $this->findOwnInvitation($invitationId);

        if ($invitation->status !== CareRequestInvitation::STATUS_PENDING) {
            return;
        }

        $invitation->update([
            'status' => CareRequestInvitation::STATUS_DECLINED,
            'responded_at' => now(),
        ]);

        CaregiverResponseMetrics::recomputeForCaregiver((int) auth()->id());

        if ($invitation->family) {
            app(MarketplaceNotificationService::class)->notify(
                recipients: $invitation->family,
                eventKey: MarketplaceEvent::INVITE_DECLINED,
                title: 'Invitation declined',
                body: auth()->user()->name.' declined your invitation.',
                url: route('family.requests.show', $invitation->care_request_id),
                payload: ['care_request_id' => $invitation->care_request_id],
                subject: $invitation
            );
        }

        FunnelTracker::track('care_request_invitation_declined', auth()->user(), $invitation, [
            'care_request_id' => $invitation->care_request_id,
        ]);

        session()->flash('status', 'Invitation declined.');
    }

    private function findOwnInvitation(int $invitationId): CareRequestInvitation
    {
        return CareRequestInvitation::query()
            ->where('caregiver_user_id', auth()->id())
            ->with([
                'careRequest:id,title,request_type,city,state,requested_start_at,recurring_days,recurring_start_time,recurring_end_time',
                'careRequest.recipient:id,care_request_id,recipient_is_requester,full_name,relationship_to_family',
                'family:id,name',
                'application:id',
                'application.conversation:id,care_request_application_id',
            ])
            ->findOrFail($invitationId);
    }

    public function render()
    {
        CareRequestInvitation::query()
            ->where('caregiver_user_id', auth()->id())
            ->where('status', CareRequestInvitation::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => CareRequestInvitation::STATUS_EXPIRED]);

        $invitations = CareRequestInvitation::query()
            ->with([
                'careRequest:id,title,request_type,city,state,requested_start_at,recurring_days,recurring_start_time,recurring_end_time',
                'careRequest.recipient:id,care_request_id,recipient_is_requester,full_name,relationship_to_family',
                'family:id,name',
                'application:id',
                'application.conversation:id,care_request_application_id',
            ])
            ->where('caregiver_user_id', auth()->id())
            ->when($this->status !== 'all', fn ($query) => $query->where('status', $this->status))
            ->latest()
            ->get();

        return view('livewire.caregiver.invitations-index', [
            'invitations' => $invitations,
            'prelaunchMode' => CaregiverPrelaunch::enabled(),
        ]);
    }
}
