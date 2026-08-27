<?php

namespace App\Livewire\Family;

use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\ContinuousCoveragePlan;
use App\Services\ContinuousCoverage\ContinuousCoverageAccess;
use App\Services\Family\FamilyCarePresentationService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class RegularCareIndex extends Component
{
    private const PAGE_SIZE = 8;

    #[Url(as: 'person')]
    public string $recipient = 'all';

    #[Url(as: 'view')]
    public string $planView = 'current';

    #[Url(as: 'type')]
    public string $careType = 'all';

    public int $visibleLimit = self::PAGE_SIZE;

    public array $planViewOptions = [
        ['label' => 'Current care', 'value' => 'current'],
        ['label' => 'Being arranged', 'value' => 'arranging'],
        ['label' => 'Ongoing care', 'value' => 'ongoing'],
        ['label' => 'Past care', 'value' => 'past'],
        ['label' => 'All care', 'value' => 'all'],
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'family', 403);
    }

    public function updatingRecipient(): void
    {
        $this->visibleLimit = self::PAGE_SIZE;
    }

    public function updatingPlanView(): void
    {
        $this->visibleLimit = self::PAGE_SIZE;
    }

    public function updatingCareType(): void
    {
        $this->visibleLimit = self::PAGE_SIZE;
    }

    public function loadMoreArrangements(): void
    {
        $this->visibleLimit += self::PAGE_SIZE;
    }

    // Retain the previous public action for compatible Livewire callers.
    public function loadMorePlans(): void
    {
        $this->loadMoreArrangements();
    }

    public function render(
        FamilyCarePresentationService $presentation,
        ContinuousCoverageAccess $continuousCoverageAccess,
    ) {
        $account = app(FamilyAccountContext::class)->account(auth()->user());
        $selectedRecipient = $this->recipient === 'all' ? null : $this->recipient;
        $continuousCoverageVisible = $continuousCoverageAccess->visibleInNavigation(auth()->user());
        $recipientOptions = collect([['label' => 'Everyone', 'value' => 'all']])
            ->merge($presentation->careRecipientNames($account)->map(
                fn (string $name): array => ['label' => $name, 'value' => $name]
            ))
            ->all();
        $careTypeOptions = [
            ['label' => 'All care types', 'value' => 'all'],
            ['label' => 'One-time care', 'value' => 'one_time'],
            ['label' => 'Recurring care', 'value' => 'regular'],
        ];
        if ($continuousCoverageVisible) {
            $careTypeOptions[] = ['label' => 'Continuous care', 'value' => 'continuous'];
        }

        $requestQuery = CareRequest::query()
            ->with([
                'recipient',
                'tasks:id,name',
            ])
            ->withCount([
                'applications',
                'applications as pending_candidate_count' => fn ($query) => $query->whereIn('status', [
                    CareRequestApplication::STATUS_APPLIED,
                    CareRequestApplication::STATUS_SHORTLISTED,
                ]),
            ])
            ->forFamilyAccount($account)
            ->where('is_system_generated', false)
            ->when($selectedRecipient, fn ($query) => $query->whereHas(
                'recipient',
                fn ($person) => $person->where('full_name', $selectedRecipient)
            ));
        $this->applyRequestView($requestQuery);
        if ($this->careType === 'one_time') {
            $requestQuery->where('request_type', CareRequest::TYPE_ONE_TIME);
        } elseif ($this->careType === 'regular') {
            $requestQuery->where('request_type', CareRequest::TYPE_RECURRING);
        } elseif ($this->careType === 'continuous') {
            $requestQuery->whereRaw('1 = 0');
        }

        $planQuery = CarePlan::query()
            ->with([
                'caregiver:id,name,email,city,state',
                'nextBooking:id,care_request_id,status,scheduled_start_at,scheduled_end_at',
                'nextBooking.payment:id,care_booking_id,status,last_error',
            ])
            ->forFamilyAccount($account)
            ->when($selectedRecipient, fn ($query) => $query->where('recipient_snapshot->full_name', $selectedRecipient));
        $this->applyPlanView($planQuery);
        if (in_array($this->careType, ['one_time', 'continuous'], true)) {
            $planQuery->whereRaw('1 = 0');
        }

        $requestCount = (clone $requestQuery)->count();
        $planCount = (clone $planQuery)->count();
        $requestItems = $requestQuery
            ->latest('updated_at')
            ->limit($this->visibleLimit)
            ->get()
            ->map(function (CareRequest $request) use ($presentation): array {
                $item = $presentation->request($request);
                $item['kind'] = 'request';
                $item['closed'] = in_array($request->status, [CareRequest::STATUS_CANCELLED, CareRequest::STATUS_EXPIRED], true);
                $item['sort_at'] = $request->updated_at;
                $item['priority'] = $item['date_passed'] || $item['status']['tone'] === 'amber' ? 0 : 2;

                return $item;
            });
        $planItems = $planQuery
            ->orderByRaw("CASE WHEN status = '".CarePlan::STATUS_PAYMENT_ATTENTION."' THEN 0 WHEN status = '".CarePlan::STATUS_COUNTERED."' THEN 1 ELSE 2 END")
            ->latest('updated_at')
            ->limit($this->visibleLimit)
            ->get()
            ->map(function (CarePlan $plan) use ($presentation): array {
                $item = $presentation->plan($plan);
                $item['kind'] = 'plan';
                $item['closed'] = in_array($plan->status, [
                    CarePlan::STATUS_ENDED,
                    CarePlan::STATUS_CANCELLED,
                    CarePlan::STATUS_DECLINED,
                    CarePlan::STATUS_EXPIRED,
                ], true);
                $item['sort_at'] = $plan->updated_at;
                $item['priority'] = in_array($plan->status, [CarePlan::STATUS_PAYMENT_ATTENTION, CarePlan::STATUS_COUNTERED], true) ? 0 : 1;
                if ($item['closed']) {
                    $item['action_label'] = 'View details';
                }

                return $item;
            });
        // Eloquent collections assume every item is a model when merging. These
        // two collections contain presentation arrays, so combine them as a
        // base collection instead.
        $arrangements = collect($requestItems->all())
            ->concat($planItems->all())
            ->sort(function (array $left, array $right): int {
                $priority = $left['priority'] <=> $right['priority'];

                return $priority !== 0
                    ? $priority
                    : (($right['sort_at']?->getTimestamp() ?? 0) <=> ($left['sort_at']?->getTimestamp() ?? 0));
            })
            ->take($this->visibleLimit)
            ->values();

        $continuousPlans = collect();
        $continuousCount = 0;
        if ($continuousCoverageVisible && in_array($this->careType, ['all', 'continuous'], true)) {
            $continuousQuery = ContinuousCoveragePlan::query()
                ->forFamilyAccount($account)
                ->when($selectedRecipient, fn ($query) => $query->where('recipient_snapshot->full_name', $selectedRecipient));
            match ($this->planView) {
                'arranging' => $continuousQuery->whereRaw('1 = 0'),
                'ongoing' => $continuousQuery->whereIn('status', [ContinuousCoveragePlan::STATUS_ACTIVE, ContinuousCoveragePlan::STATUS_PAUSED]),
                'active' => $continuousQuery->where('status', ContinuousCoveragePlan::STATUS_ACTIVE),
                'paused' => $continuousQuery->where('status', ContinuousCoveragePlan::STATUS_PAUSED),
                'past' => $continuousQuery->where('status', ContinuousCoveragePlan::STATUS_ENDED),
                'all' => null,
                default => $continuousQuery->whereIn('status', [ContinuousCoveragePlan::STATUS_ACTIVE, ContinuousCoveragePlan::STATUS_PAUSED]),
            };
            $continuousCount = (clone $continuousQuery)->count();
            $remainingSlots = max(0, $this->visibleLimit - $arrangements->count());
            $continuousPlans = $remainingSlots > 0
                ? $continuousQuery->latest('updated_at')->limit($remainingSlots)->get()
                : collect();
        }

        $totalArrangementCount = $requestCount + $planCount + $continuousCount;

        return view('livewire.family.regular-care-index', [
            'arrangements' => $arrangements,
            'continuousPlans' => $continuousPlans,
            'continuousCoverageVisible' => $continuousCoverageVisible,
            'recipientOptions' => $recipientOptions,
            'careTypeOptions' => $careTypeOptions,
            'totalArrangementCount' => $totalArrangementCount,
            'hasMoreArrangements' => $totalArrangementCount > ($arrangements->count() + $continuousPlans->count()),
        ]);
    }

    private function applyRequestView(Builder $query): void
    {
        $statuses = match ($this->planView) {
            'past' => [CareRequest::STATUS_CANCELLED, CareRequest::STATUS_EXPIRED],
            'ongoing', 'active', 'paused' => [],
            'all' => [CareRequest::STATUS_OPEN, CareRequest::STATUS_DRAFT, CareRequest::STATUS_CANCELLED, CareRequest::STATUS_EXPIRED],
            default => [CareRequest::STATUS_OPEN, CareRequest::STATUS_DRAFT],
        };

        $statuses === [] ? $query->whereRaw('1 = 0') : $query->whereIn('status', $statuses);
    }

    private function applyPlanView(Builder $query): void
    {
        $statuses = match ($this->planView) {
            'arranging' => [CarePlan::STATUS_PENDING_CAREGIVER, CarePlan::STATUS_COUNTERED, CarePlan::STATUS_DRAFT],
            'ongoing' => [CarePlan::STATUS_ACTIVE, CarePlan::STATUS_PAYMENT_ATTENTION, CarePlan::STATUS_PAUSED],
            'active' => [CarePlan::STATUS_ACTIVE, CarePlan::STATUS_PAYMENT_ATTENTION],
            'paused' => [CarePlan::STATUS_PAUSED],
            'past' => [CarePlan::STATUS_ENDED, CarePlan::STATUS_CANCELLED, CarePlan::STATUS_DECLINED, CarePlan::STATUS_EXPIRED],
            'all' => null,
            default => [
                CarePlan::STATUS_ACTIVE,
                CarePlan::STATUS_PAYMENT_ATTENTION,
                CarePlan::STATUS_PAUSED,
                CarePlan::STATUS_PENDING_CAREGIVER,
                CarePlan::STATUS_COUNTERED,
                CarePlan::STATUS_DRAFT,
            ],
        };

        if (is_array($statuses)) {
            $query->whereIn('status', $statuses);
        }
    }
}
