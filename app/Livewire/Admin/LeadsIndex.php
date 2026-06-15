<?php

namespace App\Livewire\Admin;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class LeadsIndex extends Component
{
    use WithPagination;

    public string $q = '';
    public string $pipeline = Lead::TYPE_FAMILY;
    public string $status = 'all';
    public string $assigned = 'all';
    public string $source = 'all';
    public string $priority = 'all';
    public string $sort = 'next_follow_up_at';
    public string $dir = 'asc';
    public int $perPage = 25;

    public bool $showCreateForm = false;
    public ?int $selectedLeadId = null;

    /** @var array<string, mixed> */
    public array $leadForm = [];

    /** @var array<string, mixed> */
    public array $activityForm = [];

    protected $queryString = [
        'q' => ['except' => ''],
        'pipeline' => ['except' => Lead::TYPE_FAMILY],
        'status' => ['except' => 'all'],
        'assigned' => ['except' => 'all'],
        'source' => ['except' => 'all'],
        'priority' => ['except' => 'all'],
        'sort' => ['except' => 'next_follow_up_at'],
        'dir' => ['except' => 'asc'],
        'perPage' => ['except' => 25],
    ];

    public function mount(): void
    {
        if (! in_array($this->pipeline, [Lead::TYPE_FAMILY, Lead::TYPE_REFERRAL], true)) {
            $this->pipeline = Lead::TYPE_FAMILY;
        }

        $this->resetLeadForm();
        $this->resetActivityForm();
    }

    public function updatingQ(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }
    public function updatingAssigned(): void { $this->resetPage(); }
    public function updatingSource(): void { $this->resetPage(); }
    public function updatingPriority(): void { $this->resetPage(); }
    public function updatingPerPage(): void { $this->resetPage(); }

    public function setPipeline(string $pipeline): void
    {
        if (! in_array($pipeline, [Lead::TYPE_FAMILY, Lead::TYPE_REFERRAL], true)) {
            return;
        }

        $this->pipeline = $pipeline;
        $this->status = 'all';
        $this->selectedLeadId = null;
        $this->resetLeadForm();
        $this->resetPage();
    }

    public function setSort(string $field): void
    {
        $allowed = ['created_at', 'updated_at', 'status', 'name', 'company', 'source', 'priority', 'last_contacted_at', 'next_follow_up_at'];

        if (! in_array($field, $allowed, true)) {
            return;
        }

        if ($this->sort === $field) {
            $this->dir = $this->dir === 'asc' ? 'desc' : 'asc';
            return;
        }

        $this->sort = $field;
        $this->dir = in_array($field, ['created_at', 'updated_at', 'last_contacted_at'], true) ? 'desc' : 'asc';
    }

    public function toggleCreateForm(): void
    {
        $this->showCreateForm = ! $this->showCreateForm;

        if ($this->showCreateForm) {
            $this->resetLeadForm();
        }
    }

