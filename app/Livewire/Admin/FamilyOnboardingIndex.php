<?php

namespace App\Livewire\Admin;

use App\Models\FamilyOnboarding;
use App\Models\FamilyOnboardingDelivery;
use App\Models\FamilyWelcomeVisit;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class FamilyOnboardingIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $filter = 'all';

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
        $query = FamilyOnboarding::query()->with(['initiatedBy', 'welcomeVisit', 'deliveries']);
        if ($this->filter === 'visits') {
            $query->whereHas('welcomeVisit', fn ($q) => $q->whereIn('status', ['requested', 'contacted']));
        } elseif ($this->filter === 'email') {
            $query->whereHas('deliveries', fn ($q) => $q->whereIn('status', ['failed', 'blocked', 'unconfirmed']));
        } elseif (in_array($this->filter, ['in_progress', 'completed', 'exempted'], true)) {
            $query->where('status', $this->filter);
        }

        return view('livewire.admin.family-onboarding-index', [
            'onboardings' => $query->latest('id')->paginate(25),
            'total' => FamilyOnboarding::query()->count(),
            'completed' => FamilyOnboarding::query()->where('status', 'completed')->count(),
            'pendingVisits' => FamilyWelcomeVisit::query()->whereIn('status', ['requested', 'contacted'])->count(),
            'emailIssues' => FamilyOnboardingDelivery::query()->whereIn('status', ['failed', 'blocked', 'unconfirmed'])->count(),
            'steps' => FamilyOnboarding::query()->where('status', 'in_progress')->selectRaw('current_step, count(*) as total')->groupBy('current_step')->pluck('total', 'current_step'),
        ]);
    }
}
