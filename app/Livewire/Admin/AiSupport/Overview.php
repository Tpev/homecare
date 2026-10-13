<?php

namespace App\Livewire\Admin\AiSupport;

use App\Models\AiSupportIncident;
use App\Models\AiSupportInteractionEvent;
use App\Models\AiSupportPilotGrant;
use App\Models\KnowledgeBaseEntry;
use App\Models\KnowledgeBaseVersion;
use App\Services\AiSupport\AiSupportControlService;
use App\Services\AiSupport\AiSupportHealthMonitorService;
use App\Services\AiSupport\AiSupportIncidentService;
use App\Services\AiSupport\AiSupportReadinessService;
use App\Services\AiSupport\FamilyIntentCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Overview extends Component
{
    public string $intentSearch = '';

    public string $intentDisposition = '';

    public bool $intentNeedsKnowledge = false;

    public string $resolutionReason = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->canViewAiSupportPilot(), 403);
    }

    public function resolveIncident(string $incidentId, AiSupportIncidentService $incidents): void
    {
        $this->validate(['resolutionReason' => ['required', 'string', 'min:5', 'max:500']]);
        $incidents->resolve(auth()->user(), AiSupportIncident::query()->findOrFail($incidentId), $this->resolutionReason);
        $this->reset('resolutionReason');
        session()->flash('status', 'Incident resolved. Availability controls were not changed.');
    }

    public function render(AiSupportControlService $controls, FamilyIntentCatalog $intentCatalog, AiSupportReadinessService $evidence, AiSupportHealthMonitorService $health): View
    {
        $now = now();
        $publishedIds = KnowledgeBaseVersion::query()->retrievable()->with('entry:id,stable_id')->get()
            ->pluck('entry.stable_id')->filter()->all();
        $intentRecords = collect($intentCatalog->records())->map(function (array $record) use ($publishedIds): array {
            $stages = (array) data_get($record, 'capability_stages.current', []);
            $record['delivery'] = in_array('Execute', $stages, true) && in_array('Verify', $stages, true)
                ? 'complete'
                : (in_array('Human', $stages, true) ? 'human' : 'assisted');
            $record['missing_knowledge'] = array_values(array_diff((array) $record['kb_stable_ids'], $publishedIds));

            return $record;
        });
        $search = mb_strtolower(trim($this->intentSearch));
        $matchingIntentRecords = $intentRecords
            ->when($search !== '', fn ($records) => $records->filter(fn (array $record): bool => str_contains(mb_strtolower(implode(' ', [
                $record['intent_id'], $record['domain'], $record['intent'], implode(' ', (array) $record['kb_stable_ids']),
            ])), $search)))
            ->when($this->intentDisposition !== '', fn ($records) => $records->where('delivery', $this->intentDisposition))
            ->when($this->intentNeedsKnowledge, fn ($records) => $records->filter(fn (array $record): bool => $record['missing_knowledge'] !== []))
            ->values();
        $outcomeTypes = [
            'intent_unmatched', 'intent_looped', 'intent_failed', 'intent_verification_failed',
            'intent_recovery_offered', 'guided_action_recovery', 'intent_transferred', 'handoff_completed',
            'transferred_to_human',
        ];
        $outcomesQuery = AiSupportInteractionEvent::query()
            ->whereIn('event_type', $outcomeTypes)
            ->where('occurred_at', '>=', now()->subDays(30));
        $outcomeCounts = (clone $outcomesQuery)
            ->selectRaw('event_type, COUNT(*) as aggregate')
            ->groupBy('event_type')
            ->pluck('aggregate', 'event_type')
            ->map(fn (mixed $count): int => (int) $count);
        $outcomeCounts->put(
            'intent_transferred',
            (clone $outcomesQuery)
                ->whereIn('event_type', ['intent_transferred', 'handoff_completed', 'transferred_to_human'])
                ->distinct()
                ->count('support_ticket_id'),
        );
        $recentOutcomes = (clone $outcomesQuery)
            ->select(['id', 'support_ticket_id', 'event_type', 'result_code', 'safe_metadata', 'occurred_at'])
            ->latest('occurred_at')
            ->limit(30)
            ->get();

        return view('livewire.admin.ai-support.overview', [
            'runtimeAvailable' => (bool) config('ai_support.runtime_available', false),
            'providerEnabled' => (bool) config('ai_support.provider_enabled', false),
            'masterState' => $controls->state('master_enabled'),
            'visibleState' => $controls->state('user_visible_enabled'),
            'generalReleaseState' => $controls->state('general_release_enabled'),
            'humanOnlyState' => $controls->state('human_only'),
            'activeGrants' => AiSupportPilotGrant::query()->effectiveAt()->count(),
            'scheduledGrants' => AiSupportPilotGrant::query()
                ->notRevoked()
                ->where('starts_at', '>', $now)
                ->count(),
            'expiringSoon' => AiSupportPilotGrant::query()
                ->effectiveAt()
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $now->copy()->addDays(7))
                ->count(),
            'knowledgeWorkingCount' => KnowledgeBaseEntry::query()
                ->active()
                ->whereNotNull('working_version_id')
                ->count(),
            'knowledgePublishedCount' => KnowledgeBaseEntry::query()
                ->active()
                ->whereNotNull('published_version_id')
                ->count(),
            'knowledgeDraftCount' => KnowledgeBaseEntry::query()
                ->active()
                ->whereHas('workingVersion', fn ($version) => $version->where('status', KnowledgeBaseVersion::STATUS_DRAFT))
                ->count(),
            'knowledgePausedCount' => KnowledgeBaseEntry::query()
                ->active()
                ->whereHas('workingVersion', fn ($version) => $version->where('status', KnowledgeBaseVersion::STATUS_PAUSED))
                ->count(),
            'knowledgeOverdueCount' => KnowledgeBaseEntry::query()
                ->active()
                ->whereHas('workingVersion', fn ($version) => $version->whereDate('review_by', '<', today()))
                ->count(),
            'intentCoverage' => $intentCatalog->coverageSummary(),
            'deliveryCoverage' => $intentRecords->countBy('delivery'),
            'needsKnowledgeCount' => $intentRecords->filter(fn (array $record): bool => $record['missing_knowledge'] !== [])->count(),
            'operationalEvidence' => $evidence->operationalEvidence(),
            'health' => $health->snapshot(),
            'retentionHealth' => Cache::get(\App\Console\Commands\ApplyAiSupportRetention::HEARTBEAT_KEY, []),
            'openIncidents' => AiSupportIncident::query()->where('status', AiSupportIncident::STATUS_OPEN)
                ->latest('opened_at')->limit(20)->get(),
            'intentDomains' => $intentRecords->groupBy('domain')->map(fn ($records, string $domain): array => [
                'domain' => $domain,
                'total' => $records->count(),
                'mapped' => $records->filter(fn (array $record): bool => (array) $record['kb_stable_ids'] !== [])->count(),
                'pilot' => $records->where('rollout_state', 'pilot')->count(),
            ])->values(),
            'intentRecords' => $matchingIntentRecords->take(50),
            'matchingIntentCount' => $matchingIntentRecords->count(),
            'recentIntentOutcomes' => $recentOutcomes,
            'intentOutcomeCounts' => $outcomeCounts,
        ]);
    }
}
