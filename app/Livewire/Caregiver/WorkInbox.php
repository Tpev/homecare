<?php

namespace App\Livewire\Caregiver;

use App\Models\CareRequestInvitation;
use App\Services\Marketplace\CareRequestInvitationResponseService;
use App\Support\CaregiverPrelaunch;
use App\Support\CaregiverWorkInboxBuilder;
use App\Support\FunnelTracker;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class WorkInbox extends Component
{
    public string $scope = 'all';
    public string $sort = 'priority';

    public array $scopeOptions = [
        ['label' => 'All', 'value' => 'all'],
        ['label' => 'Needs response', 'value' => 'needs_response'],
        ['label' => 'New requests', 'value' => 'new_requests'],
        ['label' => 'Applied', 'value' => 'applied'],
        ['label' => 'Hired', 'value' => 'hired'],
        ['label' => 'Completed', 'value' => 'completed'],
    ];

    public array $sortOptions = [
        ['label' => 'Priority', 'value' => 'priority'],
        ['label' => 'Newest', 'value' => 'newest'],
        ['label' => 'Start soon', 'value' => 'start_soon'],
        ['label' => 'Best fit', 'value' => 'best_fit'],
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'caregiver', 403);
        FunnelTracker::track('work_inbox_viewed', auth()->user());
    }

    public function acceptInvitation(int $invitationId): void
    {
        $invitation = $this->findOwnInvitation($invitationId);
        $result = app(CareRequestInvitationResponseService::class)->accept(
            $invitation,
            auth()->user(),
            'work_inbox'
        );

        session()->flash('status', $result['message']);

        if (($result['ok'] ?? false) && isset($result['conversation'])) {
            $this->redirect(route('messages.show', $result['conversation']->id, false), navigate: true);
        }
    }

    public function declineInvitation(int $invitationId): void
    {
        $invitation = $this->findOwnInvitation($invitationId);
        $result = app(CareRequestInvitationResponseService::class)->decline(
            $invitation,
            auth()->user(),
            'work_inbox'
        );

        session()->flash('status', $result['message']);
    }

    private function findOwnInvitation(int $invitationId): CareRequestInvitation
    {
        return CareRequestInvitation::query()
            ->where('caregiver_user_id', auth()->id())
            ->with([
                'careRequest:id,title,request_type,city,state,requested_start_at,recurring_days,recurring_start_time,recurring_end_time',
                'family:id,name',
                'application:id',
                'application.conversation:id,care_request_application_id',
            ])
            ->findOrFail($invitationId);
    }

    public function render(CaregiverWorkInboxBuilder $builder)
    {
        $user = auth()->user();

        return view('livewire.caregiver.work-inbox', [
            'prelaunchMode' => CaregiverPrelaunch::enabled(),
            'counts' => $builder->countsForUser($user),
            'items' => $builder->buildForUser($user, $this->scope, $this->sort, 50),
        ]);
    }
}
