<?php

namespace App\Livewire\Sdr;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Support\SdrOutreach;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CallingConsole extends Component
{
    public ?int $activeLeadId = null;

    public string $outcome = '';

    public string $note = '';

    public string $followUpAt = '';

    public function mount(): void
    {
        $this->activeLeadId = $this->nextOwnedLead()?->id;
    }

    public function claimNextLead(): void
    {
        if ($this->activeLead()) {
            $this->dispatch('toast', [
                'type' => 'info',
                'title' => 'Finish this lead first',
                'message' => 'Log an outcome or release this lead before claiming the next one.',
            ]);

            return;
        }

        $lead = DB::transaction(function (): ?Lead {
            $lead = Lead::query()
                ->where('lead_type', Lead::TYPE_REFERRAL)
                ->where('source', SdrOutreach::SOURCE)
                ->whereNull('assigned_admin_id')
                ->whereNotIn('status', ['active_referral', 'not_fit', 'lost', 'closed'])
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->orderByRaw("case priority when 'urgent' then 1 when 'high' then 2 when 'normal' then 3 else 4 end")
                ->orderByRaw('next_follow_up_at is null')
                ->orderBy('next_follow_up_at')
                ->oldest('created_at')
                ->lockForUpdate()
                ->first();

            if (! $lead) {
                return null;
            }

            $oldStatus = $lead->status;
            $lead->forceFill([
                'assigned_admin_id' => auth()->id(),
                'status' => 'outreach',
            ])->save();

            $lead->activities()->create([
                'actor_user_id' => auth()->id(),
                'type' => LeadActivity::TYPE_ASSIGNMENT,
                'summary' => 'Claimed for SDR call',
                'body' => 'Claimed by '.auth()->user()?->name.'.',
                'occurred_at' => now(),
                'metadata' => [
                    'source' => 'sdr_calling_console',
                    'from' => $oldStatus,
                    'to' => 'outreach',
                ],
            ]);

            return $lead->fresh();
        });

        if (! $lead) {
            $this->dispatch('toast', [
                'type' => 'info',
                'title' => 'No callable leads',
                'message' => 'Import more unclaimed practice leads or check the CRM filters.',
            ]);

            return;
        }

        $this->activeLeadId = $lead->id;
        $this->resetOutcomeForm();
    }

    public function releaseLead(): void
    {
        $lead = $this->activeLead();
        if (! $lead || (int) $lead->assigned_admin_id !== (int) auth()->id()) {
            return;
        }

        $lead->forceFill(['assigned_admin_id' => null])->save();
        $lead->activities()->create([
            'actor_user_id' => auth()->id(),
            'type' => LeadActivity::TYPE_ASSIGNMENT,
            'summary' => 'Released from SDR queue',
            'body' => 'Released by '.auth()->user()?->name.'.',
            'occurred_at' => now(),
            'metadata' => ['source' => 'sdr_calling_console'],
        ]);

        $this->activeLeadId = null;
        $this->resetOutcomeForm();
    }

