<?php

namespace App\Services\AiSupport;

use App\Models\KnowledgeBaseEntry;
use App\Models\KnowledgeBaseVersion;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use JsonException;

class FamilyOperationsKnowledgeBaseImportService
{
    public function __construct(
        private readonly FamilyOperationsKnowledgeBaseCatalog $catalog,
        private readonly KnowledgeBaseWorkflowService $workflow,
    ) {}

    /** @return array<string,mixed> */
    public function plan(): array
    {
        $creates = [];
        $revisions = [];
        $noops = [];
        $conflicts = [];
        $definitions = collect($this->catalog->allDefinitions())->keyBy('stable_id');
        $stableIds = $definitions->keys()->all();
        $existing = KnowledgeBaseEntry::query()
            ->with(['workingVersion.sources', 'publishedVersion.sources'])
            ->whereIn('stable_id', $stableIds)
            ->get()
            ->keyBy('stable_id');

        foreach (FamilyOperationsKnowledgeBaseCatalog::APPROVED_STABLE_IDS as $stableId) {
            $entry = $existing->get($stableId);
            if (! $entry) {
                $creates[] = $stableId;
            } elseif ($entry->deleted_at) {
                $conflicts[] = ['stable_id' => $stableId, 'reason' => 'stable_id_is_deleted_or_tombstoned'];
            } elseif (! $entry->workingVersion) {
                $conflicts[] = ['stable_id' => $stableId, 'reason' => 'working_version_is_missing'];
            } elseif ($this->matches($definitions->get($stableId), $entry->workingVersion)) {
                $noops[] = $stableId;
            } else {
                $conflicts[] = ['stable_id' => $stableId, 'reason' => 'existing_content_differs_from_manifest'];
            }
        }

        foreach (FamilyOperationsKnowledgeBaseCatalog::REVISION_STABLE_IDS as $stableId) {
            $entry = $existing->get($stableId);
            if (! $entry) {
                $conflicts[] = ['stable_id' => $stableId, 'reason' => 'revision_source_is_missing'];
            } elseif ($entry->deleted_at || ! $entry->workingVersion) {
                $conflicts[] = ['stable_id' => $stableId, 'reason' => 'revision_source_is_unavailable'];
            } elseif ($this->matches($definitions->get($stableId), $entry->workingVersion)) {
                $noops[] = $stableId;
            } elseif ((int) $entry->working_version_id !== (int) $entry->published_version_id
                || $entry->workingVersion->status !== KnowledgeBaseVersion::STATUS_PUBLISHED) {
                $conflicts[] = ['stable_id' => $stableId, 'reason' => 'active_revision_differs_from_manifest'];
            } else {
                $revisions[] = $stableId;
            }
        }

        return [
            'manifest_version' => FamilyOperationsKnowledgeBaseCatalog::VERSION,
            'creates' => $creates,
            'revisions' => $revisions,
            'noops' => $noops,
            'conflicts' => $conflicts,
            'counts' => [
                'creates' => count($creates),
                'revisions' => count($revisions),
                'noops' => count($noops),
                'conflicts' => count($conflicts),
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function apply(User $actor): array
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor): array {
            $plan = $this->plan();
            if ($plan['conflicts'] !== []) {
                throw new DomainException('Family operations KB import refused because governed content conflicts.');
            }

            $publishedBefore = KnowledgeBaseEntry::query()->whereNotNull('published_version_id')->count();
            $created = [];
            foreach ($plan['creates'] as $stableId) {
                $definition = $this->catalog->definition($stableId);
                $entry = $this->workflow->createDraftWithStableId(
                    $actor,
                    $stableId,
                    $this->catalog->payload($definition),
                    $this->catalog->sources($definition),
                );
                $this->requireValid($actor, $entry->workingVersion, $stableId);
                $created[] = ['stable_id' => $stableId, 'version_number' => 1];
            }

            $revised = [];
            foreach ($plan['revisions'] as $stableId) {
                $definition = $this->catalog->definition($stableId);
                $entry = KnowledgeBaseEntry::query()->with('publishedVersion')->where('stable_id', $stableId)->firstOrFail();
                $draft = $this->workflow->createDraftFrom(
                    $actor,
                    $entry->publishedVersion,
                    'Correct Family payment-method permissions and link the Family operations intent coverage.',
                );
                $draft = $this->workflow->updateWorkingVersion(
                    $actor,
                    $draft,
                    $draft->edit_version,
                    $this->catalog->payload($definition),
                    $this->catalog->sources($definition),
                );
                $this->requireValid($actor, $draft, $stableId);
                $revised[] = ['stable_id' => $stableId, 'version_number' => (int) $draft->version_number];
            }

            $publishedAfter = KnowledgeBaseEntry::query()->whereNotNull('published_version_id')->count();
            if ($publishedAfter !== $publishedBefore) {
                throw new DomainException('Draft import changed published knowledge and was rolled back.');
            }

            return [
                'manifest_version' => FamilyOperationsKnowledgeBaseCatalog::VERSION,
                'created' => $created,
                'revised' => $revised,
                'noops' => $plan['noops'],
                'published_count_before' => $publishedBefore,
                'published_count_after' => $publishedAfter,
            ];
        }, 3);
    }

