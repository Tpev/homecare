<?php

namespace App\Services\CareRecipientProfiles;

use App\Models\CarePlan;
use App\Models\CareRecipient;
use App\Models\CareRecipientProfile;
use App\Models\CareRecipientProfileVersion;
use App\Models\CareRequest;
use App\Models\ContinuousCoveragePlan;
use App\Models\ContinuousCoverageRosterMember;
use App\Models\FamilyAccountActivityLog;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Support\MarketplaceEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CareRecipientProfileAttachmentService
{
    public function __construct(
        private readonly FamilyAccountContext $familyAccounts,
        private readonly MarketplaceNotificationService $notifications,
    ) {}

    public function attachToRequestRecipient(CareRecipient $recipient, ?CareRecipientProfile $profile, User $actor): CareRecipient
    {
        $request = $recipient->careRequest()->withoutGlobalScopes()->firstOrFail();
        $account = $this->familyAccounts->account($actor);
        if ((int) $request->family_account_id !== (int) $account->id) {
            abort(404);
        }

        [$profileId, $versionId] = $profile ? $this->readyPair($profile, $account->id) : [null, null];
        $recipient->forceFill([
            'care_recipient_profile_id' => $profileId,
            'care_recipient_profile_version_id' => $versionId,
        ])->save();

        if ($profile) {
            $this->log($profile, $actor, 'care_profile_attached', ['care_request_id' => $request->id]);
        }

        return $recipient->fresh(['careRecipientProfileVersion']);
    }

    public function copyRequestToCarePlan(CareRequest $source, CarePlan $plan): void
    {
        $source->loadMissing('recipient');
        $recipient = $source->recipient;
        if (! $recipient?->care_recipient_profile_id || ! $recipient->care_recipient_profile_version_id) {
            return;
        }
        $this->assertPairForAccount(
            (int) $recipient->care_recipient_profile_id,
            (int) $recipient->care_recipient_profile_version_id,
            (int) $plan->family_account_id,
        );
        $plan->forceFill([
            'care_recipient_profile_id' => $recipient->care_recipient_profile_id,
            'care_recipient_profile_version_id' => $recipient->care_recipient_profile_version_id,
        ])->save();
    }

    public function attachToCoveragePlan(ContinuousCoveragePlan $plan, ?CareRecipientProfile $profile, User $actor): void
    {
        $account = $this->familyAccounts->account($actor);
        if ((int) $plan->family_account_id !== (int) $account->id) {
            abort(404);
        }
        [$profileId, $versionId] = $profile ? $this->readyPair($profile, $account->id) : [null, null];
        $plan->forceFill([
            'care_recipient_profile_id' => $profileId,
            'care_recipient_profile_version_id' => $versionId,
        ])->save();
        if ($profile) {
            $this->log($profile, $actor, 'care_profile_attached', ['continuous_coverage_plan_id' => $plan->id]);
        }
    }

    /** @return list<array{type:string,id:int,title:string}> */
    public function affectedActiveCare(CareRecipientProfile $profile, User $actor): array
    {
        $account = $this->familyAccounts->account($actor);
        $owned = CareRecipientProfile::query()->forFamilyAccount($account)->findOrFail($profile->id);
        $latest = (int) $owned->latest_ready_version_id;
        if ($latest < 1) {
            return [];
        }

        $items = [];
        CareRecipient::query()
            ->where('care_recipient_profile_id', $owned->id)
            ->where(fn ($query) => $query->whereNull('care_recipient_profile_version_id')->orWhere('care_recipient_profile_version_id', '!=', $latest))
            ->whereHas('careRequest', fn ($query) => $query->where('family_account_id', $account->id)->whereIn('status', [CareRequest::STATUS_OPEN, CareRequest::STATUS_FILLED]))
            ->with('careRequest:id,title,status')
            ->get()
            ->each(function (CareRecipient $recipient) use (&$items): void {
                $items[] = ['type' => 'request', 'id' => $recipient->care_request_id, 'title' => $recipient->careRequest->title ?: 'Care request'];
            });

        CarePlan::query()->forFamilyAccount($account)
            ->where('care_recipient_profile_id', $owned->id)
            ->where(fn ($query) => $query->whereNull('care_recipient_profile_version_id')->orWhere('care_recipient_profile_version_id', '!=', $latest))
            ->whereIn('status', [CarePlan::STATUS_PENDING_CAREGIVER, CarePlan::STATUS_COUNTERED, CarePlan::STATUS_ACTIVE, CarePlan::STATUS_PAYMENT_ATTENTION, CarePlan::STATUS_PAUSED])
            ->get(['id', 'title'])
            ->each(fn (CarePlan $plan) => $items[] = ['type' => 'regular', 'id' => $plan->id, 'title' => $plan->displayTitle()]);

        ContinuousCoveragePlan::query()->forFamilyAccount($account)
            ->where('care_recipient_profile_id', $owned->id)
            ->where(fn ($query) => $query->whereNull('care_recipient_profile_version_id')->orWhere('care_recipient_profile_version_id', '!=', $latest))
            ->whereIn('status', [ContinuousCoveragePlan::STATUS_ACTIVE, ContinuousCoveragePlan::STATUS_PAUSED])
            ->get(['id', 'title'])
            ->each(fn (ContinuousCoveragePlan $plan) => $items[] = ['type' => 'coverage', 'id' => $plan->id, 'title' => $plan->title]);

        return collect($items)->unique(fn ($item) => $item['type'].':'.$item['id'])->values()->all();
    }

    /** @return array{requests:int,plans:int,coverage:int,notified:int} */
    public function applyLatestToActiveCare(CareRecipientProfile $profile, User $actor): array
    {
        $account = $this->familyAccounts->account($actor);
        $owned = CareRecipientProfile::query()->forFamilyAccount($account)->with('latestReadyVersion')->findOrFail($profile->id);
        if (! $owned->isReady()) {
            throw ValidationException::withMessages(['profile' => 'Save this profile as Ready to use before updating current care.']);
        }

        $notificationTargets = [];
        $counts = DB::transaction(function () use ($owned, $account, &$notificationTargets): array {
            $requestRecipients = CareRecipient::query()
                ->where('care_recipient_profile_id', $owned->id)
                ->whereHas('careRequest', fn ($query) => $query->where('family_account_id', $account->id)->whereIn('status', [CareRequest::STATUS_OPEN, CareRequest::STATUS_FILLED]))
                ->with('careRequest.booking')
                ->lockForUpdate()
                ->get();
            foreach ($requestRecipients as $recipient) {
                $recipient->forceFill(['care_recipient_profile_version_id' => $owned->latest_ready_version_id])->save();
                $booking = $recipient->careRequest?->booking;
                if ($booking && ! in_array($booking->status, ['cancelled', 'disputed'], true)) {
                    $notificationTargets[$booking->caregiver_user_id] = [$booking->caregiver_user_id, $recipient->careRequest];
                }
            }

            $plans = CarePlan::query()->forFamilyAccount($account)
                ->where('care_recipient_profile_id', $owned->id)
                ->whereIn('status', [CarePlan::STATUS_PENDING_CAREGIVER, CarePlan::STATUS_COUNTERED, CarePlan::STATUS_ACTIVE, CarePlan::STATUS_PAYMENT_ATTENTION, CarePlan::STATUS_PAUSED])
                ->lockForUpdate()->get();
            foreach ($plans as $plan) {
                $plan->forceFill(['care_recipient_profile_version_id' => $owned->latest_ready_version_id])->save();
                if (in_array($plan->status, [CarePlan::STATUS_ACTIVE, CarePlan::STATUS_PAYMENT_ATTENTION, CarePlan::STATUS_PAUSED], true)) {
                    $notificationTargets[$plan->caregiver_user_id] = [$plan->caregiver_user_id, $plan];
                }
            }

            $coveragePlans = ContinuousCoveragePlan::query()->forFamilyAccount($account)
                ->where('care_recipient_profile_id', $owned->id)
                ->whereIn('status', [ContinuousCoveragePlan::STATUS_ACTIVE, ContinuousCoveragePlan::STATUS_PAUSED])
                ->lockForUpdate()->get();
            foreach ($coveragePlans as $plan) {
                $plan->forceFill(['care_recipient_profile_version_id' => $owned->latest_ready_version_id])->save();
                $plan->rosterMembers()->whereIn('status', [ContinuousCoverageRosterMember::STATUS_ACTIVE, ContinuousCoverageRosterMember::STATUS_PAUSED])
                    ->pluck('caregiver_user_id')
                    ->each(fn ($id) => $notificationTargets[$id] = [(int) $id, $plan]);
            }

            return ['requests' => $requestRecipients->count(), 'plans' => $plans->count(), 'coverage' => $coveragePlans->count()];
        });

        $notified = 0;
        foreach ($notificationTargets as [$userId, $subject]) {
            $caregiver = User::query()->find($userId);
            if (! $caregiver) {
                continue;
            }
            $this->notifications->notify(
                recipients: $caregiver,
                eventKey: MarketplaceEvent::CARE_PROFILE_UPDATED,
                title: 'Care profile updated',
                body: 'The family updated the care profile. Review it before your next visit.',
                url: $this->caregiverUrl($subject),
                payload: [],
                subject: $subject,
                dedupeKey: 'care-profile-updated:version-'.$owned->latest_ready_version_id.'-user-'.$caregiver->id,
            );
            $notified++;
        }

        $this->log($owned, $actor, 'care_profile_applied_to_active_care', array_merge($counts, ['notified' => $notified]));

        return array_merge($counts, ['notified' => $notified]);
    }

    /** @return array{0:int,1:int} */
    private function readyPair(CareRecipientProfile $profile, int $accountId): array
    {
        $owned = CareRecipientProfile::query()->forFamilyAccount($accountId)->findOrFail($profile->id);
        if (! $owned->isReady() || $owned->isArchived()) {
            throw ValidationException::withMessages(['profile' => 'Choose a care profile that is Ready to use.']);
        }
        $this->assertPairForAccount($owned->id, $owned->latest_ready_version_id, $accountId);

        return [$owned->id, $owned->latest_ready_version_id];
    }

    private function assertPairForAccount(int $profileId, int $versionId, int $accountId): void
    {
        $valid = CareRecipientProfileVersion::query()
            ->whereKey($versionId)
            ->where('care_recipient_profile_id', $profileId)
            ->whereHas('profile', fn ($query) => $query->withoutGlobalScopes()->where('family_account_id', $accountId))
            ->exists();
        if (! $valid) {
            abort(404);
        }
    }

    private function caregiverUrl(Model $subject): ?string
    {
        return match (true) {
            $subject instanceof CareRequest => route('care-requests.apply', $subject),
            $subject instanceof CarePlan => route('caregiver.regular-clients.index'),
            $subject instanceof ContinuousCoveragePlan => route('caregiver.continuous-coverage.index'),
            default => null,
        };
    }

    /** @param array<string, mixed> $metadata */
    private function log(CareRecipientProfile $profile, User $actor, string $action, array $metadata = []): void
    {
        FamilyAccountActivityLog::query()->create([
            'family_account_id' => $profile->family_account_id,
            'actor_user_id' => $actor->id,
            'action' => $action,
            'metadata' => array_merge(['care_recipient_profile_id' => $profile->id], $metadata),
        ]);
    }
}
