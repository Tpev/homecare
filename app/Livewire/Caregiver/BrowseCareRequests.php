<?php

namespace App\Livewire\Caregiver;

use App\Models\CareRequest;
use App\Models\CareTask;
use Carbon\Carbon;
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

    public array $taskOptions = [];

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

    public function render()
    {
        $query = CareRequest::query()
            ->with([
                'recipient',
                'tasks',
                'applications' => fn ($q) => $q
                    ->where('caregiver_user_id', auth()->id())
                    ->with(['conversation:id,care_request_application_id,care_request_id,caregiver_user_id']),
                'invitations' => fn ($q) => $q
                    ->where('caregiver_user_id', auth()->id())
                    ->latest('created_at'),
            ])
            ->where('status', CareRequest::STATUS_OPEN);

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

        match ($this->sort) {
            'start_soon' => $query->orderBy('requested_start_at'),
            'budget_high' => $query->orderByDesc('budget_max'),
            default => $query->orderByDesc('created_at'),
        };

        return view('livewire.caregiver.browse-care-requests', [
            'requests' => $query->paginate(10),
        ]);
    }
}
