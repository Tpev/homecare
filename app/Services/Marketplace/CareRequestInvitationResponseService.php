<?php

namespace App\Services\Marketplace;

use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareRequestInvitation;
use App\Models\User;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Support\CaregiverResponseMetrics;
use App\Support\FunnelTracker;
use App\Support\MarketplaceEvent;
use App\Support\MarketplacePricing;
use Illuminate\Support\Facades\DB;

class CareRequestInvitationResponseService
{
    public function __construct(
        private readonly MarketplaceNotificationService $notifications,
        private readonly MarketplacePricing $pricing,
    ) {
    }

    /**
     * @return array{ok: bool, message: string, conversation?: CareRequestConversation}
     */
    public function accept(CareRequestInvitation $invitation, User $caregiver, string $source = 'caregiver_invitation'): array
    {
        if ((int) $invitation->caregiver_user_id !== (int) $caregiver->id) {
            abort(404);
        }

        $caregiverProfile = $caregiver->caregiverProfile;
        if (! $caregiverProfile || ! $caregiverProfile->isMarketplaceReady()) {
            return ['ok' => false, 'message' => 'Complete your caregiver profile before accepting invitations.'];
        }

        if ($invitation->isExpired()) {
            $invitation->update(['status' => CareRequestInvitation::STATUS_EXPIRED]);

            return ['ok' => false, 'message' => 'Invitation expired.'];
        }

        if ($invitation->status !== CareRequestInvitation::STATUS_PENDING) {
            return ['ok' => false, 'message' => 'This invitation was already handled.'];
        }

        $conversation = DB::transaction(function () use ($invitation, $caregiver, $caregiverProfile) {
            $invitation->loadMissing(['careRequest', 'family']);

            $application = CareRequestApplication::query()->firstOrNew([
                'care_request_id' => $invitation->care_request_id,
                'caregiver_user_id' => $caregiver->id,
            ]);

            $existingStatus = $application->exists ? $application->status : null;
            $nextStatus = in_array($existingStatus, [
                CareRequestApplication::STATUS_HIRED,
                CareRequestApplication::STATUS_SHORTLISTED,
            ], true)
                ? $existingStatus
                : CareRequestApplication::STATUS_SHORTLISTED;

            $platformRate = (float) ($caregiverProfile->resolvePlatformHourlyRate() ?? 0);
            $applicationRate = $platformRate > 0 && $invitation->careRequest
                ? $this->pricing->hourlyRateForRequest($invitation->careRequest, $platformRate)
                : null;

            $application->fill([
                'status' => $nextStatus,
                'proposed_rate' => $application->proposed_rate ?: $applicationRate,
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

            return CareRequestConversation::findOrCreateForApplication(
                $application->loadMissing('careRequest'),
                $caregiver->id
            );
        });

        CaregiverResponseMetrics::recomputeForCaregiver((int) $caregiver->id);

        $invitation->loadMissing(['family', 'careRequest']);
        if ($invitation->family) {
            $this->notifications->notify(
                recipients: $invitation->family,
                eventKey: MarketplaceEvent::INVITE_ACCEPTED,
                title: 'Invitation accepted',
                body: $caregiver->name.' accepted your invitation.',
                url: route('family.requests.show', $invitation->care_request_id),
                payload: ['care_request_id' => $invitation->care_request_id],
                subject: $invitation
            );
        }

        FunnelTracker::track('care_request_invitation_accepted', $caregiver, $invitation, [
            'care_request_id' => $invitation->care_request_id,
            'source' => $source,
        ]);

        return [
            'ok' => true,
            'message' => 'Invitation accepted. The family can now hire you.',
            'conversation' => $conversation,
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function decline(CareRequestInvitation $invitation, User $caregiver, string $source = 'caregiver_invitation'): array
    {
        if ((int) $invitation->caregiver_user_id !== (int) $caregiver->id) {
            abort(404);
        }

        if ($invitation->status !== CareRequestInvitation::STATUS_PENDING) {
            return ['ok' => false, 'message' => 'This invitation was already handled.'];
        }

        $invitation->update([
            'status' => CareRequestInvitation::STATUS_DECLINED,
            'responded_at' => now(),
        ]);

        CaregiverResponseMetrics::recomputeForCaregiver((int) $caregiver->id);

        $invitation->loadMissing('family');
        if ($invitation->family) {
            $this->notifications->notify(
                recipients: $invitation->family,
                eventKey: MarketplaceEvent::INVITE_DECLINED,
                title: 'Invitation declined',
                body: $caregiver->name.' declined your invitation.',
                url: route('family.requests.show', $invitation->care_request_id),
                payload: ['care_request_id' => $invitation->care_request_id],
                subject: $invitation
            );
        }

        FunnelTracker::track('care_request_invitation_declined', $caregiver, $invitation, [
            'care_request_id' => $invitation->care_request_id,
            'source' => $source,
        ]);

        return ['ok' => true, 'message' => 'Invitation declined.'];
    }
}
