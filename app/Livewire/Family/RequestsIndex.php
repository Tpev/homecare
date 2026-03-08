<?php

namespace App\Livewire\Family;

use App\Models\CareRequest;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class RequestsIndex extends Component
{
    use WithPagination;

    public string $status = 'all';
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

    public function render()
    {
        $requests = CareRequest::query()
            ->with(['recipient'])
            ->withCount(['applications'])
            ->where('family_user_id', auth()->id())
            ->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status));

        match ($this->sort) {
            'oldest' => $requests->orderBy('created_at'),
            'start_soon' => $requests->orderBy('requested_start_at'),
            default => $requests->orderByDesc('created_at'),
        };

        return view('livewire.family.requests-index', [
            'requests' => $requests->paginate(10),
        ]);
    }
}
