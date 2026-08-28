<?php

namespace App\Services\AiSupport;

use App\Models\KnowledgeBaseEntry;
use App\Models\KnowledgeBaseVersion;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use JsonException;

class FamilyExperienceKnowledgeBaseImportService
{
    public function __construct(
        private readonly FamilyExperienceKnowledgeBaseCatalog $catalog,
        private readonly KnowledgeBaseWorkflowService $workflow,
    ) {}

    /** @return array<string,mixed> */
    public function plan(): array
    {
        $creates = $updates = $revisions = $noops = $conflicts = [];
        $definitions = collect($this->catalog->definitions())->keyBy('stable_id');
        $existing = KnowledgeBaseEntry::query()
            ->with(['workingVersion.sources', 'publishedVersion.sources'])
            ->whereIn('stable_id', FamilyExperienceKnowledgeBaseCatalog::STABLE_IDS)
            ->get()
            ->keyBy('stable_id');

        foreach (FamilyExperienceKnowledgeBaseCatalog::STABLE_IDS as $stableId) {
            $entry = $existing->get($stableId);
            if (! $entry) {
                $creates[] = $stableId;
            } elseif ($entry->deleted_at || ! $entry->workingVersion) {
                $conflicts[] = ['stable_id' => $stableId, 'reason' => 'source_is_missing_or_unavailable'];
            } elseif ($this->matches($definitions->get($stableId), $entry->workingVersion)) {
                $noops[] = $stableId;
            } elseif (! $entry->publishedVersion && $entry->workingVersion->status === KnowledgeBaseVersion::STATUS_DRAFT) {
                $updates[] = $stableId;
            } elseif ((int) $entry->working_version_id !== (int) $entry->published_version_id
                || $entry->workingVersion->status !== KnowledgeBaseVersion::STATUS_PUBLISHED) {
                $conflicts[] = ['stable_id' => $stableId, 'reason' => 'an_unpublished_admin_revision_already_exists'];
            } else {
                $revisions[] = $stableId;
            }
        }

        return [
            'manifest_version' => FamilyExperienceKnowledgeBaseCatalog::VERSION,
            'creates' => $creates,
            'updates' => $updates,
            'revisions' => $revisions,
            'noops' => $noops,
            'conflicts' => $conflicts,
            'counts' => ['creates' => count($creates), 'updates' => count($updates), 'revisions' => count($revisions), 'noops' => count($noops), 'conflicts' => count($conflicts)],
        ];
    }

