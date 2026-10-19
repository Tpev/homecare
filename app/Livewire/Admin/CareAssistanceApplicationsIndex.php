<?php

namespace App\Livewire\Admin;

use App\Models\CareAssistanceApplication;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class CareAssistanceApplicationsIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $status = 'all';

    public string $search = '';

    public function mount(): void
    {
        $this->authorizeAdministrator();

        if (! in_array($this->status, ['all', ...array_keys(CareAssistanceApplication::STATUS_LABELS)], true)) {
            $this->status = 'all';
        }
    }

    public function updatedStatus(): void
    {
        $this->authorizeAdministrator();
        $this->validate(['status' => ['required', Rule::in(['all', ...array_keys(CareAssistanceApplication::STATUS_LABELS)])]]);
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->authorizeAdministrator();
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->authorizeAdministrator();
        $this->reset('status', 'search');
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorizeAdministrator();

        $query = CareAssistanceApplication::query()->with('reviewer:id,name');
        $search = trim(mb_substr($this->search, 0, 255));

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('city', 'like', '%'.$search.'%')
                    ->orWhere('zip', 'like', '%'.$search.'%');

                if (ctype_digit($search)) {
                    $query->orWhere('id', $search);
                }
            });
        }

        if (array_key_exists($this->status, CareAssistanceApplication::STATUS_LABELS)) {
            $query->where('status', $this->status);
        }

        $counts = CareAssistanceApplication::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('livewire.admin.care-assistance-applications-index', [
            'applications' => $query
                ->orderByRaw("CASE status WHEN 'new' THEN 0 WHEN 'under_review' THEN 1 WHEN 'needs_information' THEN 2 ELSE 3 END")
                ->oldest('created_at')->orderBy('id')->paginate(20),
            'statusLabels' => CareAssistanceApplication::STATUS_LABELS,
            'counts' => $counts,
            'total' => $counts->sum(),
        ])->title('Care assistance applications | LoLo Care');
    }

    private function authorizeAdministrator(): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
    }
}
