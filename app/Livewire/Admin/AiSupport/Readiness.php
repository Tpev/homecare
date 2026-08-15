<?php

namespace App\Livewire\Admin\AiSupport;

use App\Models\AiSupportIncident;
use App\Services\AiSupport\AiSupportControlService;
use App\Services\AiSupport\AiSupportIncidentService;
use App\Services\AiSupport\AiSupportReadinessService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Readiness extends Component
{
    public string $evidenceKey = 'provider_project_configuration';

    public string $evidenceStatus = 'pending';

    public string $evidenceSummary = '';

    public string $sourceReference = '';

    public string $evidenceObservedAt = '';

    public string $evidenceExpiresAt = '';

    public bool $contentFreeConfirmed = false;

    public string $resolutionReason = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->canManageAiSupportControls(), 403);
        $this->evidenceObservedAt = now()->format('Y-m-d');
    }

    public function recordEvidence(AiSupportReadinessService $readiness): void
    {
        $validated = $this->validate([
            'evidenceKey' => ['required', Rule::in(array_keys($readiness->definitions()))],
            'evidenceStatus' => ['required', Rule::in(['passed', 'failed', 'pending', 'deferred'])],
            'evidenceSummary' => ['required', 'string', 'min:5', 'max:500'],
            'sourceReference' => ['nullable', 'string', 'max:500'],
            'evidenceObservedAt' => ['required', 'date'],
            'evidenceExpiresAt' => ['nullable', 'date', 'after:evidenceObservedAt'],
            'contentFreeConfirmed' => ['accepted'],
        ]);

        $readiness->record(
            auth()->user(),
            $validated['evidenceKey'],
            $validated['evidenceStatus'],
            $validated['evidenceSummary'],
            $validated['sourceReference'] ?: null,
            CarbonImmutable::parse($validated['evidenceObservedAt'])->endOfDay(),
            $validated['evidenceExpiresAt'] ? CarbonImmutable::parse($validated['evidenceExpiresAt'])->endOfDay() : null,
        );

        $this->reset(['evidenceSummary', 'sourceReference', 'evidenceExpiresAt', 'contentFreeConfirmed']);
        $this->evidenceStatus = 'pending';
        $this->evidenceObservedAt = now()->format('Y-m-d');
        session()->flash('status', 'Readiness evidence version recorded. This did not change any runtime control or pilot grant.');
    }

    public function resolveIncident(string $incidentId, AiSupportIncidentService $incidents): void
    {
        $validated = $this->validate([
            'resolutionReason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $incident = AiSupportIncident::query()->findOrFail($incidentId);
        $incidents->resolve(auth()->user(), $incident, $validated['resolutionReason']);
        $this->reset('resolutionReason');
        session()->flash('status', 'Incident marked resolved. Disabled capabilities remain disabled until a separate audited control change.');
    }

    public function render(AiSupportReadinessService $readiness, AiSupportControlService $controls): View
    {
        return view('livewire.admin.ai-support.readiness', [
            'snapshot' => $readiness->snapshot($controls, AiSupportReadinessService::SCOPE_INITIAL_PILOT),
            'expansionSnapshot' => $readiness->snapshot($controls, AiSupportReadinessService::SCOPE_EXPANSION),
            'definitions' => $readiness->definitions(),
            'incidents' => AiSupportIncident::query()
                ->where('status', AiSupportIncident::STATUS_OPEN)
                ->latest('opened_at')
                ->limit(20)
                ->get(),
        ]);
    }
}
