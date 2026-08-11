<?php

namespace App\Livewire\Caregiver;

use App\Models\CareRequestInvitation;
use App\Services\Marketplace\CareRequestInvitationResponseService;
use App\Support\CaregiverPrelaunch;
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
        $invitation = $this->findOwnInvitation($invitationId);
        $result = app(CareRequestInvitationResponseService::class)->accept(
            $invitation,
            auth()->user()
        );

        if ($result['ok'] ?? false) {
            $this->redirect(route('care-requests.apply', $invitation->care_request_id, false), navigate: true);

            return;
        }

        session()->flash('status', $result['message']);
    }

    public function decline(int $invitationId): void
    {
        $invitation = $this->findOwnInvitation($invitationId);
        $result = app(CareRequestInvitationResponseService::class)->decline(
            $invitation,
            auth()->user()
        );

        session()->flash('status', $result['message']);
    }

    private function findOwnInvitation(int $invitationId): CareRequestInvitation
    {
        return CareRequestInvitation::query()
            ->where('caregiver_user_id', auth()->id())
            ->with([
                'careRequest:id,title,request_type,city,state,requested_start_at,recurring_days,recurring_start_time,recurring_end_time,recurring_schedule',
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
                'careRequest:id,title,request_type,city,state,requested_start_at,recurring_days,recurring_start_time,recurring_end_time,recurring_schedule',
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
