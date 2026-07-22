<?php

namespace App\Livewire\Admin;

use App\Models\CarePlan;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class CarePlansIndex extends Component
{
    use WithPagination;

    public string $status = 'live';

    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $plans = CarePlan::query()
            ->with(['family:id,name,email', 'caregiver:id,name,email', 'nextBooking.payment'])
            ->when($this->status === 'live', fn ($query) => $query->whereIn('status', [CarePlan::STATUS_ACTIVE, CarePlan::STATUS_PAYMENT_ATTENTION, CarePlan::STATUS_PAUSED]))
            ->when($this->status !== 'all' && $this->status !== 'live', fn ($query) => $query->where('status', $this->status))
            ->when(trim($this->search) !== '', function ($query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(function ($nested) use ($term): void {
                    $nested->where('title', 'like', $term)
                        ->orWhereHas('family', fn ($user) => $user->where('name', 'like', $term)->orWhere('email', 'like', $term))
                        ->orWhereHas('caregiver', fn ($user) => $user->where('name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->latest()
            ->paginate(20);

        return view('livewire.admin.care-plans-index', ['plans' => $plans]);
    }
}
