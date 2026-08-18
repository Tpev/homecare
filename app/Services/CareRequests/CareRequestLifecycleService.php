<?php

namespace App\Services\CareRequests;

use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestInvitation;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Support\FunnelTracker;
use App\Support\MarketplaceEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CareRequestLifecycleService
{
    public function __construct(
        private readonly FamilyAccountContext $familyAccounts,
        private readonly MarketplaceNotificationService $notifications,
    ) {}

    /** @return array{request:CareRequest,affected_caregiver_count:int} */
    public function withdraw(User $actor, CareRequest $careRequest): array
    {
        $account = $this->familyAccounts->account($actor);

        $result = DB::transaction(function () use ($careRequest, $account): array {
            $request = CareRequest::query()
                ->forFamilyAccount($account)
                ->with('booking:id,care_request_id')
                ->lockForUpdate()
                ->findOrFail($careRequest->id);

            if (! in_array($request->status, [CareRequest::STATUS_DRAFT, CareRequest::STATUS_OPEN], true)) {
                throw ValidationException::withMessages([
                    'request' => 'Only a draft or open request can be withdrawn. This request is '.$request->status.'.',
                ]);
            }
            if ($request->booking) {
                throw ValidationException::withMessages([
                    'request' => 'This request already has a visit. Use the visit change or cancellation flow instead.',
                ]);
            }

            $activeApplications = $request->applications()
                ->whereIn('status', [CareRequestApplication::STATUS_APPLIED, CareRequestApplication::STATUS_SHORTLISTED])
                ->get(['id', 'caregiver_user_id']);
            $pendingInvitations = $request->invitations()
                ->where('status', CareRequestInvitation::STATUS_PENDING)
                ->get(['id', 'caregiver_user_id']);

            if ($activeApplications->isNotEmpty()) {
                $request->applications()->whereKey($activeApplications->pluck('id')->all())
                    ->update(['status' => CareRequestApplication::STATUS_NOT_SELECTED]);
            }
            if ($pendingInvitations->isNotEmpty()) {
                $request->invitations()->whereKey($pendingInvitations->pluck('id')->all())->update([
                    'status' => CareRequestInvitation::STATUS_CANCELLED,
                    'expires_at' => now(),
                ]);
            }

            $request->forceFill(['status' => CareRequest::STATUS_CANCELLED])->save();

            return [
                'request' => $request->fresh(),
                'caregiver_ids' => $activeApplications->pluck('caregiver_user_id')
                    ->merge($pendingInvitations->pluck('caregiver_user_id'))
                    ->filter()->unique()->values()->all(),
            ];
        }, 3);

        $caregivers = User::query()->whereIn('id', $result['caregiver_ids'])->get();
        if ($caregivers->isNotEmpty()) {
            $this->notifications->notify(
                recipients: $caregivers,
                eventKey: MarketplaceEvent::CARE_REQUEST_WITHDRAWN,
                title: 'Care request withdrawn',
                body: 'A family withdrew a request you were following. No action is needed.',
                url: route('caregiver.work-inbox.index'),
                payload: ['care_request_id' => $result['request']->id],
                subject: $result['request'],
                dedupeKey: 'care-request-withdrawn:request-'.$result['request']->id,
            );
        }

        FunnelTracker::track('care_request_withdrawn', $actor, $result['request'], [
            'affected_caregivers' => $caregivers->count(),
        ]);

        return ['request' => $result['request'], 'affected_caregiver_count' => $caregivers->count()];
    }
}
