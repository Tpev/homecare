<?php

namespace App\Livewire\Caregiver;

use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareRequestInvitation;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Support\CaregiverResponseMetrics;
use App\Support\CaregiverWorkInboxBuilder;
use App\Support\FunnelTracker;
use App\Support\MarketplaceEvent;
use Illuminate\Support\Facades\DB;
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
        ['label' => 'Recommended', 'value' => 'recommended'],
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
            'source' => 'work_inbox',
        ]);

        session()->flash('status', 'Invitation accepted. Conversation opened.');
        $this->redirect(route('messages.show', $conversation->id, false), navigate: true);
    }

    public function declineInvitation(int $invitationId): void
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
            'source' => 'work_inbox',
        ]);

        session()->flash('status', 'Invitation declined.');
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
            'counts' => $builder->countsForUser($user),
            'items' => $builder->buildForUser($user, $this->scope, $this->sort, 50),
        ]);
    }
}
