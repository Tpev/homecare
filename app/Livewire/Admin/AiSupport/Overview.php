<?php

namespace App\Livewire\Admin\AiSupport;

use App\Models\AiSupportInteractionEvent;
use App\Models\AiSupportPilotGrant;
use App\Models\KnowledgeBaseEntry;
use App\Models\KnowledgeBaseVersion;
use App\Services\AiSupport\AiSupportControlService;
use App\Services\AiSupport\FamilyIntentCatalog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Overview extends Component
{
    public string $intentSearch = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->canViewAiSupportPilot(), 403);
    }

    public function render(AiSupportControlService $controls, FamilyIntentCatalog $intentCatalog): View
    {
        $now = now();
        $intentRecords = collect($intentCatalog->records());
        $search = mb_strtolower(trim($this->intentSearch));
        $visibleIntentRecords = $intentRecords
            ->when($search !== '', fn ($records) => $records->filter(fn (array $record): bool => str_contains(mb_strtolower(implode(' ', [
                $record['intent_id'], $record['domain'], $record['intent'], implode(' ', (array) $record['kb_stable_ids']),
            ])), $search)))
            ->values();
        $outcomeTypes = [
            'intent_unmatched', 'intent_looped', 'intent_failed', 'intent_verification_failed',
            'intent_recovery_offered', 'guided_action_recovery', 'intent_transferred', 'handoff_completed',
            'transferred_to_human',
        ];
        $recentOutcomes = AiSupportInteractionEvent::query()
            ->whereIn('event_type', $outcomeTypes)
            ->where('occurred_at', '>=', now()->subDays(30))
            ->latest('occurred_at')
            ->limit(500)
            ->get();

        $outcomeCounts = $recentOutcomes->countBy('event_type');
        $outcomeCounts->put(
            'intent_transferred',
            $recentOutcomes
                ->whereIn('event_type', ['intent_transferred', 'handoff_completed', 'transferred_to_human'])
                ->unique('support_ticket_id')
                ->count(),
        );

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
            'intentDomains' => $intentRecords->groupBy('domain')->map(fn ($records, string $domain): array => [
                'domain' => $domain,
                'total' => $records->count(),
                'mapped' => $records->filter(fn (array $record): bool => (array) $record['kb_stable_ids'] !== [])->count(),
                'pilot' => $records->where('rollout_state', 'pilot')->count(),
            ])->values(),
            'intentRecords' => $visibleIntentRecords,
            'recentIntentOutcomes' => $recentOutcomes->take(30),
            'intentOutcomeCounts' => $outcomeCounts,
        ]);
    }
}
