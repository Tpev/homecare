<?php

namespace App\Livewire\Admin;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class FamilyLeadsIndex extends Component
{
    use WithPagination;

    public string $q = '';

    public string $status = 'active';

    public string $source = 'all';

    public string $assigned = 'all';

    public ?int $selectedLeadId = null;

    public bool $showCreateForm = false;

    public string $selectedStatus = '';

    public string $selectedPriority = '';

    public string $selectedAssignee = '';

    public string $selectedFollowUpAt = '';

    public string $note = '';

    /** @var array<string, mixed> */
    public array $leadForm = [];

    protected $queryString = [
        'q' => ['except' => ''],
        'status' => ['except' => 'active'],
        'source' => ['except' => 'all'],
        'assigned' => ['except' => 'all'],
    ];

    public function mount(): void
    {
        $this->resetLeadForm();
    }

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSource(): void
    {
        $this->resetPage();
    }

    public function updatedAssigned(): void
    {
        $this->resetPage();
    }

    public function openLead(int $leadId): void
    {
        $lead = Lead::query()->where('lead_type', Lead::TYPE_FAMILY)->findOrFail($leadId);
        $this->selectedLeadId = $lead->id;
        $this->loadSelectedFields($lead);
    }

    public function closeLead(): void
    {
        $this->selectedLeadId = null;
        $this->note = '';
    }

    public function toggleCreateForm(): void
    {
        $this->showCreateForm = ! $this->showCreateForm;
        $this->resetLeadForm();
    }

