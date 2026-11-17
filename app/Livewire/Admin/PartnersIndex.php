<?php

namespace App\Livewire\Admin;

use App\Models\PartnerProfile;
use App\Support\PartnerOptions;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class PartnersIndex extends Component
{
    use WithPagination;

    public string $q = '';

    public string $category = 'all';

    public string $state = 'all';

    public string $status = 'all';

    #[Locked]
    public ?int $selectedId = null;

    public string $partnerStatus = 'new';

    public string $internalNotes = '';

    protected $queryString = [
        'q' => ['except' => ''],
        'category' => ['except' => 'all'],
        'state' => ['except' => 'all'],
        'status' => ['except' => 'all'],
    ];

    public function boot(): void
    {
        $this->authorizeAdmin();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['q', 'category', 'state', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->authorizeAdmin();
        $this->reset('q', 'category', 'state', 'status');
        $this->resetPage();
    }

    public function selectPartner(int $id): void
    {
        $this->authorizeAdmin();
        $partner = PartnerProfile::query()->findOrFail($id);
        $this->selectedId = $partner->id;
        $this->partnerStatus = $partner->status;
        $this->internalNotes = $partner->internal_notes ?? '';
        $this->resetValidation();
        session()->forget('partnerSaved');
        $this->dispatch('partner-selected');
    }

    public function closePartner(): void
    {
        $this->authorizeAdmin();
        $this->selectedId = null;
        $this->resetValidation();
    }

    public function savePartner(): void
    {
        $this->authorizeAdmin();
        abort_unless($this->selectedId !== null, 404);
        $validated = $this->validate([
            'partnerStatus' => ['required', Rule::in(array_keys(PartnerOptions::statuses()))],
            'internalNotes' => ['nullable', 'string', 'max:5000'],
        ]);

        $partner = PartnerProfile::query()->findOrFail($this->selectedId);
        $partner->status = $validated['partnerStatus'];
        $partner->internal_notes = filled($validated['internalNotes']) ? trim($validated['internalNotes']) : null;
        $partner->save();

        session()->flash('partnerSaved', 'Partner status and notes saved.');
    }

    public function render(): View
    {
        $this->authorizeAdmin();

        $query = PartnerProfile::query();
        $search = mb_substr(trim($this->q), 0, 200);
        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                foreach (['business_name', 'contact_name', 'contact_email', 'contact_phone', 'coverage_areas'] as $column) {
                    $query->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }
        if ($this->category !== 'all') {
            $query->whereJsonContains('categories', $this->category);
        }
        if ($this->state !== 'all') {
            $query->whereJsonContains('coverage_states', $this->state);
        }
        if ($this->status !== 'all') {
            $query->where('status', $this->status);
        }

        $counts = PartnerProfile::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('livewire.admin.partners-index', [
            'partners' => $query->orderByDesc('submitted_at')->orderByDesc('id')->paginate(12),
            'selectedPartner' => $this->selectedId ? PartnerProfile::query()->find($this->selectedId) : null,
            'counts' => $counts,
            'totalPartners' => $counts->sum(),
            'categoryOptions' => PartnerOptions::categories(),
            'stateOptions' => PartnerOptions::states(),
            'statusOptions' => PartnerOptions::statuses(),
        ]);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
    }
}
