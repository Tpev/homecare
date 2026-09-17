<?php

namespace App\Livewire\Admin;

use App\Models\CareRequest;
use App\Models\FamilyAccount;
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

    #[Url]
    public string $search = '';

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
        // Start from accounts so a family who registered while enrollment was off
        // is visible as not enrolled, without inventing a completion or enrolling them.
        $query = FamilyAccount::query()
            ->with(['owner', 'onboarding.initiatedBy', 'onboarding.welcomeVisit', 'onboarding.deliveries'])
            ->where(fn ($q) => $q->where('status', FamilyAccount::STATUS_ACTIVE)->orWhereHas('onboarding'))
            ->addSelect(['latest_request_id' => CareRequest::query()->select('id')
                ->whereColumn('family_account_id', 'family_accounts.id')->where('is_system_generated', false)
                ->latest('id')->limit(1)]);
        $search = trim(mb_substr($this->search, 0, 255));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $matchUser = fn ($user) => $user->where(fn ($fields) => $fields
                    ->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
                $q->whereHas('owner', $matchUser)
                    ->orWhereHas('onboarding.initiatedBy', $matchUser)
                    ->orWhereHas('activeMemberships.user', $matchUser);
                if (ctype_digit($search)) {
                    $q->orWhere('family_accounts.id', $search);
                }
            });
        }
        if ($this->filter === 'visits') {
            $query->whereHas('onboarding.welcomeVisit', fn ($q) => $q->whereIn('status', ['requested', 'contacted']));
        } elseif ($this->filter === 'email') {
            $query->whereHas('onboarding.deliveries', fn ($q) => $q->whereIn('status', ['failed', 'blocked', 'unconfirmed']));
        } elseif ($this->filter === 'not_enrolled') {
            $query->whereDoesntHave('onboarding');
        } elseif (in_array($this->filter, ['in_progress', 'completed', 'exempted'], true)) {
            $query->whereHas('onboarding', fn ($q) => $q->where('status', $this->filter));
        }

        return view('livewire.admin.family-onboarding-index', [
            'families' => $query->latest('family_accounts.id')->paginate(25),
            'total' => FamilyOnboarding::query()->count(),
            'completed' => FamilyOnboarding::query()->where('status', 'completed')->count(),
            'pendingVisits' => FamilyWelcomeVisit::query()->whereIn('status', ['requested', 'contacted'])->count(),
            'emailIssues' => FamilyOnboardingDelivery::query()->whereIn('status', ['failed', 'blocked', 'unconfirmed'])->count(),
            'steps' => FamilyOnboarding::query()->where('status', 'in_progress')->selectRaw('current_step, count(*) as total')->groupBy('current_step')->pluck('total', 'current_step'),
        ]);
    }
}
