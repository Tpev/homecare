<?php

namespace App\Livewire\Admin;

use App\Models\Lead;
use App\Models\PartnerLeadCredit;
use App\Models\PartnerProfile;
use App\Services\Partners\PartnerLeadInventory;
use App\Services\Partners\PartnerLeadService;
use App\Support\PartnerOptions;
use App\Support\StaffAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class PartnerLeadsIndex extends Component
{
    use WithPagination;

    public string $q = '';

    public string $category = 'all';

    public string $status = 'all';

    #[Locked]
    public ?int $selectedId = null;

    public string $assignmentCategory = '';

    public string $assignmentPartnerId = '';

    public string $partnerSearch = '';

    public bool $sharingConfirmed = false;

    public bool $geographyConfirmed = false;

    protected $queryString = [
        'q' => ['except' => ''],
        'category' => ['except' => 'all'],
        'status' => ['except' => 'all'],
    ];

    public function boot(): void
    {
        $this->authorizeAdmin();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['q', 'category', 'status'], true)) {
            $this->resetPage();
        }
        if ($property === 'assignmentCategory') {
            $this->reset('assignmentPartnerId', 'partnerSearch');
        }
        if (in_array($property, ['assignmentCategory', 'assignmentPartnerId'], true)) {
            $this->reset('sharingConfirmed', 'geographyConfirmed');
            $this->resetValidation();
        }
    }

    public function clearFilters(): void
    {
        $this->authorizeAdmin();
        $this->reset('q', 'category', 'status');
        $this->resetPage();
    }

    public function selectLead(int $id, ?string $category = null): void
    {
        $this->authorizeAdmin();
        $lead = Lead::query()->where('lead_type', Lead::TYPE_FAMILY)->findOrFail($id);
        $service = app(PartnerLeadService::class);
        $requested = $service->categoriesFor($lead);
        $sent = $service->sentCategoriesFor($lead);
        $known = array_values(array_unique([...$requested, ...array_keys($sent)]));
        abort_if($category !== null && ! in_array($category, $known, true), 404);

        $this->resetSelection();
        $this->selectedId = $lead->id;
        $available = array_values(array_diff($requested, array_keys($sent)));
        $this->assignmentCategory = $category ?? $available[0] ?? $known[0] ?? '';
        $this->dispatch('partner-lead-selected');
    }

    public function closeLead(): void
    {
        $this->authorizeAdmin();
        $this->resetSelection();
    }

    public function assignLead(): void
    {
        $this->authorizeAdmin();
        abort_unless($this->selectedId !== null, 404);
        $this->validate([
            'assignmentCategory' => ['required', Rule::in(array_keys(PartnerOptions::categories()))],
            'assignmentPartnerId' => ['required', 'integer', 'min:1'],
        ], [
            'assignmentCategory.required' => 'Choose a requested service.',
            'assignmentPartnerId.required' => 'Choose a partner to receive this lead.',
        ]);
        $lead = Lead::query()->where('lead_type', Lead::TYPE_FAMILY)->findOrFail($this->selectedId);
        $partner = PartnerProfile::query()->findOrFail((int) $this->assignmentPartnerId);
        app(PartnerLeadService::class)->assign(
            $partner, $lead, $this->assignmentCategory, auth()->user(),
            $this->sharingConfirmed, $this->geographyConfirmed,
        );

        session()->flash('leadDelivered', $lead->name.' sent to '.$partner->business_name.' for '.
            PartnerOptions::categories()[$this->assignmentCategory].'. One credit used.');
        $this->reset('assignmentPartnerId', 'sharingConfirmed', 'geographyConfirmed');
        $this->resetValidation();
    }

    public function render()
    {
        $this->authorizeAdmin();
        $inventory = app(PartnerLeadInventory::class)->page($this->q, $this->category, $this->status, $this->getPage());
        $service = app(PartnerLeadService::class);
        $selectedLead = $this->selectedId
            ? Lead::query()->where('lead_type', Lead::TYPE_FAMILY)->find($this->selectedId) : null;
        $selectedCategories = $selectedLead ? $service->categoriesFor($selectedLead) : [];
        $selectedSent = $selectedLead ? $service->sentCategoriesFor($selectedLead) : [];
        $selectedBlockedReason = $selectedLead ? $service->unavailabilityReason($selectedLead) : null;
        $eligiblePartners = collect();
        $selectedPartner = null;
        $partnerChoicesTruncated = false;

        if ($selectedLead && ! $selectedBlockedReason && ! isset($selectedSent[$this->assignmentCategory])
            && in_array($this->assignmentCategory, $selectedCategories, true)) {
            $eligibleQuery = $this->eligiblePartnersQuery();
            $search = mb_substr(trim($this->partnerSearch), 0, 150);
            if ($search !== '') {
                $eligibleQuery->where(function (Builder $query) use ($search): void {
                    foreach (['business_name', 'contact_name', 'coverage_areas'] as $column) {
                        $query->orWhere($column, 'like', '%'.$search.'%');
                    }
                });
            }
            $eligiblePartners = $eligibleQuery->orderBy('business_name')->orderBy('id')->limit(31)->get();
            $partnerChoicesTruncated = $eligiblePartners->count() > 30;
            $eligiblePartners = $eligiblePartners->take(30);
            if ($this->assignmentPartnerId !== '') {
                $selectedPartner = $this->eligiblePartnersQuery()->find((int) $this->assignmentPartnerId);
            }
        }

        return view('livewire.admin.partner-leads-index', $inventory + [
            'categoryOptions' => PartnerOptions::categories(),
            'selectedLead' => $selectedLead,
            'selectedCategories' => $selectedCategories,
            'selectedSent' => $selectedSent,
            'selectedBlockedReason' => $selectedBlockedReason,
            'eligiblePartners' => $eligiblePartners,
            'selectedPartner' => $selectedPartner,
            'selectedPartnerCredits' => (int) ($selectedPartner?->available_credits ?? 0),
            'partnerChoicesTruncated' => $partnerChoicesTruncated,
        ]);
    }

    private function eligiblePartnersQuery(): Builder
    {
        return PartnerProfile::query()->where('status', PartnerProfile::STATUS_APPROVED)
            ->whereJsonContains('categories', $this->assignmentCategory)
            ->whereHas('user', fn (Builder $query) => StaffAccess::allowed($query)->where('role', 'partner'))
            ->whereHas('leadCredits', fn (Builder $query) => $query->available()->whereNotNull('paid_at'))
            ->addSelect(['available_credits' => PartnerLeadCredit::query()
                ->selectRaw('COALESCE(SUM(quantity - used_quantity), 0)')
                ->whereColumn('partner_profile_id', 'partner_profiles.id')
                ->available()->whereNotNull('paid_at')]);
    }

    private function resetSelection(): void
    {
        $this->reset('selectedId', 'assignmentCategory', 'assignmentPartnerId', 'partnerSearch', 'sharingConfirmed', 'geographyConfirmed');
        $this->resetValidation();
    }

    private function authorizeAdmin(): void
    {
        $user = auth()->user()?->fresh();
        abort_unless($user?->isAdministrator() && StaffAccess::allowsUser($user), 403);
    }
}
