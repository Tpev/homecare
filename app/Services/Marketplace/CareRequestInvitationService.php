<?php

namespace App\Services\Marketplace;

use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestInvitation;
use App\Models\User;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Support\FunnelTracker;
use App\Support\MarketplaceEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CareRequestInvitationService
{
    public const STATE_SENT = 'sent';

    public const STATE_REINVITED = 'reinvited';

    public const STATE_PENDING = 'pending';

    public const STATE_ACCEPTED = 'accepted';

    public const STATE_REPLIED = 'replied';

    public const STATE_HIRED = 'hired';

    public const STATE_DECLINED = 'declined';

    public const STATE_EXPIRED = 'expired';

    public const STATE_CANCELLED = 'cancelled';

    public const STATE_REQUEST_UNAVAILABLE = 'request_unavailable';

    public const STATE_NOT_CAREGIVER = 'not_caregiver';

    public const STATE_NOT_READY = 'not_ready';

    public const STATE_NOT_ACCEPTING = 'not_accepting';

    public function __construct(
        private readonly MarketplaceNotificationService $notifications,
    ) {}

    public function send(
        User $family,
        CareRequest $careRequest,
        User $caregiver,
        ?string $message = null,
        bool $reinvite = false,
        string $source = 'care_request',
    ): CareRequestInvitationResult {
        if ($family->role !== 'family' || (int) $careRequest->family_user_id !== (int) $family->id) {
            throw new AuthorizationException('You cannot manage invitations for this request.');
        }

        $result = DB::transaction(function () use ($family, $careRequest, $caregiver, $message, $reinvite): CareRequestInvitationResult {
            $lockedRequest = CareRequest::query()->lockForUpdate()->findOrFail($careRequest->id);

            if ((int) $lockedRequest->family_user_id !== (int) $family->id) {
                throw new AuthorizationException('You cannot manage invitations for this request.');
            }

            if ($lockedRequest->status !== CareRequest::STATUS_OPEN) {
                return new CareRequestInvitationResult(
                    self::STATE_REQUEST_UNAVAILABLE,
                    'This care request is no longer open for invitations.'
                );
            }

            $application = CareRequestApplication::query()
                ->where('care_request_id', $lockedRequest->id)
                ->where('caregiver_user_id', $caregiver->id)
                ->lockForUpdate()
                ->first();

            if ($application) {
                if ($application->status === CareRequestApplication::STATUS_HIRED) {
                    return new CareRequestInvitationResult(
                        self::STATE_HIRED,
                        $caregiver->name.' is already the selected caregiver for this request.',
                        application: $application,
                    );
                }

                return new CareRequestInvitationResult(
                    self::STATE_REPLIED,
                    $caregiver->name.' already replied to this request. Open their reply instead of sending another invitation.',
                    application: $application,
                );
            }

            $invitation = CareRequestInvitation::query()
                ->where('care_request_id', $lockedRequest->id)
                ->where('caregiver_user_id', $caregiver->id)
                ->lockForUpdate()
                ->first();

            if ($invitation?->isExpired()) {
                $invitation->forceFill(['status' => CareRequestInvitation::STATUS_EXPIRED])->save();
            }

            if ($invitation) {
                if ($invitation->status === CareRequestInvitation::STATUS_PENDING) {
                    return new CareRequestInvitationResult(
                        self::STATE_PENDING,
                        'An invitation has already been sent to '.$caregiver->name.'.',
                        invitation: $invitation,
                    );
                }

                if ($invitation->status === CareRequestInvitation::STATUS_ACCEPTED) {
                    return new CareRequestInvitationResult(
                        self::STATE_ACCEPTED,
                        $caregiver->name.' already accepted this invitation.',
                        invitation: $invitation,
                    );
                }

                $historicalState = match ($invitation->status) {
                    CareRequestInvitation::STATUS_DECLINED => self::STATE_DECLINED,
                    CareRequestInvitation::STATUS_EXPIRED => self::STATE_EXPIRED,
                    CareRequestInvitation::STATUS_CANCELLED => self::STATE_CANCELLED,
                    default => self::STATE_CANCELLED,
                };

                if (! $reinvite) {
                    return new CareRequestInvitationResult(
                        $historicalState,
                        'This invitation is '.str_replace('_', ' ', $historicalState).'. Choose "Invite again" if you want to send a new invitation.',
                        invitation: $invitation,
                    );
                }
            }

            $profile = $caregiver->caregiverProfile;
            if ($caregiver->role !== 'caregiver') {
                return new CareRequestInvitationResult(
                    self::STATE_NOT_CAREGIVER,
                    'This person does not have a caregiver account.'
                );
            }

            if (! $profile || ! $profile->isMarketplaceReady()) {
                return new CareRequestInvitationResult(
                    self::STATE_NOT_READY,
                    $caregiver->name.' is not currently available in the caregiver marketplace.'
                );
            }

            if (! $profile->is_accepting_new_clients) {
                return new CareRequestInvitationResult(
                    self::STATE_NOT_ACCEPTING,
                    $caregiver->name.' is not accepting new clients right now.'
                );
            }

            $cleanMessage = trim((string) $message) ?: null;
            if ($invitation) {
                $invitation->forceFill([
                    'family_user_id' => $family->id,
                    'care_request_application_id' => null,
                    'status' => CareRequestInvitation::STATUS_PENDING,
                    'message' => $cleanMessage,
                    'expires_at' => now()->addHours(72),
                    'responded_at' => null,
                ])->save();

                return new CareRequestInvitationResult(
                    self::STATE_REINVITED,
                    'A new invitation was sent to '.$caregiver->name.'.',
                    invitation: $invitation->fresh(),
                    sentNow: true,
                );
            }

            $invitation = CareRequestInvitation::query()->firstOrCreate(
                [
                    'care_request_id' => $lockedRequest->id,
                    'caregiver_user_id' => $caregiver->id,
                ],
                [
                    'family_user_id' => $family->id,
                    'status' => CareRequestInvitation::STATUS_PENDING,
                    'message' => $cleanMessage,
                    'expires_at' => now()->addHours(72),
                ]
            );

            if (! $invitation->wasRecentlyCreated) {
                return new CareRequestInvitationResult(
                    self::STATE_PENDING,
                    'An invitation has already been sent to '.$caregiver->name.'.',
                    invitation: $invitation,
                );
            }

            return new CareRequestInvitationResult(
                self::STATE_SENT,
                'Invitation sent to '.$caregiver->name.'.',
                invitation: $invitation,
                sentNow: true,
            );
        }, 3);

        if (! $result->sentNow || ! $result->invitation) {
            return $result;
        }

        $invitation = $result->invitation->loadMissing('caregiver');
        $cycle = $result->state.'-'.$invitation->updated_at?->format('YmdHisv');
        $dedupeBase = 'care-request-invite-'.$invitation->id.'-'.$cycle;

        if ($invitation->caregiver) {
            $this->notifications->notify(
                recipients: $invitation->caregiver,
                eventKey: MarketplaceEvent::INVITATION_RECEIVED,
                title: $family->name.' invited you to a care request',
                body: 'Review the schedule, approximate location, requested care, and rate before you respond.',
                url: route('caregiver.invitations.index'),
                payload: ['care_request_id' => $careRequest->id],
                subject: $invitation,
                dedupeKey: $dedupeBase.'-caregiver',
            );
        }

        $this->notifications->notify(
            recipients: $family,
            eventKey: MarketplaceEvent::INVITATION_SENT,
            title: 'Invitation sent',
            body: 'Your invitation was sent to '.$caregiver->name.'.',
            url: route('family.requests.show', $careRequest->id),
            payload: [
                'care_request_id' => $careRequest->id,
                'caregiver_user_id' => $caregiver->id,
            ],
            subject: $invitation,
            dedupeKey: $dedupeBase.'-family',
        );

        FunnelTracker::track('care_request_invitation_sent', $family, $invitation, [
            'care_request_id' => $careRequest->id,
            'caregiver_user_id' => $caregiver->id,
            'source' => $source,
            'reinvitation' => $result->state === self::STATE_REINVITED,
        ]);

        return $result;
    }
}