    /** @return array<string,mixed> */
    public function publishPackage(User $actor, string $reason): array
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $reason): array {
            $applied = $this->apply($actor);
            $published = [];
            $alreadyPublished = [];

            foreach ($this->catalog->allDefinitions() as $definition) {
                $stableId = $definition['stable_id'];
                $entry = KnowledgeBaseEntry::query()->with('workingVersion.sources')->where('stable_id', $stableId)->firstOrFail();
                $version = $entry->workingVersion;
                if (! $version || ! $this->matches($definition, $version)) {
                    throw new DomainException($stableId.' no longer matches the approved package.');
                }
                if ($version->status === KnowledgeBaseVersion::STATUS_PUBLISHED
                    && (int) $entry->published_version_id === (int) $version->id) {
                    $alreadyPublished[] = $stableId;

                    continue;
                }
                if ($version->status === KnowledgeBaseVersion::STATUS_DRAFT) {
                    $version = $this->workflow->submitForReview($actor, $version);
                }
                if ($version->status === KnowledgeBaseVersion::STATUS_IN_REVIEW) {
                    $version = $this->workflow->approve($actor, $version);
                }
                if ($version->status !== KnowledgeBaseVersion::STATUS_APPROVED) {
                    throw new DomainException($stableId.' cannot be published from '.$version->status.'.');
                }
                $version = $this->workflow->publish($actor, $version, $reason);
                $published[] = ['stable_id' => $stableId, 'version_number' => (int) $version->version_number];
            }

            return [
                ...$applied,
                'published' => $published,
                'already_published' => $alreadyPublished,
            ];
        }, 3);
    }

    private function requireValid(User $actor, KnowledgeBaseVersion $version, string $stableId): void
    {
        if (! $this->workflow->validateAndStore($actor, $version)['passed']) {
            throw new DomainException($stableId.' failed governed validation.');
        }
    }

    /** @param array<string,mixed> $definition */
    private function matches(array $definition, KnowledgeBaseVersion $version): bool
    {
        return hash_equals($this->definitionFingerprint($definition), $this->versionFingerprint($version));
    }

    /** @param array<string,mixed> $definition */
    private function definitionFingerprint(array $definition): string
    {
        return $this->fingerprint([
            'payload' => $this->normalize($this->catalog->payload($definition)),
            'sources' => $this->normalizeSources($this->catalog->sources($definition)),
        ]);
    }

    private function versionFingerprint(KnowledgeBaseVersion $version): string
    {
        $fields = [
            'type', 'title', 'answer_body', 'sensitivity', 'product_area', 'locale', 'roles',
            'membership_states', 'route_target_ids', 'capability_ids', 'facts_may_state',
            'facts_must_not_infer', 'next_actions', 'escalation_conditions',
            'retrieval_examples_match', 'retrieval_examples_no_match', 'evaluation_ids',
            'change_note', 'review_by', 'expires_on',
        ];
        $payload = collect($fields)->mapWithKeys(function (string $field) use ($version): array {
            $value = $version->getAttribute($field);
            if (in_array($field, ['review_by', 'expires_on'], true)) {
                $value = $value?->format('Y-m-d');
            }

            return [$field => $value];
        })->all();

        return $this->fingerprint([
            'payload' => $this->normalize($payload),
            'sources' => $this->normalizeSources($version->sources->map(fn ($source): array => [
                'source_id' => $source->source_id,
                'title' => $source->title,
                'url' => $source->url,
                'section_anchor' => $source->section_anchor,
                'fact_supported' => $source->fact_supported,
            ])->all()),
        ]);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function normalize(array $payload): array
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
    private function normalizeSources(array $sources): array
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
            throw new DomainException('Family operations KB content could not be fingerprinted.', previous: $exception);
        }
    }

    private function authorize(User $actor): void
    {
        if (! $actor->canManageKnowledgeBase()) {
            throw new DomainException('The import actor is not authorized to manage the knowledge base.');
        }
    }
}
