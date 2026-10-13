<?php

namespace App\Livewire\Family;

use App\Models\CareArrangement;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\ContinuousCoveragePlan;
use App\Services\ContinuousCoverage\ContinuousCoverageAccess;
use App\Services\Family\FamilyCareActionService;
use App\Services\Family\FamilyCarePresentationService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class RequestsIndex extends Component
{
    private const ACTION_PREVIEW_LIMIT = 3;

    // Kept for compatible deep links and existing Livewire callers. The
    // overview is intentionally no longer a request-management table.
    public string $status = 'all';

    public string $requestType = 'all';

    public string $sort = 'latest';

    #[Url(as: 'care', except: 0)]
    public int $sharedCareId = 0;

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'family', 403);
    }

    public function render(
        FamilyCareActionService $actions,
        FamilyCarePresentationService $presentation,
        ContinuousCoverageAccess $continuousCoverageAccess,
    ) {
        $account = app(FamilyAccountContext::class)->account(auth()->user());

        $sharedCareSchedules = CareArrangement::forFamilyAccount($account)->where('status', 'active')
            ->with('sourceRequest.recipient')->latest()->get();
        $activeSharedCare = $sharedCareSchedules->firstWhere('id', $this->sharedCareId) ?: $sharedCareSchedules->first();
        if ($activeSharedCare) {
            $this->sharedCareId = $activeSharedCare->id;
        }

        $familyActions = $actions->forAccount($account);
        if ($activeSharedCare) {
            // The selected care home's reply card already provides this action.
            $familyActions = $familyActions->reject(fn ($action) => ($action['type'] ?? '') === 'applicants'
                && ($action['resource_type'] ?? '') === 'care_request'
                && ($action['resource_id'] ?? 0) === $activeSharedCare->source_care_request_id)->values();
        }
        $attentionCount = $familyActions->count();
        $familyActions = $familyActions->take(self::ACTION_PREVIEW_LIMIT)->values();

        $upcomingCount = $presentation->upcomingVisitCount($account);
        $nextVisit = $presentation->upcomingVisits($account, 1)->first();
        $otherUpcomingVisits = $activeSharedCare
            ? $presentation->upcomingVisits($account, 3, excludingArrangementId: $activeSharedCare->id)
            : collect();

        $openRequests = CareRequest::query()
            ->forFamilyAccount($account)
            ->where('is_system_generated', false)
            ->whereIn('status', [CareRequest::STATUS_OPEN, CareRequest::STATUS_DRAFT])
            ->count();

        $regularPlansQuery = CarePlan::query()
            ->forFamilyAccount($account)
            ->whereNotIn('status', [
                CarePlan::STATUS_ENDED,
                CarePlan::STATUS_CANCELLED,
                CarePlan::STATUS_DECLINED,
                CarePlan::STATUS_EXPIRED,
            ]);
        $regularPlans = (clone $regularPlansQuery)->count();
        $arrangingPlans = (clone $regularPlansQuery)->whereIn('status', [
            CarePlan::STATUS_PENDING_CAREGIVER,
            CarePlan::STATUS_COUNTERED,
            CarePlan::STATUS_DRAFT,
        ])->count();
        $ongoingPlans = (clone $regularPlansQuery)->whereIn('status', [
            CarePlan::STATUS_ACTIVE,
            CarePlan::STATUS_PAYMENT_ATTENTION,
            CarePlan::STATUS_PAUSED,
        ])->count();

        $continuousCoverageVisible = $continuousCoverageAccess->visibleInNavigation(auth()->user());
        $continuousPlans = $continuousCoverageVisible
            ? ContinuousCoveragePlan::query()
                ->forFamilyAccount($account)
                ->whereIn('status', [
                    ContinuousCoveragePlan::STATUS_ACTIVE,
                    ContinuousCoveragePlan::STATUS_PAUSED,
                ])
                ->count()
            : 0;

        return view('livewire.family.requests-index', [
            'sharedCareSchedules' => $sharedCareSchedules,
            'activeSharedCare' => $activeSharedCare,
            'otherUpcomingVisits' => $otherUpcomingVisits,
            'familyActions' => $familyActions,
            'attentionCount' => $attentionCount,
            'nextVisit' => $nextVisit,
            'upcomingCount' => $upcomingCount,
            'openRequestCount' => $openRequests,
            'beingArrangedCount' => $openRequests + $arrangingPlans,
            'ongoingPlanCount' => $ongoingPlans,
            'planCount' => $regularPlans,
            'continuousPlanCount' => $continuousPlans,
            'continuousCoverageVisible' => $continuousCoverageVisible,
            'arrangementCount' => $openRequests + $regularPlans + $continuousPlans,
        ]);
    }
}