    public function createLead(): void
    {
        $validated = $this->validate([
            'leadForm.name' => ['required', 'string', 'max:160'],
            'leadForm.phone' => ['required', 'string', 'max:40'],
            'leadForm.email' => ['nullable', 'email', 'max:160'],
            'leadForm.location' => ['nullable', 'string', 'max:160'],
            'leadForm.relationship' => ['nullable', 'string', 'max:100'],
            'leadForm.care_for' => ['nullable', 'string', 'max:160'],
            'leadForm.care_needs' => ['nullable', 'string', 'max:500'],
            'leadForm.urgency' => ['nullable', 'string', 'max:100'],
            'leadForm.schedule' => ['nullable', 'string', 'max:200'],
            'leadForm.details' => ['nullable', 'string', 'max:2000'],
            'leadForm.priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
        ])['leadForm'];

        $needs = collect(preg_split('/[,;\n]+/', (string) ($validated['care_needs'] ?? '')))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();

        $lead = Lead::query()->create([
            'lead_type' => Lead::TYPE_FAMILY,
            'name' => trim($validated['name']),
            'phone' => trim($validated['phone']),
            'email' => filled($validated['email'] ?? null) ? trim($validated['email']) : null,
            'location' => filled($validated['location'] ?? null) ? trim($validated['location']) : null,
            'contact_role' => filled($validated['relationship'] ?? null) ? trim($validated['relationship']) : null,
            'status' => 'new',
            'priority' => $validated['priority'],
            'source' => 'manual_crm',
            'source_detail' => 'Manual family lead',
            'submitted_at' => now(),
            'data' => [
                'source' => 'manual_crm',
                'form_answers' => [
                    'relationship' => $validated['relationship'] ?? null,
                    'care_for' => $validated['care_for'] ?? null,
                    'care_needs' => $needs,
                    'urgency' => $validated['urgency'] ?? null,
                    'schedule' => $validated['schedule'] ?? null,
                    'additional_details' => $validated['details'] ?? null,
                ],
                'original_submission' => [
                    'entered_by' => auth()->id(),
                    'entered_at' => now()->toISOString(),
                    'answers' => $validated,
                ],
            ],
        ]);

        $lead->activities()->create([
            'actor_user_id' => auth()->id(),
            'type' => LeadActivity::TYPE_NOTE,
            'summary' => 'Lead entered manually',
            'body' => filled($validated['details'] ?? null) ? trim($validated['details']) : null,
            'occurred_at' => now(),
            'metadata' => ['source' => 'admin_family_crm'],
        ]);

        $this->showCreateForm = false;
        $this->selectedLeadId = $lead->id;
        $this->loadSelectedFields($lead);
        $this->resetLeadForm();

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Family lead created',
            'message' => 'The lead is ready for assignment and calling.',
        ]);
    }

    public function saveLead(): void
    {
        $lead = $this->selectedLead;
        if (! $lead) {
            return;
        }

        $adminIds = array_keys($this->assigneeOptions());
        $validated = $this->validate([
            'selectedStatus' => ['required', Rule::in(array_keys(Lead::FAMILY_STAGES))],
            'selectedPriority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'selectedAssignee' => ['nullable', Rule::in($adminIds)],
            'selectedFollowUpAt' => ['nullable', 'date'],
        ]);

        $oldStatus = $lead->status;
        $oldAssignee = $lead->assigned_admin_id;
        $newAssignee = filled($validated['selectedAssignee']) ? (int) $validated['selectedAssignee'] : null;

        $lead->forceFill([
            'status' => $validated['selectedStatus'],
            'priority' => $validated['selectedPriority'],
            'assigned_admin_id' => $newAssignee,
            'next_follow_up_at' => filled($validated['selectedFollowUpAt']) ? Carbon::parse($validated['selectedFollowUpAt']) : null,
            'converted_at' => $validated['selectedStatus'] === 'converted' ? ($lead->converted_at ?: now()) : $lead->converted_at,
        ])->save();

        if ($oldStatus !== $lead->status) {
            $lead->activities()->create([
                'actor_user_id' => auth()->id(),
                'type' => LeadActivity::TYPE_STAGE_CHANGE,
                'summary' => 'Family stage changed',
                'body' => 'Moved from '.(Lead::FAMILY_STAGES[$oldStatus] ?? $oldStatus).' to '.(Lead::FAMILY_STAGES[$lead->status] ?? $lead->status).'.',
                'occurred_at' => now(),
                'metadata' => ['from' => $oldStatus, 'to' => $lead->status, 'source' => 'admin_family_crm'],
            ]);
        }

        if ((int) $oldAssignee !== (int) $newAssignee) {
            $lead->activities()->create([
                'actor_user_id' => auth()->id(),
                'type' => LeadActivity::TYPE_ASSIGNMENT,
                'summary' => 'Owner updated',
                'body' => $lead->assignedAdmin ? 'Assigned to '.$lead->assignedAdmin->name.'.' : 'Lead is now unassigned.',
                'occurred_at' => now(),
                'metadata' => ['source' => 'admin_family_crm'],
            ]);
        }

        $this->loadSelectedFields($lead->fresh());
        $this->dispatch('toast', ['type' => 'success', 'title' => 'Lead saved', 'message' => 'Ownership, timing, and stage are up to date.']);
    }

    public function addNote(): void
    {
        $lead = $this->selectedLead;
        if (! $lead) {
            return;
        }

        $validated = $this->validate(['note' => ['required', 'string', 'max:3000']]);
        $lead->activities()->create([
            'actor_user_id' => auth()->id(),
            'type' => LeadActivity::TYPE_NOTE,
            'summary' => 'Management note',
            'body' => trim($validated['note']),
            'occurred_at' => now(),
            'metadata' => ['source' => 'admin_family_crm'],
        ]);
        $this->note = '';
    }

    public function getSelectedLeadProperty(): ?Lead
    {
        return $this->selectedLeadId
            ? Lead::query()
                ->where('lead_type', Lead::TYPE_FAMILY)
                ->with([
                    'assignedAdmin:id,name,email',
                    'activities' => fn ($query) => $query->with('actor:id,name,email')->latest('occurred_at')->limit(40),
                ])
                ->find($this->selectedLeadId)
            : null;
    }

    public function render(): View
    {
        return view('livewire.admin.family-leads-index', [
            'assigneeOptions' => $this->assigneeOptions(),
            'leads' => $this->baseQuery()
                ->with('assignedAdmin:id,name,email')
                ->orderByRaw("case priority when 'urgent' then 1 when 'high' then 2 when 'normal' then 3 else 4 end")
                ->orderByRaw('next_follow_up_at is null')
                ->orderBy('next_follow_up_at')
                ->latest('submitted_at')
                ->paginate(18),
            'selectedLead' => $this->selectedLead,
            'stageOptions' => Lead::FAMILY_STAGES,
            'stats' => $this->stats(),
        ]);
    }

    private function baseQuery(bool $filters = true): Builder
    {
        $query = Lead::query()->where('lead_type', Lead::TYPE_FAMILY);

        if (! $filters) {
            return $query;
        }

        if ($this->status === 'active') {
            $query->whereNotIn('status', ['converted', 'unreachable', 'not_fit', 'lost', 'closed']);
        } elseif ($this->status !== 'all') {
            $query->where('status', $this->status);
        }

        if ($this->source !== 'all') {
            $query->where('source', $this->source);
        }

        if ($this->assigned === 'unassigned') {
            $query->whereNull('assigned_admin_id');
        } elseif ($this->assigned !== 'all') {
            $query->where('assigned_admin_id', (int) $this->assigned);
        }

        $search = trim($this->q);
        if ($search !== '') {
            $query->where(function (Builder $sub) use ($search): void {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('source_detail', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /** @return array<string, int> */
    private function stats(): array
    {
        $query = $this->baseQuery(filters: false);

        return [
            'new' => (clone $query)->where('status', 'new')->count(),
            'due' => (clone $query)
                ->whereNotIn('status', ['converted', 'unreachable', 'not_fit', 'lost', 'closed'])
                ->where(fn (Builder $q) => $q->whereNull('next_follow_up_at')->orWhere('next_follow_up_at', '<=', now()))
                ->count(),
            'callbacks' => (clone $query)->where('status', 'callback_scheduled')->count(),
            'qualified' => (clone $query)->whereIn('status', ['qualified', 'assessment_scheduled'])->count(),
            'converted' => (clone $query)->whereNotNull('converted_at')->count(),
        ];
    }

    /** @return array<int, string> */
    private function assigneeOptions(): array
    {
        return User::query()
            ->whereIn('role', ['admin', 'sales', 'sdr'])
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private function loadSelectedFields(Lead $lead): void
    {
        $this->selectedStatus = $lead->status;
        $this->selectedPriority = $lead->priority ?: 'normal';
        $this->selectedAssignee = $lead->assigned_admin_id ? (string) $lead->assigned_admin_id : '';
        $this->selectedFollowUpAt = $lead->next_follow_up_at?->format('Y-m-d\TH:i') ?? '';
        $this->note = '';
    }

    private function resetLeadForm(): void
    {
        $this->leadForm = [
            'name' => '',
            'phone' => '',
            'email' => '',
            'location' => '',
            'relationship' => '',
            'care_for' => '',
            'care_needs' => '',
            'urgency' => '',
            'schedule' => '',
            'details' => '',
            'priority' => 'normal',
        ];
    }
}
