<?php

namespace App\Services\Messaging;

use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareRequestInvitation;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Support\Facades\DB;

class CareRequestChatService
{
    public function closedReason(CareRequest $request, int $caregiverId): ?string
    {
        $application = $request->applications()->where('caregiver_user_id', $caregiverId)->first();

        // Keep visit coordination available after recruitment has finished.
        if ($application?->status === CareRequestApplication::STATUS_HIRED) {
            return null;
        }

        if ($request->status !== CareRequest::STATUS_OPEN || ! $request->isAcceptingApplications()
            || ($request->isRecurring() && $request->recurring_ends_on?->copy()->endOfDay()->isPast())) {
            return 'This request is no longer accepting caregivers. You can still read this conversation.';
        }

        if ($application) {
            return in_array($application->status, [CareRequestApplication::STATUS_APPLIED, CareRequestApplication::STATUS_SHORTLISTED], true)
                ? null
                : 'This application is no longer active. You can still read this conversation.';
        }

        $invitation = $request->invitations()->where('caregiver_user_id', $caregiverId)->first();
        if ($invitation?->status === CareRequestInvitation::STATUS_PENDING && ! $invitation->isExpired()) {
            return null;
        }

        return 'There is no active invitation or application for this request. You can still read any previous messages.';
    }

    public function open(User $actor, CareRequest $request, int $caregiverId): CareRequestConversation
    {
        return DB::transaction(function () use ($actor, $request, $caregiverId): CareRequestConversation {
            $request = CareRequest::query()->lockForUpdate()->findOrFail($request->id);
            $allowed = $actor->role === 'family'
                ? app(FamilyAccountContext::class)->canAccessRecord($actor, $request)
                : $actor->role === 'caregiver' && (int) $actor->id === $caregiverId;
            abort_unless($allowed, 403);

            $conversation = $request->conversations()->where('caregiver_user_id', $caregiverId)->first();
            if ($conversation) {
                abort_unless($conversation->isParticipant($actor), 403);
            } else {
                abort_unless($this->closedReason($request, $caregiverId) === null, 403);
            }

            $application = $request->applications()->where('caregiver_user_id', $caregiverId)->first();

            return CareRequestConversation::findOrCreateForRequest($request, $caregiverId, $actor->id, $application);
        }, 3);
    }
}
