<?php

namespace App\Livewire\Admin;

use App\Models\FamilyAcquisitionSetting;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\MarketingSpendDaily;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class FamilyAcquisitionOverview extends Component
{
    public string $range = 'all';

    public string $campaign = 'all';

    public bool $showAlertSettings = false;

    public bool $alertsEnabled = true;

    public string $newLeadAlertEmails = '';

    public string $escalationAlertEmails = '';

    public int $firstCallSlaMinutes = 15;

    public function mount(): void
    {
        $this->loadAlertSettings();
    }

    public function toggleAlertSettings(): void
    {
        $this->showAlertSettings = ! $this->showAlertSettings;
    }

    public function saveAlertSettings(): void
    {
        $validated = $this->validate([
            'alertsEnabled' => ['boolean'],
            'newLeadAlertEmails' => ['nullable', 'string', 'max:3000'],
            'escalationAlertEmails' => ['nullable', 'string', 'max:3000'],
            'firstCallSlaMinutes' => ['required', 'integer', 'min:5', 'max:240'],
        ]);

        $invalidNew = FamilyAcquisitionSetting::invalidEmails($validated['newLeadAlertEmails']);
        $invalidEscalation = FamilyAcquisitionSetting::invalidEmails($validated['escalationAlertEmails']);

        if ($invalidNew !== []) {
            $this->addError('newLeadAlertEmails', 'Check these email addresses: '.implode(', ', $invalidNew));

            return;
        }

        if ($invalidEscalation !== []) {
            $this->addError('escalationAlertEmails', 'Check these email addresses: '.implode(', ', $invalidEscalation));

            return;
        }

        $newRecipients = FamilyAcquisitionSetting::parseEmails($validated['newLeadAlertEmails']);
        if ($validated['alertsEnabled'] && $newRecipients === []) {
            $this->addError('newLeadAlertEmails', 'Add at least one SDR email while alerts are enabled.');

            return;
        }

        $escalationRecipients = FamilyAcquisitionSetting::parseEmails($validated['escalationAlertEmails']);
        $settings = FamilyAcquisitionSetting::current();
        $settings->forceFill([
            'alerts_enabled' => (bool) $validated['alertsEnabled'],
            'new_lead_alert_emails' => $newRecipients !== [] ? implode("\n", $newRecipients) : null,
            'escalation_alert_emails' => $escalationRecipients !== [] ? implode("\n", $escalationRecipients) : null,
            'first_call_sla_minutes' => (int) $validated['firstCallSlaMinutes'],
            'updated_by_user_id' => auth()->id(),
        ])->save();

        $this->loadAlertSettings();
        $this->showAlertSettings = false;
        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Lead alerts updated',
            'message' => $settings->alerts_enabled
                ? 'New family leads will email the SDR list immediately.'
                : 'Family lead email alerts are paused.',
        ]);
    }

    public function render(): View
    {
        $rangeDays = in_array($this->range, ['30', '60', '90'], true) ? (int) $this->range : null;
        $start = $rangeDays ? now()->subDays($rangeDays - 1)->startOfDay() : null;
        $end = now()->endOfDay();

        $liveLeads = Lead::query()
            ->where('lead_type', Lead::TYPE_FAMILY)
            ->get(['id', 'status', 'converted_at']);

        $recentStageChanges = LeadActivity::query()
            ->where('type', LeadActivity::TYPE_STAGE_CHANGE)
            ->whereHas('lead', fn ($query) => $query->where('lead_type', Lead::TYPE_FAMILY))
            ->with([
                'lead:id,name,status',
                'actor:id,name,email',
            ])
            ->latest('occurred_at')
            ->limit(6)
            ->get();

        $leads = Lead::query()
            ->where('lead_type', Lead::TYPE_FAMILY)
            ->when($start, function ($query) use ($start, $end): void {
                $query->where(function ($dateQuery) use ($start, $end): void {
                    $dateQuery->whereBetween('submitted_at', [$start, $end])
                        ->orWhere(function ($fallback) use ($start, $end): void {
                            $fallback->whereNull('submitted_at')->whereBetween('created_at', [$start, $end]);
                        });
                });
            })
            ->with(['activities.actor:id,name,email', 'assignedAdmin:id,name,email'])
            ->get();

        if ($this->campaign !== 'all') {
            $leads = $leads->filter(fn (Lead $lead): bool => (string) data_get($lead->data, 'meta.campaign_id', 'manual') === $this->campaign)->values();
        }

        $spendQuery = MarketingSpendDaily::query()
            ->when($start, fn ($query) => $query->whereBetween('spend_date', [$start->toDateString(), $end->toDateString()]));
        if ($this->campaign !== 'all' && $this->campaign !== 'manual') {
            $spendQuery->where('campaign_id', $this->campaign);
        } elseif ($this->campaign === 'manual') {
            $spendQuery->whereRaw('1 = 0');
        }
        $spend = $spendQuery->get();

        $campaignOptions = MarketingSpendDaily::query()
            ->when($start, fn ($query) => $query->whereBetween('spend_date', [$start->toDateString(), $end->toDateString()]))
            ->orderBy('campaign_name')
            ->pluck('campaign_name', 'campaign_id')
            ->unique()
            ->all();

        $responses = $leads
            ->filter(fn (Lead $lead): bool => $lead->submitted_at && $lead->first_call_at)
            ->map(fn (Lead $lead): int => (int) $lead->submitted_at->diffInMinutes($lead->first_call_at))
            ->sort()
            ->values();

        $leadCount = $leads->count();
        $customerCount = $leads->whereNotNull('converted_at')->count();
        $contactedCount = $leads->whereNotNull('first_connected_at')->count();
        $qualifiedCount = $leads->filter(fn (Lead $lead): bool => in_array($lead->status, ['qualified', 'assessment_scheduled', 'intake_scheduled', 'converted'], true))->count();
        $spendCents = (int) $spend->sum('spend_cents');
        $slaCutoff = now()->subMinutes($this->firstCallSlaMinutes);
        $slaBreaches = $leads->filter(fn (Lead $lead): bool => ! $lead->first_call_at
            && ($lead->submitted_at ?: $lead->created_at) <= $slaCutoff
            && ! in_array($lead->status, ['converted', 'unreachable', 'not_fit', 'lost', 'closed'], true))->count();

        return view('livewire.admin.family-acquisition-overview', [
            'attemptPerformance' => $this->attemptPerformance($leads),
            'campaignOptions' => $campaignOptions,
            'campaignRows' => $this->campaignRows($leads, $spend),
            'funnel' => [
                ['label' => 'Leads received', 'value' => $leadCount],
                ['label' => 'First call attempted', 'value' => $leads->whereNotNull('first_call_at')->count()],
                ['label' => 'Contact established', 'value' => $contactedCount],
                ['label' => 'Qualified', 'value' => $qualifiedCount],
                ['label' => 'Assessment booked', 'value' => $leads->filter(fn (Lead $lead): bool => in_array($lead->status, ['assessment_scheduled', 'intake_scheduled', 'converted'], true))->count()],
                ['label' => 'Care started', 'value' => $customerCount],
            ],
            'metrics' => [
                'leads' => $leadCount,
                'spend' => $spendCents / 100,
                'cpl' => $leadCount > 0 ? ($spendCents / 100) / $leadCount : null,
                'cac' => $customerCount > 0 ? ($spendCents / 100) / $customerCount : null,
                'contact_rate' => $leadCount > 0 ? ($contactedCount / $leadCount) * 100 : 0,
                'conversion_rate' => $leadCount > 0 ? ($customerCount / $leadCount) * 100 : 0,
                'sla_breaches' => $slaBreaches,
            ],
            'livePipeline' => [
                'total' => $liveLeads->count(),
                'new' => $liveLeads->where('status', 'new')->count(),
                'calling' => $liveLeads->whereIn('status', ['attempting_contact', 'callback_scheduled', 'contacted', 'nurture'])->count(),
                'qualified' => $liveLeads->where('status', 'qualified')->count(),
                'assessment' => $liveLeads->whereIn('status', ['assessment_scheduled', 'intake_scheduled'])->count(),
                'care_started' => $liveLeads->whereNotNull('converted_at')->count(),
            ],
            'outcomes' => $this->outcomeDistribution($leads),
            'recentStageChanges' => $recentStageChanges,
            'sdrRows' => $this->sdrRows($leads),
            'speed' => [
                'median' => $this->percentile($responses, 50),
                'p75' => $this->percentile($responses, 75),
                'p90' => $this->percentile($responses, 90),
                'within_15' => $leadCount > 0 ? ($responses->filter(fn (int $minutes): bool => $minutes <= 15)->count() / $leadCount) * 100 : 0,
                'uncalled' => $leads->whereNull('first_call_at')->count(),
                'buckets' => $this->speedBuckets($responses),
            ],
            'start' => $start,
            'end' => $end,
        ]);
    }

    /** @return array<int, array<string, int|float>> */
    private function attemptPerformance(Collection $leads): array
    {
        $activities = $leads->flatMap->activities
            ->filter(fn (LeadActivity $activity): bool => filled(data_get($activity->metadata, 'family_attempt_number')));

        return collect(range(1, 7))->map(function (int $attempt) use ($activities): array {
            $calls = $activities->filter(fn (LeadActivity $activity): bool => (int) data_get($activity->metadata, 'family_attempt_number') === $attempt);
            $connected = $calls->filter(fn (LeadActivity $activity): bool => (bool) data_get($activity->metadata, 'connected'))->count();

            return [
                'attempt' => $attempt,
                'calls' => $calls->count(),
                'connected' => $connected,
                'rate' => $calls->count() > 0 ? ($connected / $calls->count()) * 100 : 0,
            ];
        })->all();
    }

    /** @return array<int, array<string, int|float|string|null>> */
    private function campaignRows(Collection $leads, Collection $spend): array
    {
        $spendByCampaign = $spend->groupBy('campaign_id');

        return $leads
            ->groupBy(fn (Lead $lead): string => (string) data_get($lead->data, 'meta.campaign_id', 'manual'))
            ->map(function (Collection $campaignLeads, string $campaignId) use ($spendByCampaign): array {
                $campaignSpend = (int) $spendByCampaign->get($campaignId, collect())->sum('spend_cents');
                $customers = $campaignLeads->whereNotNull('converted_at')->count();
                $count = $campaignLeads->count();

                return [
                    'id' => $campaignId,
                    'name' => $campaignId === 'manual' ? 'Manual CRM / community' : (string) data_get($campaignLeads->first()?->data, 'meta.campaign_name', $campaignId),
                    'platform' => $campaignId === 'manual' ? 'Manual' : ucfirst((string) data_get($campaignLeads->first()?->data, 'meta.platform', 'Meta')),
                    'leads' => $count,
                    'spend' => $campaignSpend / 100,
                    'cpl' => $count > 0 ? ($campaignSpend / 100) / $count : null,
                    'contact_rate' => $count > 0 ? ($campaignLeads->whereNotNull('first_connected_at')->count() / $count) * 100 : 0,
                    'customers' => $customers,
                    'cac' => $customers > 0 ? ($campaignSpend / 100) / $customers : null,
                ];
            })
            ->sortByDesc('leads')
            ->values()
            ->all();
    }

    /** @return array<int, array<string, int|string>> */
    private function outcomeDistribution(Collection $leads): array
    {
        return $leads->flatMap->activities
            ->filter(fn (LeadActivity $activity): bool => filled(data_get($activity->metadata, 'family_outcome')))
            ->groupBy(fn (LeadActivity $activity): string => (string) data_get($activity->metadata, 'family_outcome_label'))
            ->map(fn (Collection $items, string $label): array => ['label' => $label, 'count' => $items->count()])
            ->sortByDesc('count')
            ->values()
            ->take(8)
            ->all();
    }

    /** @return array<int, array<string, int|float|string>> */
    private function sdrRows(Collection $leads): array
    {
        return $leads->flatMap->activities
            ->filter(fn (LeadActivity $activity): bool => filled(data_get($activity->metadata, 'family_outcome')))
            ->groupBy('actor_user_id')
            ->map(function (Collection $calls): array {
                $connected = $calls->filter(fn (LeadActivity $activity): bool => (bool) data_get($activity->metadata, 'connected'))->count();
                $qualified = $calls->filter(fn (LeadActivity $activity): bool => in_array(data_get($activity->metadata, 'family_outcome'), ['connected_qualified', 'assessment_booked'], true))->count();

                return [
                    'name' => $calls->first()?->actor?->name ?? 'Unassigned / system',
                    'calls' => $calls->count(),
                    'connected' => $connected,
                    'connect_rate' => $calls->count() > 0 ? ($connected / $calls->count()) * 100 : 0,
                    'qualified' => $qualified,
                ];
            })
            ->sortByDesc('calls')
            ->values()
            ->all();
    }

    /** @return array<int, array{label: string, count: int}> */
    private function speedBuckets(Collection $responses): array
    {
        return [
            ['label' => 'Under 5 min', 'count' => $responses->filter(fn (int $minutes): bool => $minutes < 5)->count()],
            ['label' => '5–15 min', 'count' => $responses->filter(fn (int $minutes): bool => $minutes >= 5 && $minutes <= 15)->count()],
            ['label' => '16–30 min', 'count' => $responses->filter(fn (int $minutes): bool => $minutes > 15 && $minutes <= 30)->count()],
            ['label' => '31–60 min', 'count' => $responses->filter(fn (int $minutes): bool => $minutes > 30 && $minutes <= 60)->count()],
            ['label' => 'Over 60 min', 'count' => $responses->filter(fn (int $minutes): bool => $minutes > 60)->count()],
        ];
    }

    private function percentile(Collection $values, int $percentile): ?int
    {
        if ($values->isEmpty()) {
            return null;
        }

        $index = (int) ceil(($percentile / 100) * $values->count()) - 1;

        return (int) $values->values()->get(max(0, $index));
    }

    private function loadAlertSettings(): void
    {
        $settings = FamilyAcquisitionSetting::current();
        $this->alertsEnabled = (bool) $settings->alerts_enabled;
        $this->newLeadAlertEmails = (string) $settings->new_lead_alert_emails;
        $this->escalationAlertEmails = (string) $settings->escalation_alert_emails;
        $this->firstCallSlaMinutes = max(5, (int) $settings->first_call_sla_minutes);
    }
}
