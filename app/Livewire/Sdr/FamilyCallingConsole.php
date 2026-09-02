<?php

namespace App\Livewire\Sdr;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Support\FamilyLeadOutreach;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class FamilyCallingConsole extends Component
{
    public ?int $activeLeadId = null;

    public string $outcome = '';

    public string $note = '';

    public string $followUpAt = '';

    public bool $callStarted = false;

    public function mount(): void
    {
        $lead = $this->nextOwnedLead();
        $this->activeLeadId = $lead?->id;
        $this->callStarted = filled(data_get($lead?->data, 'family_outreach.current_call_started_at'));
    }

    public function claimNextLead(): void
    {
        if ($this->activeLead()) {
            $this->dispatch('toast', [
                'type' => 'info',
                'title' => 'Finish this family first',
                'message' => 'Save an outcome or release the lead before claiming another.',
            ]);

            return;
        }

        $lead = DB::transaction(function (): ?Lead {
            $lead = $this->callableQuery()
                ->whereNull('assigned_admin_id')
                ->orderByRaw("case priority when 'urgent' then 1 when 'high' then 2 when 'normal' then 3 else 4 end")
                ->orderByRaw('next_follow_up_at is null desc')
                ->orderBy('next_follow_up_at')
                ->oldest('submitted_at')
                ->lockForUpdate()
                ->first();

            if (! $lead) {
                return null;
            }

            $oldStatus = $lead->status;
            $lead->forceFill([
                'assigned_admin_id' => auth()->id(),
                'status' => $lead->status === 'new' ? 'attempting_contact' : $lead->status,
            ])->save();

            $lead->activities()->create([
                'actor_user_id' => auth()->id(),
                'type' => LeadActivity::TYPE_ASSIGNMENT,
                'summary' => 'Claimed for family call',
                'body' => 'Claimed by '.auth()->user()?->name.'.',
                'occurred_at' => now(),
                'metadata' => [
                    'source' => 'family_calling_console',
                    'from' => $oldStatus,
                    'to' => $lead->status,
                ],
            ]);

            return $lead->fresh();
        });

        if (! $lead) {
            $this->dispatch('toast', [
                'type' => 'info',
                'title' => 'Queue is clear',
                'message' => 'There are no unassigned family calls due right now.',
            ]);

            return;
        }

        $this->activeLeadId = $lead->id;
        $this->resetOutcomeForm();
    }

    public function startCall(): void
    {
        $lead = $this->activeLead();
        if (! $lead || (int) $lead->assigned_admin_id !== (int) auth()->id()) {
            return;
        }

        $startedAt = now();
        $data = $lead->data ?: [];
        data_set($data, 'family_outreach.current_call_started_at', $startedAt->toISOString());
        data_set($data, 'family_outreach.current_call_started_by', auth()->id());

        $lead->forceFill([
            'first_call_at' => $lead->first_call_at ?: $startedAt,
            'data' => $data,
        ])->save();

        $this->callStarted = true;
    }

    public function releaseLead(): void
    {
        $lead = $this->activeLead();
        if (! $lead || (int) $lead->assigned_admin_id !== (int) auth()->id()) {
            return;
        }

        $data = $lead->data ?: [];
        data_forget($data, 'family_outreach.current_call_started_at');
        data_forget($data, 'family_outreach.current_call_started_by');

        $lead->forceFill(['assigned_admin_id' => null, 'data' => $data])->save();
        $lead->activities()->create([
            'actor_user_id' => auth()->id(),
            'type' => LeadActivity::TYPE_ASSIGNMENT,
            'summary' => 'Released from family queue',
            'body' => 'Released by '.auth()->user()?->name.'.',
            'occurred_at' => now(),
            'metadata' => ['source' => 'family_calling_console'],
        ]);

        $this->activeLeadId = null;
        $this->resetOutcomeForm();
    }

