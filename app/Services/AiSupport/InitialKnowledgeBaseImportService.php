<?php

namespace App\Services\AiSupport;

use App\Models\KnowledgeBaseEntry;
use App\Models\KnowledgeBaseVersion;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use JsonException;

class InitialKnowledgeBaseImportService
{
    public function __construct(
        private readonly InitialKnowledgeBaseCatalog $catalog,
        private readonly KnowledgeBaseWorkflowService $workflow,
    ) {}

    /**
     * @return array{
     *   manifest_version:string,
     *   creates:list<string>,
     *   noops:list<string>,
     *   conflicts:list<array{stable_id:string,reason:string}>,
     *   counts:array{creates:int,noops:int,conflicts:int}
     * }
     */
    public function plan(): array
    {
        $creates = [];
        $noops = [];
        $conflicts = [];

        $existing = KnowledgeBaseEntry::query()
            ->with(['workingVersion.sources'])
            ->whereIn('stable_id', InitialKnowledgeBaseCatalog::APPROVED_STABLE_IDS)
            ->get()
            ->keyBy('stable_id');

        foreach ($this->catalog->entries() as $definition) {
            $stableId = $definition['stable_id'];
            /** @var KnowledgeBaseEntry|null $entry */
            $entry = $existing->get($stableId);
            if (! $entry) {
                $creates[] = $stableId;

                continue;
            }

            if ($entry->deleted_at) {
                $conflicts[] = ['stable_id' => $stableId, 'reason' => 'stable_id_is_deleted_or_tombstoned'];

                continue;
            }

            $working = $entry->workingVersion;
            if (! $working) {
                $conflicts[] = ['stable_id' => $stableId, 'reason' => 'working_version_is_missing'];

                continue;
            }

            if ($working->status !== KnowledgeBaseVersion::STATUS_DRAFT) {
                $conflicts[] = ['stable_id' => $stableId, 'reason' => 'working_version_is_not_draft'];

                continue;
            }

            if (! hash_equals($this->definitionFingerprint($definition), $this->versionFingerprint($working))) {
                $conflicts[] = ['stable_id' => $stableId, 'reason' => 'existing_draft_differs_from_manifest'];

                continue;
            }

            $noops[] = $stableId;
        }

        return [
            'manifest_version' => InitialKnowledgeBaseCatalog::VERSION,
            'creates' => $creates,
            'noops' => $noops,
            'conflicts' => $conflicts,
            'counts' => [
                'creates' => count($creates),
                'noops' => count($noops),
                'conflicts' => count($conflicts),
            ],
        ];
    }

    /**
     * @return array{
     *   manifest_version:string,
     *   created:list<array{stable_id:string,version_id:int,version_number:int}>,
     *   noops:list<string>,
     *   published_count_before:int,
     *   published_count_after:int
     * }
     */
    public function apply(User $actor): array
    {
        if (! $actor->canManageKnowledgeBase()) {
            throw new DomainException('The import actor is not authorized to manage the knowledge base.');
        }

        $publishedBefore = KnowledgeBaseEntry::query()->whereNotNull('published_version_id')->count();

        return DB::transaction(function () use ($actor, $publishedBefore): array {
            $plan = $this->plan();
            if ($plan['conflicts'] !== []) {
                throw new DomainException('Initial knowledge import refused because one or more stable IDs conflict.');
            }

            $definitions = collect($this->catalog->entries())->keyBy('stable_id');
            $created = [];
            foreach ($plan['creates'] as $stableId) {
                $definition = $definitions->get($stableId);
                $entry = $this->workflow->createDraftWithStableId(
                    $actor,
                    $stableId,
                    $this->catalog->payload($definition),
                    $this->catalog->sources($definition),
                );
                $validation = $this->workflow->validateAndStore($actor, $entry->workingVersion);
                if (! $validation['passed']) {
                    throw new DomainException($stableId.' failed governed KB validation during import.');
                }
                $version = $entry->workingVersion->fresh();
                $created[] = [
                    'stable_id' => $stableId,
                    'version_id' => (int) $version->id,
                    'version_number' => (int) $version->version_number,
                ];
            }

            $publishedAfter = KnowledgeBaseEntry::query()->whereNotNull('published_version_id')->count();
            if ($publishedAfter !== $publishedBefore) {
                throw new DomainException('Draft import changed the published knowledge count and was rolled back.');
            }

            return [
                'manifest_version' => InitialKnowledgeBaseCatalog::VERSION,
                'created' => $created,
                'noops' => $plan['noops'],
                'published_count_before' => $publishedBefore,
                'published_count_after' => $publishedAfter,
            ];
        }, 3);
    }

    /** @param array<string,mixed> $definition */
    private function definitionFingerprint(array $definition): string
    {
        return $this->fingerprint([
            'payload' => $this->normalizedPayload($this->catalog->payload($definition)),
            'sources' => $this->normalizedSources($this->catalog->sources($definition)),
        ]);
    }

    private function versionFingerprint(KnowledgeBaseVersion $version): string
    {
        return $this->fingerprint([
            'payload' => $this->normalizedPayload([
                'type' => $version->type,
                'title' => $version->title,
                'answer_body' => $version->answer_body,
                'sensitivity' => $version->sensitivity,
                'product_area' => $version->product_area,
                'locale' => $version->locale,
                'roles' => $version->roles,
                'membership_states' => $version->membership_states,
                'route_target_ids' => $version->route_target_ids,
                'capability_ids' => $version->capability_ids,
                'facts_may_state' => $version->facts_may_state,
                'facts_must_not_infer' => $version->facts_must_not_infer,
                'next_actions' => $version->next_actions,
                'escalation_conditions' => $version->escalation_conditions,
                'retrieval_examples_match' => $version->retrieval_examples_match,
                'retrieval_examples_no_match' => $version->retrieval_examples_no_match,
                'evaluation_ids' => $version->evaluation_ids,
                'change_note' => $version->change_note,
                'review_by' => $version->review_by?->format('Y-m-d'),
                'expires_on' => $version->expires_on?->format('Y-m-d'),
            ]),
            'sources' => $this->normalizedSources($version->sources->map(fn ($source): array => [
                'source_id' => $source->source_id,
                'title' => $source->title,
                'url' => $source->url,
                'section_anchor' => $source->section_anchor,
                'fact_supported' => $source->fact_supported,
            ])->all()),
        ]);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function normalizedPayload(array $payload): array
    {
        foreach ($payload as $field => $value) {
            if (is_array($value)) {
                $payload[$field] = array_values(array_unique(array_filter(array_map(
                    fn (mixed $item): string => trim((string) $item),
                    $value,
                ))));
            } elseif (is_string($value)) {
                $payload[$field] = trim($value);
            }
        }

        return $payload;
    }

    /** @param list<array<string,mixed>> $sources @return list<array<string,mixed>> */
    private function normalizedSources(array $sources): array
    {
        return array_values(array_map(fn (array $source): array => [
            'source_id' => trim((string) ($source['source_id'] ?? '')),
            'title' => trim((string) ($source['title'] ?? '')),
            'url' => filled($source['url'] ?? null) ? trim((string) $source['url']) : null,
            'section_anchor' => filled($source['section_anchor'] ?? null) ? trim((string) $source['section_anchor']) : null,
            'fact_supported' => trim((string) ($source['fact_supported'] ?? '')),
        ], $sources));
    }

    /** @param array<string,mixed> $value */
    private function fingerprint(array $value): string
    {
        try {
            return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } catch (JsonException $exception) {
            throw new DomainException('Initial knowledge content could not be fingerprinted.', previous: $exception);
        }
    }
}
