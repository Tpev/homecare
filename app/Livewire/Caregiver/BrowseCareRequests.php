<?php

namespace App\Livewire\Caregiver;

use App\Models\CareRequest;
use App\Models\CareTask;
use App\Support\CaregiverPrelaunch;
use App\Support\MarketplacePricing;
use App\Support\WeeklySchedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class BrowseCareRequests extends Component
{
    use WithPagination;

    public string $city = '';

    public string $state = '';

    public array $taskIds = [];

    public string $requestType = 'all';

    public string $when = 'any';

    public string $sort = 'newest';

    public string $scope = 'new_to_you';

    public array $taskOptions = [];

    public array $scopeOptions = [
        ['label' => 'New to you', 'value' => 'new_to_you'],
        ['label' => 'Invited', 'value' => 'invited'],
        ['label' => 'Applied', 'value' => 'applied'],
        ['label' => 'All open', 'value' => 'all'],
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'caregiver', 403);

        $this->taskOptions = CareTask::query()->orderBy('name')->get(['id', 'name'])->toArray();
    }

    public function updatingCity(): void
    {
        $this->resetPage();
    }

    public function updatingState(): void
    {
        $this->resetPage();
    }

    public function updatingTaskIds(): void
    {
        $this->resetPage();
    }

    public function updatingWhen(): void
    {
        $this->resetPage();
    }

    public function updatingRequestType(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function updatingScope(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset([
            'city',
            'state',
            'taskIds',
            'requestType',
            'when',
            'sort',
            'scope',
        ]);

        $this->requestType = 'all';
        $this->when = 'any';
        $this->sort = 'newest';
        $this->scope = 'new_to_you';
        $this->resetPage();
    }

    public function render()
    {
        $prelaunchMode = CaregiverPrelaunch::enabled();
        $caregiverId = (int) auth()->id();

        $query = $this->filteredOpenRequestsQuery($prelaunchMode, $caregiverId);
        $scopeCounts = $this->scopeCounts($query, $caregiverId);

        $this->applyScope($query, $caregiverId);

        $query
            ->with([
                'recipient',
                'family:id,email',
                'tasks',
                'applications' => fn ($q) => $q
                    ->where('caregiver_user_id', $caregiverId)
                    ->with(['conversation:id,care_request_application_id,care_request_id,caregiver_user_id']),
                'invitations' => fn ($q) => $q
                    ->where('caregiver_user_id', $caregiverId)
                    ->latest('created_at'),
            ])
            ->withCount('applications');

        match ($this->sort) {
            'start_soon' => $query->orderByRaw('requested_start_at is null')->orderBy('requested_start_at'),
            default => $query->orderByDesc('created_at'),
        };

        return view('livewire.caregiver.browse-care-requests', [
            'prelaunchMode' => $prelaunchMode,
            'requests' => $query->paginate(10),
            'scopeCounts' => $scopeCounts,
        ]);
    }

    public function scheduleLabel(CareRequest $request): string
    {
        if ($request->request_type === CareRequest::TYPE_RECURRING) {
            $schedule = $request->recurringScheduleLabel();

            return 'Recurring'.($schedule !== '' ? ': '.$schedule : '');
        }

        if ($request->requested_start_at && $request->requested_end_at) {
            return $request->requested_start_at->format('M d, Y g:i A').' to '.$request->requested_end_at->format('g:i A');
        }

        return 'Schedule pending';
    }

    public function estimatedPayLine(CareRequest $request): ?string
    {
        $minutes = $this->estimatedMinutes($request);
        if (! $minutes || $minutes <= 0) {
            return null;
        }

        $rate = app(MarketplacePricing::class)->caregiverGrossHourlyCents() / 100;
        $hours = $minutes / 60;
        $hoursLabel = abs($hours - round($hours)) < 0.01
            ? (string) (int) round($hours)
            : number_format($hours, 1);
        $total = round($hours * $rate, 2);

        return sprintf('%sh at $%s/hr* - about $%s gross', $hoursLabel, number_format($rate, 2), number_format($total, 2));
    }

    public function postedLabel(CareRequest $request): string
    {
        return $request->created_at?->diffForHumans() ?? 'recently';
    }

    public function responseTargetLabel(CareRequest $request): string
    {
        return ($request->preferred_response_hours ?: 12).'h response target';
    }

    public function requestPreview(CareRequest $request): ?string
    {
        $preview = trim((string) ($request->scope_of_work ?: $request->additional_info ?: $request->time_expectations));

        return $preview !== '' ? Str::limit($preview, 220) : null;
    }

    private function filteredOpenRequestsQuery(bool $prelaunchMode, int $caregiverId): Builder
    {
        $query = CareRequest::query()
            ->where('status', CareRequest::STATUS_OPEN)
            ->acceptingApplications()
            ->visibleToCaregiver($caregiverId);

        if ($prelaunchMode) {
            $query->whereRaw('1 = 0');
        }

        if ($this->requestType !== 'all') {
            $query->where('request_type', $this->requestType);
        }

        if ($this->city !== '') {
            $query->where('city', 'like', '%'.$this->city.'%');
        }

        if ($this->state !== '') {
            $query->where('state', strtoupper($this->state));
        }

        if ($this->taskIds !== []) {
            $query->whereHas('tasks', fn ($q) => $q->whereIn('care_tasks.id', $this->taskIds));
        }

        if ($this->when === 'today') {
            $query->whereBetween('requested_start_at', [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()]);
        }

        if ($this->when === 'this_week') {
            $query->whereBetween('requested_start_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
        }

        return $query;
    }

    /**
     * @return array<string, int>
     */
    private function scopeCounts(Builder $query, int $caregiverId): array
    {
        return [
            'new_to_you' => (clone $query)
                ->whereDoesntHave('applications', fn ($q) => $q->where('caregiver_user_id', $caregiverId))
                ->whereDoesntHave('invitations', fn ($q) => $this->pendingInvitationFilter($q, $caregiverId))
                ->count(),
            'invited' => (clone $query)
                ->whereHas('invitations', fn ($q) => $this->pendingInvitationFilter($q, $caregiverId))
                ->count(),
            'applied' => (clone $query)
                ->whereHas('applications', fn ($q) => $q->where('caregiver_user_id', $caregiverId))
                ->count(),
            'all' => (clone $query)->count(),
        ];
    }

    private function applyScope(Builder $query, int $caregiverId): void
    {
        if ($this->scope === 'invited') {
            $query->whereHas('invitations', fn ($q) => $this->pendingInvitationFilter($q, $caregiverId));

            return;
        }

        if ($this->scope === 'applied') {
            $query->whereHas('applications', fn ($q) => $q->where('caregiver_user_id', $caregiverId));

            return;
        }

        if ($this->scope === 'new_to_you') {
            $query
                ->whereDoesntHave('applications', fn ($q) => $q->where('caregiver_user_id', $caregiverId))
                ->whereDoesntHave('invitations', fn ($q) => $this->pendingInvitationFilter($q, $caregiverId));
        }
    }

    private function pendingInvitationFilter(Builder $query, int $caregiverId): void
    {
        $query
            ->where('caregiver_user_id', $caregiverId)
            ->where('status', \App\Models\CareRequestInvitation::STATUS_PENDING)
            ->where(function ($nested) {
                $nested->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            });
    }

    private function estimatedMinutes(CareRequest $request): ?int
    {
        if ($request->request_type === CareRequest::TYPE_ONE_TIME && $request->requested_start_at && $request->requested_end_at) {
            $minutes = (int) $request->requested_start_at->diffInMinutes($request->requested_end_at, false);

            return $minutes > 0 ? $minutes : null;
        }

        if ($request->request_type === CareRequest::TYPE_RECURRING) {
            $slot = WeeklySchedule::first($request->recurringScheduleSlots());
            if (! $slot) {
                return null;
            }

            return WeeklySchedule::durationMinutes($slot);
        }

        return null;
    }

    private function timeStringToMinutes(?string $time): ?int
    {
        if (! $time) {
            return null;
        }

        try {
            $parsed = Carbon::parse($time);
        } catch (\Throwable) {
            return null;
        }

        return ((int) $parsed->format('H') * 60) + (int) $parsed->format('i');
    }
}