    public function logOutcome(?string $selectedOutcome = null): void
    {
        if ($selectedOutcome !== null) {
            $this->outcome = $selectedOutcome;
        }

        $lead = $this->activeLead();
        if (! $lead || (int) $lead->assigned_admin_id !== (int) auth()->id()) {
            return;
        }

        $validated = $this->validate([
            'outcome' => ['required', Rule::in(array_keys(FamilyLeadOutreach::outcomeOptions()))],
            'note' => ['nullable', 'string', 'max:3000'],
            'followUpAt' => ['nullable', 'required_if:outcome,callback_requested', 'date', 'after_or_equal:now'],
        ], [
            'followUpAt.required_if' => 'Choose the time the family requested.',
        ]);

        $outcome = $validated['outcome'];
        $callNumber = ((int) $lead->call_attempt_count) + 1;
        $unansweredAttemptNumber = FamilyLeadOutreach::isRetryable($outcome)
            ? min(FamilyLeadOutreach::MAX_ATTEMPTS, ((int) $lead->unanswered_attempt_count) + 1)
            : (FamilyLeadOutreach::isConnected($outcome) ? 0 : (int) $lead->unanswered_attempt_count);
        $stage = FamilyLeadOutreach::stageForOutcome($outcome, $unansweredAttemptNumber);
        $oldStatus = $lead->status;

        $followUpAt = filled($validated['followUpAt'])
            ? Carbon::parse($validated['followUpAt'])
            : FamilyLeadOutreach::defaultFollowUpForOutcome($outcome, $unansweredAttemptNumber, $lead);

        $callStartedAt = data_get($lead->data, 'family_outreach.current_call_started_at');
        $firstCallAt = $lead->first_call_at
            ?: (filled($callStartedAt) ? Carbon::parse($callStartedAt) : now());

        $data = $lead->data ?: [];
        data_set($data, 'family_outreach.last_outcome', $outcome);
        data_set($data, 'family_outreach.last_outcome_label', FamilyLeadOutreach::outcomeLabel($outcome));
        data_set($data, 'family_outreach.last_called_at', now()->toISOString());
        data_set($data, 'family_outreach.last_called_by', auth()->id());
        data_forget($data, 'family_outreach.current_call_started_at');
        data_forget($data, 'family_outreach.current_call_started_by');

        $lead->forceFill([
            'status' => $stage,
            'call_attempt_count' => $callNumber,
            'unanswered_attempt_count' => $unansweredAttemptNumber,
            'first_call_at' => $firstCallAt,
            'first_connected_at' => FamilyLeadOutreach::isConnected($outcome)
                ? ($lead->first_connected_at ?: now())
                : $lead->first_connected_at,
            'last_contacted_at' => now(),
            'next_follow_up_at' => $followUpAt,
            'do_not_contact_at' => $outcome === 'do_not_contact' ? now() : $lead->do_not_contact_at,
            'closed_reason' => FamilyLeadOutreach::closedReasonForOutcome($outcome, $unansweredAttemptNumber),
            'data' => $data,
        ])->save();

        $lead->activities()->create([
            'actor_user_id' => auth()->id(),
            'type' => LeadActivity::TYPE_CALL,
            'summary' => 'Family call: '.FamilyLeadOutreach::outcomeLabel($outcome),
            'body' => trim((string) $validated['note']) !== '' ? trim((string) $validated['note']) : null,
            'occurred_at' => now(),
            'metadata' => [
                'source' => 'family_calling_console',
                'family_outcome' => $outcome,
                'family_outcome_label' => FamilyLeadOutreach::outcomeLabel($outcome),
                'family_attempt_number' => $callNumber,
                'family_unanswered_attempt_number' => FamilyLeadOutreach::isRetryable($outcome) ? $unansweredAttemptNumber : null,
                'connected' => FamilyLeadOutreach::isConnected($outcome),
                'follow_up_at' => $followUpAt?->toISOString(),
            ],
        ]);

        if ($oldStatus !== $stage) {
            $lead->activities()->create([
                'actor_user_id' => auth()->id(),
                'type' => LeadActivity::TYPE_STAGE_CHANGE,
                'summary' => 'Family stage changed',
                'body' => 'Moved from '.$this->stageLabel($oldStatus).' to '.$this->stageLabel($stage).'.',
                'occurred_at' => now(),
                'metadata' => [
                    'source' => 'family_calling_console',
                    'from' => $oldStatus,
                    'to' => $stage,
                ],
            ]);
        }

        $this->resetOutcomeForm();
        $this->activeLeadId = $this->nextOwnedLead()?->id;

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Outcome saved',
            'message' => $stage === 'unreachable'
                ? 'Seven attempts are complete. The lead is now marked unreachable.'
                : ($followUpAt ? 'The lead will return to the queue at the scheduled time.' : 'The CRM and family funnel were updated.'),
        ]);
    }

    public function render(): View
    {
        $activeLead = $this->activeLead();

        return view('livewire.sdr.family-calling-console', [
            'activeLead' => $activeLead,
            'availableCount' => $this->availableCount(),
            'callStats' => $this->callStats(),
            'dueOwnedCount' => $this->dueOwnedCount(),
            'outcomeOptions' => FamilyLeadOutreach::outcomeOptions(),
            'recentCalls' => $this->recentCalls(),
            'telHref' => FamilyLeadOutreach::telHref($activeLead?->phone),
        ]);
    }

    private function activeLead(): ?Lead
    {
        return $this->activeLeadId
            ? Lead::query()
                ->with([
                    'assignedAdmin:id,name,email',
                    'activities' => fn ($query) => $query->with('actor:id,name,email')->latest('occurred_at')->limit(20),
                ])
                ->find($this->activeLeadId)
            : null;
    }

    private function nextOwnedLead(): ?Lead
    {
        return $this->callableQuery()
            ->where('assigned_admin_id', auth()->id())
            ->orderByRaw("case priority when 'urgent' then 1 when 'high' then 2 when 'normal' then 3 else 4 end")
            ->orderBy('next_follow_up_at')
            ->oldest('submitted_at')
            ->first();
    }

    private function callableQuery(): Builder
    {
        return Lead::query()
            ->where('lead_type', Lead::TYPE_FAMILY)
            ->whereNotIn('status', FamilyLeadOutreach::TERMINAL_STAGES)
            ->where('unanswered_attempt_count', '<', FamilyLeadOutreach::MAX_ATTEMPTS)
            ->whereNull('do_not_contact_at')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->where(function (Builder $query): void {
                $query->whereNull('next_follow_up_at')
                    ->orWhere('next_follow_up_at', '<=', now());
            });
    }

    private function availableCount(): int
    {
        return $this->callableQuery()->whereNull('assigned_admin_id')->count();
    }

    private function dueOwnedCount(): int
    {
        return $this->callableQuery()->where('assigned_admin_id', auth()->id())->count();
    }

    /** @return array<string, int> */
    private function callStats(): array
    {
        $calls = LeadActivity::query()
            ->where('actor_user_id', auth()->id())
            ->where('type', LeadActivity::TYPE_CALL)
            ->where('occurred_at', '>=', now()->startOfDay())
            ->get()
            ->filter(fn (LeadActivity $activity): bool => filled(data_get($activity->metadata, 'family_outcome')));

        return [
            'today' => $calls->count(),
            'connected' => $calls->filter(fn (LeadActivity $activity): bool => (bool) data_get($activity->metadata, 'connected'))->count(),
            'qualified' => $calls->where('metadata.family_outcome', 'connected_qualified')->count()
                + $calls->where('metadata.family_outcome', 'assessment_booked')->count(),
        ];
    }

    private function recentCalls(): \Illuminate\Support\Collection
    {
        return LeadActivity::query()
            ->with('lead:id,name,status,call_attempt_count,unanswered_attempt_count')
            ->where('actor_user_id', auth()->id())
            ->where('type', LeadActivity::TYPE_CALL)
            ->latest('occurred_at')
            ->limit(12)
            ->get()
            ->filter(fn (LeadActivity $activity): bool => filled(data_get($activity->metadata, 'family_outcome')))
            ->take(5);
    }

    private function resetOutcomeForm(): void
    {
        $this->outcome = '';
        $this->note = '';
        $this->followUpAt = '';
        $this->callStarted = false;
    }

    private function stageLabel(string $stage): string
    {
        return Lead::FAMILY_STAGES[$stage] ?? str($stage)->replace('_', ' ')->title()->toString();
    }
}
