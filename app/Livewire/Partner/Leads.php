<?php

namespace App\Livewire\Partner;

use App\Models\PartnerProfile;
use App\Support\PartnerOptions;
use App\Support\StaffAccess;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Leads extends Component
{
    use WithPagination;

    public string $search = '';

    public string $categoryFilter = '';

    public function boot(): void
    {
        $user = auth()->user()?->fresh();
        abort_unless($user?->role === 'partner' && StaffAccess::allowsUser($user), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'categoryFilter');
        $this->resetPage();
    }

    public function render()
    {
        $profile = PartnerProfile::query()->where('user_id', auth()->id())->firstOrFail();
        $query = $profile->leads();
        $search = mb_substr(trim($this->search), 0, 200);

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                foreach (['contact_name', 'contact_email', 'contact_phone', 'location', 'zip'] as $column) {
                    $query->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }
        if ($this->categoryFilter !== '') {
            $query->where('category', $this->categoryFilter);
        }

        return view('livewire.partner.leads', [
            'profile' => $profile,
            'leads' => $query->latest('assigned_at')->latest('id')->paginate(12),
            'totalCount' => $profile->leads()->count(),
            'remainingCredits' => $profile->remainingLeadCredits(),
            'categories' => PartnerOptions::categories(),
        ]);
    }
}
