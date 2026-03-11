<?php

namespace App\Livewire\Family;

use App\Models\CareRequest;
use App\Support\CareRequestProgress;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class RequestsIndex extends Component
{
    use WithPagination;

    public string $status = 'all';
    public string $requestType = 'all';
    public string $sort = 'latest';

    public array $statusOptions = [
        ['label' => 'All statuses', 'value' => 'all'],
        ['label' => 'Open', 'value' => CareRequest::STATUS_OPEN],
        ['label' => 'Filled', 'value' => CareRequest::STATUS_FILLED],
        ['label' => 'Draft', 'value' => CareRequest::STATUS_DRAFT],
        ['label' => 'Cancelled', 'value' => CareRequest::STATUS_CANCELLED],
        ['label' => 'Expired', 'value' => CareRequest::STATUS_EXPIRED],
    ];

    public array $sortOptions = [
        ['label' => 'Latest first', 'value' => 'latest'],
        ['label' => 'Oldest first', 'value' => 'oldest'],
        ['label' => 'Start time (soonest)', 'value' => 'start_soon'],
    ];

    public array $requestTypeOptions = [
        ['label' => 'All types', 'value' => 'all'],
        ['label' => 'One-time', 'value' => CareRequest::TYPE_ONE_TIME],
        ['label' => 'Recurring', 'value' => CareRequest::TYPE_RECURRING],
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'family', 403);
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function updatingRequestType(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $requests = CareRequest::query()
            ->with(['recipient', 'booking'])
            ->withCount(['applications'])
            ->where('family_user_id', auth()->id())
            ->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))
            ->when($this->requestType !== 'all', fn ($q) => $q->where('request_type', $this->requestType));

        match ($this->sort) {
            'oldest' => $requests->orderBy('created_at'),
            'start_soon' => $requests->orderBy('requested_start_at'),
            default => $requests->orderByDesc('created_at'),
        };

        $paginated = $requests->paginate(10);

        $avgFirstResponseMinutes = CareRequest::query()
            ->where('family_user_id', auth()->id())
            ->whereNotNull('first_applicant_at')
            ->get(['created_at', 'first_applicant_at'])
            ->avg(fn (CareRequest $request) => (int) $request->created_at->diffInMinutes($request->first_applicant_at));

        return view('livewire.family.requests-index', [
            'requests' => $paginated,
            'avgFirstResponseLabel' => CareRequestProgress::minutesLabel(
                $avgFirstResponseMinutes !== null ? (int) round($avgFirstResponseMinutes) : null
            ),
        ]);
    }
}
