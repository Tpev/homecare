<?php

namespace App\Livewire\Admin\AiSupport;

use App\Models\KnowledgeBaseEntry;
use App\Models\KnowledgeBaseVersion;
use App\Services\AiSupport\KnowledgeBaseWorkflowService;
use App\Services\AiSupport\NavigationTargetRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class KnowledgeEditor extends Component
{
    #[Locked]
    public ?int $entryId = null;

    #[Locked]
    public ?int $versionId = null;

    #[Locked]
    public int $expectedEditVersion = 1;

    public string $type = 'product_fact';

    public string $title = '';

    public string $answerBody = '';

    public string $sensitivity = 'authenticated';

    public string $productArea = 'support';

    public string $locale = 'en-US';

    public array $roles = ['family'];

    public string $membershipStatesText = '';

    public array $routeTargetIds = [];

    public string $capabilityIdsText = 'support_answers_v1';

    public string $factsMayStateText = '';

    public string $factsMustNotInferText = '';

    public string $nextActionsText = '';

    public string $escalationConditionsText = '';

    public string $retrievalMatchText = '';

    public string $retrievalNoMatchText = '';

    public string $evaluationIdsText = '';

    public string $changeNote = '';

    public string $reviewBy = '';

    public string $expiresOn = '';

    public array $sources = [];

    public string $lifecycleReason = '';

    public string $confirmationText = '';

    public string $newVersionChangeNote = '';

    public function mount(?KnowledgeBaseEntry $entry = null): void
    {
        abort_unless(auth()->user()?->canManageKnowledgeBase(), 403);
        $this->reviewBy = CarbonImmutable::now()->addMonths(3)->format('Y-m-d');
        $this->sources = [$this->emptySource()];

        if ($entry?->exists) {
            $entry->load(['workingVersion.sources']);
            abort_if($entry->deleted_at, 410);
            $version = $entry->workingVersion;
            abort_unless($version, 404);
            $this->entryId = $entry->id;
            $this->fillFromVersion($version);
        }
    }

    public function addSource(): void
    {
        $this->sources[] = $this->emptySource();
    }

    public function removeSource(int $index): void
    {
        unset($this->sources[$index]);
        $this->sources = array_values($this->sources);
        if ($this->sources === []) {
            $this->sources[] = $this->emptySource();
        }
    }

    public function save(KnowledgeBaseWorkflowService $workflow): void
    {
        $this->validateEditor();
        $payload = $this->payload();

        if (! $this->entryId) {
            $entry = $workflow->createDraft(auth()->user(), $payload, $this->sources);
            $this->redirectRoute('admin.ai-support.knowledge.edit', $entry, navigate: true);

            return;
        }

        $version = $workflow->updateWorkingVersion(
            auth()->user(),
            $this->version,
            $this->expectedEditVersion,
            $payload,
            $this->sources,
        );
        $this->fillFromVersion($version);
        $this->resetValidation();
        session()->flash('status', 'Knowledge draft saved. Published content, if any, is unchanged.');
    }

    public function runValidation(KnowledgeBaseWorkflowService $workflow): void
    {
        $this->requirePersistedVersion();
        $result = $workflow->validateAndStore(auth()->user(), $this->version);
        $this->fillFromVersion($this->version->fresh('sources'));
        session()->flash('status', $result['passed'] ? 'Validation passed.' : 'Validation found blocking fields.');
    }

    public function submitForReview(KnowledgeBaseWorkflowService $workflow): void
    {
        $version = $workflow->submitForReview(auth()->user(), $this->version);
        $this->fillFromVersion($version);
        session()->flash('status', 'Knowledge version submitted for review.');
    }

    public function approve(KnowledgeBaseWorkflowService $workflow): void
    {
        $version = $workflow->approve(auth()->user(), $this->version);
        $this->fillFromVersion($version);
        session()->flash('status', 'Knowledge version self-reviewed and approved.');
    }

    public function publish(KnowledgeBaseWorkflowService $workflow): void
    {
        $this->validateLifecycleConfirmation('PUBLISH');
        $version = $workflow->publish(auth()->user(), $this->version, $this->lifecycleReason);
        $this->fillFromVersion($version);
        $this->resetLifecycle();
        session()->flash('status', 'Knowledge version published now. It is the only version eligible for retrieval.');
    }

    public function pause(KnowledgeBaseWorkflowService $workflow): void
    {
        $this->validateLifecycleConfirmation('PAUSE');
        $this->fillFromVersion($workflow->pause(auth()->user(), $this->version, $this->lifecycleReason));
        $this->resetLifecycle();
        session()->flash('status', 'Knowledge retrieval paused immediately.');
    }

    public function resume(KnowledgeBaseWorkflowService $workflow): void
    {
        $this->validateLifecycleConfirmation('RESUME');
        $this->fillFromVersion($workflow->resume(auth()->user(), $this->version, $this->lifecycleReason));
        $this->resetLifecycle();
        session()->flash('status', 'Knowledge version returned to published retrieval.');
    }

    public function withdraw(KnowledgeBaseWorkflowService $workflow): void
    {
        $this->validateLifecycleConfirmation('WITHDRAW');
        $this->fillFromVersion($workflow->withdraw(auth()->user(), $this->version, $this->lifecycleReason));
        $this->resetLifecycle();
        session()->flash('status', 'Knowledge version withdrawn from retrieval.');
    }

    public function createNewVersion(KnowledgeBaseWorkflowService $workflow): void
    {
        $this->validate(['newVersionChangeNote' => ['required', 'string', 'min:5', 'max:500']]);
        $draft = $workflow->createDraftFrom(auth()->user(), $this->version, $this->newVersionChangeNote);
        $this->fillFromVersion($draft);
        $this->reset('newVersionChangeNote');
        session()->flash('status', 'New draft version created. The released version remains unchanged until this draft is published.');
    }

    public function deleteEntry(KnowledgeBaseWorkflowService $workflow): void
    {
        $this->validateLifecycleConfirmation((string) $this->entry?->stable_id);
        $workflow->delete(auth()->user(), $this->version, $this->lifecycleReason);
        $this->redirectRoute('admin.ai-support.knowledge.index', navigate: true);
    }

    public function getEntryProperty(): ?KnowledgeBaseEntry
    {
        return $this->entryId
            ? KnowledgeBaseEntry::query()->with(['versions.author', 'versions.sources', 'publishedVersion'])->findOrFail($this->entryId)
            : null;
    }

    public function getVersionProperty(): KnowledgeBaseVersion
    {
        abort_unless($this->versionId, 404);

        return KnowledgeBaseVersion::query()->with(['entry', 'sources', 'dependencies'])->findOrFail($this->versionId);
    }

    public function render(NavigationTargetRegistry $navigation): View
    {
        return view('livewire.admin.ai-support.knowledge-editor', [
            'entry' => $this->entry,
            'version' => $this->versionId ? $this->version : null,
            'navigationTargets' => collect($navigation->ids()),
        ]);
    }

    private function validateEditor(): void
    {
        $this->validate([
            'type' => ['required', Rule::in(['product_fact', 'task_playbook', 'navigation', 'escalation'])],
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'answerBody' => ['required', 'string', 'min:10', 'max:50000'],
            'sensitivity' => ['required', Rule::in(['public', 'authenticated', 'shared_care', 'owner_only', 'restricted'])],
            'productArea' => ['required', 'string', 'min:2', 'max:120'],
            'locale' => ['required', 'string', 'max:16'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in(['family', 'caregiver'])],
            'changeNote' => ['required', 'string', 'min:5', 'max:500'],
            'reviewBy' => ['required', 'date'],
            'expiresOn' => ['nullable', 'date'],
            'sources' => ['required', 'array', 'min:1', 'max:20'],
            'sources.*.source_id' => ['nullable', 'string', 'max:120'],
            'sources.*.title' => ['nullable', 'string', 'max:255'],
            'sources.*.url' => ['nullable', 'url', 'max:2048'],
            'sources.*.section_anchor' => ['nullable', 'string', 'max:255'],
            'sources.*.fact_supported' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'answer_body' => $this->answerBody,
            'sensitivity' => $this->sensitivity,
            'product_area' => $this->productArea,
            'locale' => $this->locale,
            'roles' => $this->roles,
            'membership_states' => $this->lines($this->membershipStatesText),
            'route_target_ids' => $this->routeTargetIds,
            'capability_ids' => $this->lines($this->capabilityIdsText),
            'facts_may_state' => $this->lines($this->factsMayStateText),
            'facts_must_not_infer' => $this->lines($this->factsMustNotInferText),
            'next_actions' => $this->lines($this->nextActionsText),
            'escalation_conditions' => $this->lines($this->escalationConditionsText),
            'retrieval_examples_match' => $this->lines($this->retrievalMatchText),
            'retrieval_examples_no_match' => $this->lines($this->retrievalNoMatchText),
            'evaluation_ids' => $this->lines($this->evaluationIdsText),
            'change_note' => $this->changeNote,
            'review_by' => $this->reviewBy,
            'expires_on' => $this->expiresOn ?: null,
        ];
    }

    private function fillFromVersion(KnowledgeBaseVersion $version): void
    {
        $version->loadMissing('sources');
        $this->versionId = $version->id;
        $this->entryId = $version->knowledge_base_entry_id;
        $this->expectedEditVersion = $version->edit_version;
        $this->type = $version->type;
        $this->title = $version->title;
        $this->answerBody = $version->answer_body;
        $this->sensitivity = $version->sensitivity;
        $this->productArea = $version->product_area;
        $this->locale = $version->locale;
        $this->roles = (array) $version->roles;
        $this->membershipStatesText = $this->lineText($version->membership_states);
        $this->routeTargetIds = (array) $version->route_target_ids;
        $this->capabilityIdsText = $this->lineText($version->capability_ids);
        $this->factsMayStateText = $this->lineText($version->facts_may_state);
        $this->factsMustNotInferText = $this->lineText($version->facts_must_not_infer);
        $this->nextActionsText = $this->lineText($version->next_actions);
        $this->escalationConditionsText = $this->lineText($version->escalation_conditions);
        $this->retrievalMatchText = $this->lineText($version->retrieval_examples_match);
        $this->retrievalNoMatchText = $this->lineText($version->retrieval_examples_no_match);
        $this->evaluationIdsText = $this->lineText($version->evaluation_ids);
        $this->changeNote = $version->change_note;
        $this->reviewBy = $version->review_by?->format('Y-m-d') ?? '';
        $this->expiresOn = $version->expires_on?->format('Y-m-d') ?? '';
        $this->sources = $version->sources->map(fn ($source): array => [
            'source_id' => $source->source_id,
            'title' => $source->title,
            'url' => $source->url ?? '',
            'section_anchor' => $source->section_anchor ?? '',
            'fact_supported' => $source->fact_supported,
        ])->values()->all() ?: [$this->emptySource()];
    }

    private function validateLifecycleConfirmation(string $expected): void
    {
        $this->validate([
            'lifecycleReason' => ['required', 'string', 'min:5', 'max:500'],
            'confirmationText' => ['required', Rule::in([$expected])],
        ]);
    }

    private function resetLifecycle(): void
    {
        $this->reset(['lifecycleReason', 'confirmationText']);
    }

    private function requirePersistedVersion(): void
    {
        abort_unless($this->versionId, 404);
    }

    /** @return list<string> */
    private function lines(string $value): array
    {
        return collect(preg_split('/\R/u', $value) ?: [])->map(fn (string $line): string => trim($line))->filter()->unique()->values()->all();
    }

    private function lineText(mixed $value): string
    {
        return implode("\n", (array) $value);
    }

    /** @return array<string, string> */
    private function emptySource(): array
    {
        return ['source_id' => '', 'title' => '', 'url' => '', 'section_anchor' => '', 'fact_supported' => ''];
    }
}