    public function logOutcome(): void
    {
        $lead = $this->activeLead();
        if (! $lead) {
            return;
        }

        $validated = $this->validate([
            'outcome' => ['required', Rule::in(array_keys(SdrOutreach::outcomeOptions()))],
            'note' => ['nullable', 'string', 'max:3000'],
            'followUpAt' => ['nullable', 'date'],
        ]);

        $outcome = $validated['outcome'];
        $stage = SdrOutreach::stageForOutcome($outcome);
        $oldStatus = $lead->status;
        $followUpAt = filled($validated['followUpAt'])
            ? Carbon::parse($validated['followUpAt'])
            : SdrOutreach::defaultFollowUpForOutcome($outcome);

        $data = $lead->data ?: [];
        data_set($data, 'sdr.last_outcome', $outcome);
        data_set($data, 'sdr.last_outcome_label', SdrOutreach::outcomeLabel($outcome));
        data_set($data, 'sdr.last_called_at', now()->toISOString());
        data_set($data, 'sdr.last_called_by', auth()->id());

        $lead->forceFill([
            'status' => $stage,
            'last_contacted_at' => now(),
            'next_follow_up_at' => $followUpAt,
            'closed_reason' => SdrOutreach::closedReasonForOutcome($outcome),
            'converted_at' => $stage === 'active_referral' ? ($lead->converted_at ?: now()) : $lead->converted_at,
            'data' => $data,
        ])->save();

        $lead->activities()->create([
            'actor_user_id' => auth()->id(),
            'type' => LeadActivity::TYPE_CALL,
            'summary' => 'SDR call: '.SdrOutreach::outcomeLabel($outcome),
            'body' => trim((string) $validated['note']) !== '' ? trim((string) $validated['note']) : null,
            'occurred_at' => now(),
            'metadata' => [
                'source' => 'sdr_calling_console',
                'sdr_outcome' => $outcome,
                'sdr_outcome_label' => SdrOutreach::outcomeLabel($outcome),
                'follow_up_at' => $followUpAt?->toISOString(),
                'tags' => SdrOutreach::leadTags($lead),
            ],
        ]);

        if ($oldStatus !== $stage) {
            $lead->activities()->create([
                'actor_user_id' => auth()->id(),
                'type' => LeadActivity::TYPE_STAGE_CHANGE,
                'summary' => 'Stage changed',
                'body' => 'Moved from '.$this->stageLabel($oldStatus).' to '.$this->stageLabel($stage).'.',
                'occurred_at' => now(),
                'metadata' => [
                    'source' => 'sdr_calling_console',
                    'from' => $oldStatus,
                    'to' => $stage,
                ],
            ]);
        }

        $this->activeLeadId = null;
        $this->resetOutcomeForm();

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Outcome saved',
            'message' => 'The lead timeline and referral CRM were updated.',
        ]);
    }

    private function activeLead(): ?Lead
    {
        return $this->activeLeadId
            ? Lead::query()
                ->with(['assignedAdmin:id,name,email', 'activities' => fn ($query) => $query->latest('occurred_at')->limit(5)])
                ->find($this->activeLeadId)
            : null;
    }

    public function render(): View
    {
        $activeLead = $this->activeLead();

        return view('livewire.sdr.calling-console', [
            'activeLead' => $activeLead,
            'availableCount' => $this->availableCount(),
            'callStats' => $this->callStats(),
            'outcomeOptions' => SdrOutreach::outcomeOptions(),
            'recentCalls' => $this->recentCalls(),
            'telHref' => $activeLead ? SdrOutreach::telHref($activeLead->phone) : null,
            'zoomHref' => $activeLead ? SdrOutreach::zoomCallHref($activeLead->phone) : null,
        ]);
    }

    private function nextOwnedLead(): ?Lead
    {
        return Lead::query()
            ->where('lead_type', Lead::TYPE_REFERRAL)
            ->where('source', SdrOutreach::SOURCE)
            ->where('assigned_admin_id', auth()->id())
            ->whereNotIn('status', ['active_referral', 'not_fit', 'lost', 'closed'])
            ->orderByRaw('next_follow_up_at is null')
            ->orderBy('next_follow_up_at')
            ->latest('updated_at')
            ->first();
    }

    private function availableCount(): int
    {
        return Lead::query()
            ->where('lead_type', Lead::TYPE_REFERRAL)
            ->where('source', SdrOutreach::SOURCE)
            ->whereNull('assigned_admin_id')
            ->whereNotIn('status', ['active_referral', 'not_fit', 'lost', 'closed'])
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->count();
    }

    /** @return array<string, int> */
    private function callStats(): array
    {
        $calls = LeadActivity::query()
            ->where('actor_user_id', auth()->id())
            ->where('type', LeadActivity::TYPE_CALL)
            ->where('occurred_at', '>=', now()->startOfDay())
            ->get()
            ->filter(fn (LeadActivity $activity): bool => filled(data_get($activity->metadata, 'sdr_outcome')));

        return [
            'today' => $calls->count(),
            'resource_requested' => $calls->filter(fn (LeadActivity $activity): bool => data_get($activity->metadata, 'sdr_outcome') === 'resource_requested')->count(),
            'meeting_requested' => $calls->filter(fn (LeadActivity $activity): bool => data_get($activity->metadata, 'sdr_outcome') === 'meeting_requested')->count(),
        ];
    }

    private function recentCalls(): \Illuminate\Support\Collection
    {
        return LeadActivity::query()
            ->with('lead:id,name,company,status')
            ->where('actor_user_id', auth()->id())
            ->where('type', LeadActivity::TYPE_CALL)
            ->latest('occurred_at')
            ->limit(5)
            ->get()
            ->filter(fn (LeadActivity $activity): bool => filled(data_get($activity->metadata, 'sdr_outcome')));
    }

    private function resetOutcomeForm(): void
    {
        $this->outcome = '';
        $this->note = '';
        $this->followUpAt = '';
    }

    private function stageLabel(string $stage): string
    {
        return Lead::REFERRAL_STAGES[$stage] ?? str($stage)->replace('_', ' ')->title()->toString();
    }
}