    /** @return array<string,mixed> */
    public function apply(User $actor): array
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor): array {
            $plan = $this->plan();
            if ($plan['conflicts'] !== []) {
                throw new DomainException('Family experience KB realignment refused because source content is missing or already being edited.');
            }

            $publishedBefore = KnowledgeBaseEntry::query()->whereNotNull('published_version_id')->count();
            $created = [];
            foreach ($plan['creates'] as $stableId) {
                $definition = $this->catalog->definition($stableId);
                $entry = $this->workflow->createDraftWithStableId(
                    $actor,
                    $stableId,
                    $definition['payload'],
                    $definition['sources'],
                );
                if (! $this->workflow->validateAndStore($actor, $entry->workingVersion)['passed']) {
                    throw new DomainException($stableId.' failed knowledge validation.');
                }
                $created[] = ['stable_id' => $stableId, 'version_number' => 1];
            }
            $updated = [];
            foreach ($plan['updates'] as $stableId) {
                $definition = $this->catalog->definition($stableId);
                $entry = KnowledgeBaseEntry::query()->with('workingVersion')->where('stable_id', $stableId)->firstOrFail();
                $draft = $this->workflow->updateWorkingVersion(
                    $actor,
                    $entry->workingVersion,
                    $entry->workingVersion->edit_version,
                    $definition['payload'],
                    $definition['sources'],
                );
                if (! $this->workflow->validateAndStore($actor, $draft)['passed']) {
                    throw new DomainException($stableId.' failed knowledge validation.');
                }
                $updated[] = ['stable_id' => $stableId, 'version_number' => (int) $draft->version_number];
            }
            $revised = [];
            foreach ($plan['revisions'] as $stableId) {
                $definition = $this->catalog->definition($stableId);
                $entry = KnowledgeBaseEntry::query()->with('publishedVersion')->where('stable_id', $stableId)->firstOrFail();
                $draft = $this->workflow->createDraftFrom(
                    $actor,
                    $entry->publishedVersion,
                    'Realign Family guidance with the current Care workspace, navigation, pricing, and recurring-care terminology.',
                );
                $draft = $this->workflow->updateWorkingVersion(
                    $actor,
                    $draft,
                    $draft->edit_version,
                    $definition['payload'],
                    $definition['sources'],
                );
                if (! $this->workflow->validateAndStore($actor, $draft)['passed']) {
                    throw new DomainException($stableId.' failed knowledge validation.');
                }
                $revised[] = ['stable_id' => $stableId, 'version_number' => (int) $draft->version_number];
            }

            if (KnowledgeBaseEntry::query()->whereNotNull('published_version_id')->count() !== $publishedBefore) {
                throw new DomainException('Draft realignment changed published knowledge and was rolled back.');
            }

            return [
                'manifest_version' => FamilyExperienceKnowledgeBaseCatalog::VERSION,
                'created' => $created,
                'updated' => $updated,
                'revised' => $revised,
                'noops' => $plan['noops'],
            ];
        }, 3);
    }

    /** @return array<string,mixed> */
    public function publishPackage(User $actor, string $reason): array
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $reason): array {
            $applied = $this->apply($actor);
            $published = $alreadyPublished = [];
            foreach ($this->catalog->definitions() as $definition) {
                $stableId = $definition['stable_id'];
                $entry = KnowledgeBaseEntry::query()->with('workingVersion.sources')->where('stable_id', $stableId)->firstOrFail();
                $version = $entry->workingVersion;
                if (! $version || ! $this->matches($definition, $version)) {
                    throw new DomainException($stableId.' no longer matches the alignment package.');
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

            return [...$applied, 'published' => $published, 'already_published' => $alreadyPublished];
        }, 3);
    }

    /** @param array{payload:array<string,mixed>,sources:list<array<string,mixed>>} $definition */
    private function matches(array $definition, KnowledgeBaseVersion $version): bool
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
        $sources = $version->sources->map(fn ($source): array => [
            'source_id' => $source->source_id,
            'title' => $source->title,
            'url' => $source->url,
            'section_anchor' => $source->section_anchor,
            'fact_supported' => $source->fact_supported,
        ])->all();

        return hash_equals(
            $this->fingerprint($definition['payload'], $definition['sources']),
            $this->fingerprint($payload, $sources),
        );
    }

    /** @param array<string,mixed> $payload @param list<array<string,mixed>> $sources */
    private function fingerprint(array $payload, array $sources): string
    {
        foreach ($payload as $field => $value) {
            $payload[$field] = is_array($value)
                ? array_values(array_unique(array_filter(array_map(static fn (mixed $item): string => trim((string) $item), $value))))
                : (is_string($value) ? trim($value) : $value);
        }
        $sources = array_values(array_map(static fn (array $source): array => [
            'source_id' => trim((string) ($source['source_id'] ?? '')),
            'title' => trim((string) ($source['title'] ?? '')),
            'url' => filled($source['url'] ?? null) ? trim((string) $source['url']) : null,
            'section_anchor' => filled($source['section_anchor'] ?? null) ? trim((string) $source['section_anchor']) : null,
            'fact_supported' => trim((string) ($source['fact_supported'] ?? '')),
        ], $sources));

        try {
            return hash('sha256', json_encode(['payload' => $payload, 'sources' => $sources], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } catch (JsonException $exception) {
            throw new DomainException('Family experience knowledge could not be fingerprinted.', previous: $exception);
        }
    }

    private function authorize(User $actor): void
    {
        if (! $actor->canManageKnowledgeBase()) {
            throw new DomainException('The knowledge actor is not authorized.');
        }
    }
}
