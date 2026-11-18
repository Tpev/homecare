<?php

namespace App\Livewire\Admin;

use App\Models\Lead;
use App\Models\PartnerLeadCredit;
use App\Models\PartnerProfile;
use App\Services\Partners\PartnerLeadService;
use App\Support\PartnerOptions;
use App\Support\StaffAccess;
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

    public string $leadSearch = '';

    #[Locked]
    public ?int $assignmentLeadId = null;

    public string $assignmentCategory = '';

    public bool $sharingConfirmed = false;

    public bool $geographyConfirmed = false;

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
        if ($property === 'assignmentCategory') {
            $this->reset('sharingConfirmed', 'geographyConfirmed');
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
        $this->resetAssignment();
        $this->resetValidation();
        session()->forget('partnerSaved');
        $this->dispatch('partner-selected');
    }

    public function closePartner(): void
    {
        $this->authorizeAdmin();
        $this->selectedId = null;
        $this->resetAssignment();
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

    public function selectAssignmentLead(int $id): void
    {
        $this->authorizeAdmin();
        abort_unless($this->selectedId !== null, 404);
        $partner = PartnerProfile::query()->findOrFail($this->selectedId);
        $lead = Lead::query()->where('lead_type', Lead::TYPE_FAMILY)->findOrFail($id);
        $this->resetAssignment();
        $this->assignmentLeadId = $lead->id;
        $categories = array_values(array_intersect(
            app(PartnerLeadService::class)->categoriesFor($lead), $partner->categories ?? [],
        ));
        $this->assignmentCategory = $categories[0] ?? '';
    }

    public function clearAssignmentLead(): void
    {
        $this->authorizeAdmin();
        $this->resetAssignment();
    }

    public function assignLead(): void
    {
        $this->authorizeAdmin();
        abort_unless($this->selectedId !== null, 404);
        $this->validate([
            'assignmentLeadId' => ['required', 'integer'],
            'assignmentCategory' => ['required', Rule::in(array_keys(PartnerOptions::categories()))],
        ]);
        app(PartnerLeadService::class)->assign(
            PartnerProfile::query()->findOrFail($this->selectedId),
            Lead::query()->findOrFail($this->assignmentLeadId),
            $this->assignmentCategory,
            auth()->user(),
            $this->sharingConfirmed,
            $this->geographyConfirmed,
        );
        $this->resetAssignment();
        session()->flash('partnerLeadSent', 'Lead sent to the partner dashboard. One credit used.');
    }

    private function resetAssignment(): void
    {
        $this->reset('leadSearch', 'assignmentLeadId', 'assignmentCategory', 'sharingConfirmed', 'geographyConfirmed');
        $this->resetValidation();
        session()->forget('partnerLeadSent');
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
        $selectedPartner = $this->selectedId ? PartnerProfile::query()->find($this->selectedId) : null;
        $selectedLead = $selectedPartner && $this->assignmentLeadId ? Lead::query()->find($this->assignmentLeadId) : null;
        $leadResults = collect();
        $leadSearch = mb_substr(trim($this->leadSearch), 0, 150);
        if ($selectedPartner && ! $selectedLead && (mb_strlen($leadSearch) >= 2 || ctype_digit($leadSearch))) {
            $leadResults = Lead::query()->where('lead_type', Lead::TYPE_FAMILY)
                ->where(function ($query) use ($leadSearch) {
                    if (ctype_digit($leadSearch)) {
                        $query->orWhere('id', $leadSearch);
                    }
                    foreach (['name', 'email', 'phone', 'location', 'zip'] as $column) {
                        $query->orWhere($column, 'like', '%'.$leadSearch.'%');
                    }
                })->latest('id')->limit(8)->get(['id', 'name', 'email', 'phone', 'location', 'zip']);
        }
        $assignmentCategories = $selectedLead ? array_intersect_key(PartnerOptions::categories(), array_flip(array_intersect(
            app(PartnerLeadService::class)->categoriesFor($selectedLead), $selectedPartner->categories ?? [],
        ))) : [];
        $availableLeadCredits = $selectedPartner ? PartnerLeadCredit::query()->available()
            ->where('partner_profile_id', $selectedPartner->id)->whereNotNull('paid_at')
            ->selectRaw('COALESCE(SUM(quantity - used_quantity), 0) as remaining')->value('remaining') : 0;

        return view('livewire.admin.partners-index', [
            'partners' => $query->orderByDesc('submitted_at')->orderByDesc('id')->paginate(12),
            'selectedPartner' => $selectedPartner,
            'selectedAssignmentLead' => $selectedLead,
            'leadResults' => $leadResults,
            'assignmentCategories' => $assignmentCategories,
            'availableLeadCredits' => (int) $availableLeadCredits,
            'counts' => $counts,
            'totalPartners' => $counts->sum(),
            'categoryOptions' => PartnerOptions::categories(),
            'stateOptions' => PartnerOptions::states(),
            'statusOptions' => PartnerOptions::statuses(),
        ]);
    }

    private function authorizeAdmin(): void
    {
        $user = auth()->user()?->fresh();
        abort_unless($user?->isAdministrator() && StaffAccess::allowsUser($user), 403);
    }
}