    public function createLead(): void
    {
        $data = $this->validateLeadForm();
        $notes = trim((string) ($data['notes'] ?? ''));
        unset($data['notes']);

        $lead = Lead::query()->create(array_merge($data, [
            'lead_type' => $this->pipeline,
            'status' => $data['status'] ?: 'new',
            'priority' => $data['priority'] ?: Lead::PRIORITY_NORMAL,
            'data' => [
                'source' => $data['source'] ?: 'manual_admin',
                'created_from' => 'admin_crm',
            ],
        ]));

        $lead->activities()->create([
            'actor_user_id' => auth()->id(),
            'type' => LeadActivity::TYPE_NOTE,
            'summary' => 'Lead created',
            'body' => $notes !== '' ? $notes : null,
            'occurred_at' => now(),
            'metadata' => ['created_from' => 'admin_crm'],
        ]);

        $this->showCreateForm = false;
        $this->selectedLeadId = $lead->id;
        $this->loadLeadForm($lead);
        $this->resetActivityForm();

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Lead created',
            'message' => 'The CRM record is ready.',
        ]);
    }

    public function openLead(int $id): void
    {
        $lead = Lead::query()->findOrFail($id);
        $this->selectedLeadId = $lead->id;
        $this->loadLeadForm($lead);
        $this->resetActivityForm();
    }

    public function closeLead(): void
    {
        $this->selectedLeadId = null;
        $this->resetActivityForm();
    }

    public function saveLead(): void
    {
        $lead = $this->selectedLead;
        if (! $lead) {
            return;
        }

        $data = $this->validateLeadForm(forExistingLead: $lead);
        unset($data['notes']);

        $oldStatus = $lead->status;
        $oldAssignee = $lead->assigned_admin_id;

        $lead->fill($data);
        $lead->save();

        if ($oldStatus !== $lead->status) {
            $this->recordStageChange($lead, $oldStatus, $lead->status);
        }

        if ((int) $oldAssignee !== (int) $lead->assigned_admin_id) {
            $lead->activities()->create([
                'actor_user_id' => auth()->id(),
                'type' => LeadActivity::TYPE_ASSIGNMENT,
                'summary' => 'Owner updated',
                'body' => $lead->assignedAdmin
                    ? 'Assigned to '.$lead->assignedAdmin->name.'.'
                    : 'Lead is now unassigned.',
                'occurred_at' => now(),
            ]);
        }

        $this->loadLeadForm($lead->fresh());

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Saved',
            'message' => 'Lead details updated.',
        ]);
    }

    public function updateStatus(int $leadId, string $status): void
    {
        $lead = Lead::query()->findOrFail($leadId);
        if (! array_key_exists($status, $lead->stageOptions())) {
            return;
        }

        $oldStatus = $lead->status;
        $lead->status = $status;

        if (in_array($status, ['contacted', 'outreach', 'meeting_scheduled'], true)) {
            $lead->last_contacted_at ??= now();
        }

        if (in_array($status, ['converted', 'active_referral'], true)) {
            $lead->converted_at ??= now();
        }

        $lead->save();
        $this->recordStageChange($lead, $oldStatus, $status);

        if ($this->selectedLeadId === $lead->id) {
            $this->loadLeadForm($lead->fresh());
        }
    }

    public function assignToMe(int $leadId): void
    {
        $lead = Lead::query()->findOrFail($leadId);
        $lead->forceFill(['assigned_admin_id' => auth()->id()])->save();

        $lead->activities()->create([
            'actor_user_id' => auth()->id(),
            'type' => LeadActivity::TYPE_ASSIGNMENT,
            'summary' => 'Owner updated',
            'body' => 'Assigned to '.auth()->user()?->name.'.',
            'occurred_at' => now(),
        ]);

        if ($this->selectedLeadId === $lead->id) {
            $this->loadLeadForm($lead->fresh());
        }
    }

    public function logActivity(): void
    {
        $lead = $this->selectedLead;
        if (! $lead) {
            return;
        }

        $validated = $this->validate([
            'activityForm.type' => ['required', Rule::in(array_keys($this->activityTypeOptions()))],
            'activityForm.body' => ['required', 'string', 'max:3000'],
            'activityForm.occurred_at' => ['nullable', 'date'],
            'activityForm.next_follow_up_at' => ['nullable', 'date'],
        ]);

        $activity = $validated['activityForm'];
        $occurredAt = filled($activity['occurred_at'])
            ? Carbon::parse($activity['occurred_at'])
            : now();

        $lead->activities()->create([
            'actor_user_id' => auth()->id(),
            'type' => $activity['type'],
            'summary' => $this->activityTypeOptions()[$activity['type']],
            'body' => trim((string) $activity['body']),
            'occurred_at' => $occurredAt,
        ]);

        if (in_array($activity['type'], [LeadActivity::TYPE_CALL, LeadActivity::TYPE_EMAIL, LeadActivity::TYPE_SMS, LeadActivity::TYPE_MEETING], true)) {
            $lead->last_contacted_at = $occurredAt;
        }

        if (filled($activity['next_follow_up_at'])) {
            $lead->next_follow_up_at = Carbon::parse($activity['next_follow_up_at']);
        }

        $lead->save();
        $this->loadLeadForm($lead->fresh());
        $this->resetActivityForm();
    }

    public function deleteLead(int $leadId): void
    {
        Lead::query()->whereKey($leadId)->delete();

        if ($this->selectedLeadId === $leadId) {
            $this->closeLead();
        }

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Deleted',
            'message' => 'Lead deleted.',
        ]);
    }

    public function getSelectedLeadProperty(): ?Lead
    {
        return $this->selectedLeadId
            ? Lead::query()
                ->with(['assignedAdmin:id,name,email', 'activities.actor:id,name,email'])
                ->find($this->selectedLeadId)
            : null;
    }

    public function render(): View
    {
        $boardLeads = $this->baseQuery(applyStatus: false)
            ->with('assignedAdmin:id,name,email')
            ->orderByRaw('next_follow_up_at is null')
            ->orderBy('next_follow_up_at')
            ->latest('updated_at')
            ->limit(120)
            ->get()
            ->groupBy('status');

        $leads = $this->baseQuery()
            ->with('assignedAdmin:id,name,email')
            ->when($this->sort === 'next_follow_up_at', function (Builder $query) {
                $query->orderByRaw('next_follow_up_at is null');
            })
            ->orderBy($this->sort, $this->dir)
            ->paginate($this->perPage);

        return view('livewire.admin.leads-index', [
            'admins' => $this->adminOptions(),
            'activityTypeOptions' => $this->activityTypeOptions(),
            'boardLeads' => $boardLeads,
            'leads' => $leads,
            'pipelineOptions' => $this->pipelineOptions(),
            'priorityOptions' => $this->priorityOptions(),
            'selectedLead' => $this->selectedLead,
            'sourceOptions' => $this->sourceOptions(),
            'stageOptions' => $this->currentStageOptions(),
            'stats' => $this->stats(),
        ]);
    }

    private function baseQuery(bool $applyStatus = true): Builder
    {
        $query = Lead::query()
            ->where('lead_type', $this->pipeline);

        if ($applyStatus && $this->status !== 'all') {
            $query->where('status', $this->status);
        }

        if ($this->assigned === 'me') {
            $query->where('assigned_admin_id', auth()->id());
        } elseif ($this->assigned === 'unassigned') {
            $query->whereNull('assigned_admin_id');
        } elseif ($this->assigned !== 'all') {
            $query->where('assigned_admin_id', (int) $this->assigned);
        }

        if ($this->source !== 'all') {
            $query->where('source', $this->source);
        }

        if ($this->priority !== 'all') {
            $query->where('priority', $this->priority);
        }

        $q = trim($this->q);
        if ($q !== '') {
            $qLower = Str::lower($q);
            $query->where(function (Builder $sub) use ($q, $qLower) {
                $sub->where('email', 'like', "%{$qLower}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('company', 'like', "%{$q}%")
                    ->orWhere('contact_role', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%")
                    ->orWhere('zip', 'like', "%{$q}%")
                    ->orWhere('source_detail', 'like', "%{$q}%");
            });
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function validateLeadForm(?Lead $forExistingLead = null): array
    {
        $stageKeys = array_keys($forExistingLead?->stageOptions() ?? $this->currentStageOptions());
        $adminIds = array_keys($this->adminOptions());

        $validated = $this->validate([
            'leadForm.name' => ['required', 'string', 'max:160'],
            'leadForm.email' => ['nullable', 'email', 'max:160'],
            'leadForm.phone' => ['nullable', 'string', 'max:40'],
            'leadForm.company' => ['nullable', 'string', 'max:180'],
            'leadForm.contact_role' => ['nullable', 'string', 'max:120'],
            'leadForm.location' => ['nullable', 'string', 'max:160'],
            'leadForm.zip' => ['nullable', 'string', 'max:20'],
            'leadForm.status' => ['required', Rule::in($stageKeys)],
            'leadForm.priority' => ['required', Rule::in(array_keys($this->priorityOptions()))],
            'leadForm.source' => ['nullable', Rule::in(array_keys($this->sourceOptions()))],
            'leadForm.source_detail' => ['nullable', 'string', 'max:255'],
            'leadForm.assigned_admin_id' => ['nullable', Rule::in($adminIds)],
            'leadForm.last_contacted_at' => ['nullable', 'date'],
            'leadForm.next_follow_up_at' => ['nullable', 'date'],
            'leadForm.closed_reason' => ['nullable', 'string', 'max:255'],
            'leadForm.notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $data = $validated['leadForm'];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim($value) !== '' ? trim($value) : null;
            }
        }

        $data['assigned_admin_id'] = filled($data['assigned_admin_id']) ? (int) $data['assigned_admin_id'] : null;
        $data['last_contacted_at'] = filled($data['last_contacted_at']) ? Carbon::parse($data['last_contacted_at']) : null;
        $data['next_follow_up_at'] = filled($data['next_follow_up_at']) ? Carbon::parse($data['next_follow_up_at']) : null;

        return $data;
    }

    private function loadLeadForm(?Lead $lead = null): void
    {
        $lead ??= $this->selectedLead;

        if (! $lead) {
            $this->resetLeadForm();
            return;
        }

        $this->leadForm = [
            'name' => (string) $lead->name,
            'email' => (string) $lead->email,
            'phone' => (string) $lead->phone,
            'company' => (string) $lead->company,
            'contact_role' => (string) $lead->contact_role,
            'location' => (string) $lead->location,
            'zip' => (string) $lead->zip,
            'status' => (string) $lead->status,
            'priority' => (string) ($lead->priority ?: Lead::PRIORITY_NORMAL),
            'source' => (string) ($lead->source ?: ''),
            'source_detail' => (string) $lead->source_detail,
            'assigned_admin_id' => $lead->assigned_admin_id ? (string) $lead->assigned_admin_id : '',
            'last_contacted_at' => $lead->last_contacted_at?->format('Y-m-d\TH:i') ?? '',
            'next_follow_up_at' => $lead->next_follow_up_at?->format('Y-m-d\TH:i') ?? '',
            'closed_reason' => (string) $lead->closed_reason,
            'notes' => '',
        ];
    }

    private function resetLeadForm(): void
    {
        $this->leadForm = [
            'name' => '',
            'email' => '',
            'phone' => '',
            'company' => '',
            'contact_role' => '',
            'location' => '',
            'zip' => '',
            'status' => 'new',
            'priority' => Lead::PRIORITY_NORMAL,
            'source' => '',
            'source_detail' => '',
            'assigned_admin_id' => '',
            'last_contacted_at' => '',
            'next_follow_up_at' => '',
            'closed_reason' => '',
            'notes' => '',
        ];
    }

    private function resetActivityForm(): void
    {
        $this->activityForm = [
            'type' => LeadActivity::TYPE_NOTE,
            'body' => '',
            'occurred_at' => now()->format('Y-m-d\TH:i'),
            'next_follow_up_at' => '',
        ];
    }

    private function recordStageChange(Lead $lead, string $oldStatus, string $newStatus): void
    {
        if ($oldStatus === $newStatus) {
            return;
        }

        $lead->activities()->create([
            'actor_user_id' => auth()->id(),
            'type' => LeadActivity::TYPE_STAGE_CHANGE,
            'summary' => 'Stage changed',
            'body' => sprintf('Moved from %s to %s.', $this->stageName($lead, $oldStatus), $this->stageName($lead, $newStatus)),
            'occurred_at' => now(),
            'metadata' => ['from' => $oldStatus, 'to' => $newStatus],
        ]);
    }

    private function stageName(Lead $lead, string $stage): string
    {
        return $lead->stageOptions()[$stage] ?? str($stage)->replace('_', ' ')->title()->toString();
    }

    /** @return array<string, string> */
    public function currentStageOptions(): array
    {
        return $this->pipeline === Lead::TYPE_REFERRAL ? Lead::REFERRAL_STAGES : Lead::FAMILY_STAGES;
    }

    /** @return array<string, string> */
    private function pipelineOptions(): array
    {
        return [
            Lead::TYPE_FAMILY => 'Family / care receiver leads',
            Lead::TYPE_REFERRAL => 'Referral source recruiting',
        ];
    }

    /** @return array<string, string> */
    private function priorityOptions(): array
    {
        return [
            Lead::PRIORITY_LOW => 'Low',
            Lead::PRIORITY_NORMAL => 'Normal',
            Lead::PRIORITY_HIGH => 'High',
            Lead::PRIORITY_URGENT => 'Urgent',
        ];
    }

    /** @return array<string, string> */
    private function sourceOptions(): array
    {
        return [
            'manual_admin' => 'Manual entry',
            'callback_page' => 'Callback page',
            'voice_agent' => 'Voice agent',
            'website' => 'Website form',
            'phone' => 'Phone call',
            'email' => 'Email',
            'pcp_outreach' => 'PCP outreach',
            'case_manager' => 'Case manager',
            'hospital' => 'Hospital / discharge',
            'community_event' => 'Community event',
            'referral' => 'Referral',
            'other' => 'Other',
        ];
    }

    /** @return array<string, string> */
    private function activityTypeOptions(): array
    {
        return [
            LeadActivity::TYPE_NOTE => 'Note',
            LeadActivity::TYPE_CALL => 'Call',
            LeadActivity::TYPE_EMAIL => 'Email',
            LeadActivity::TYPE_SMS => 'SMS',
            LeadActivity::TYPE_MEETING => 'Meeting',
        ];
    }

    /** @return array<int, string> */
    private function adminOptions(): array
    {
        return User::query()
            ->where(function (Builder $query) {
                $query->where('role', 'admin')
                    ->orWhereRaw('lower(email) = ?', ['test@test.com']);
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<string, int> */
    private function stats(): array
    {
        $base = Lead::query()->where('lead_type', $this->pipeline);

        $closedStages = $this->pipeline === Lead::TYPE_REFERRAL
            ? ['active_referral', 'not_fit', 'closed']
            : ['converted', 'lost', 'closed'];

        return [
            'total' => (clone $base)->count(),
            'open' => (clone $base)->whereNotIn('status', $closedStages)->count(),
            'due' => (clone $base)
                ->whereNotNull('next_follow_up_at')
                ->where('next_follow_up_at', '<=', now())
                ->whereNotIn('status', $closedStages)
                ->count(),
            'unassigned' => (clone $base)->whereNull('assigned_admin_id')->count(),
            'converted' => (clone $base)->whereIn('status', $this->pipeline === Lead::TYPE_REFERRAL ? ['active_referral'] : ['converted'])->count(),
        ];
    }

    public function stageBadgeClass(string $status): string
    {
        return match ($status) {
            'new' => 'bg-blue-50 text-blue-700 ring-blue-100',
            'contacted', 'outreach', 'meeting_scheduled', 'intake_scheduled' => 'bg-amber-50 text-amber-700 ring-amber-100',
            'qualified', 'active_referral', 'converted' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
            'lost', 'not_fit', 'closed' => 'bg-slate-100 text-slate-700 ring-slate-200',
            default => 'bg-indigo-50 text-indigo-700 ring-indigo-100',
        };
    }

    public function priorityBadgeClass(?string $priority): string
    {
        return match ($priority) {
            Lead::PRIORITY_URGENT => 'bg-rose-50 text-rose-700 ring-rose-100',
            Lead::PRIORITY_HIGH => 'bg-orange-50 text-orange-700 ring-orange-100',
            Lead::PRIORITY_LOW => 'bg-slate-50 text-slate-600 ring-slate-200',
            default => 'bg-sky-50 text-sky-700 ring-sky-100',
        };
    }
}
