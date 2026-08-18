<?php

namespace App\Services\AiSupport;

use App\Models\KnowledgeBaseEntry;
use App\Models\KnowledgeBaseVersion;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class PaymentTimeKnowledgeBaseImportService
{
    public function __construct(
        private readonly PaymentTimeKnowledgeBaseCatalog $catalog,
        private readonly KnowledgeBaseWorkflowService $workflow,
    ) {}

    /** @return array<string,mixed> */
    public function plan(): array
    {
        $creates = [];
        $noops = [];
        $conflicts = [];
        $existing = KnowledgeBaseEntry::query()
            ->with('workingVersion.sources')
            ->whereIn('stable_id', PaymentTimeKnowledgeBaseCatalog::APPROVED_STABLE_IDS)
            ->get()
            ->keyBy('stable_id');

        foreach ($this->catalog->entries() as $definition) {
            $stableId = (string) $definition['stable_id'];
            $entry = $existing->get($stableId);
            if (! $entry) {
                $creates[] = $stableId;
            } elseif ($entry->deleted_at || ! $entry->workingVersion) {
                $conflicts[] = ['stable_id' => $stableId, 'reason' => 'entry_is_deleted_or_missing_a_working_version'];
            } elseif ($this->matches($definition, $entry->workingVersion)) {
                $noops[] = $stableId;
            } else {
                $conflicts[] = ['stable_id' => $stableId, 'reason' => 'existing_content_differs_from_approved_manifest'];
            }
        }

        return [
            'manifest_version' => PaymentTimeKnowledgeBaseCatalog::VERSION,
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

    /** @return array<string,mixed> */
    public function apply(User $actor): array
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor): array {
            $plan = $this->plan();
            if ($plan['conflicts'] !== []) {
                throw new DomainException('Batch 4 KB import refused because governed content conflicts.');
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
                if (! $this->workflow->validateAndStore($actor, $entry->workingVersion)['passed']) {
                    throw new DomainException($stableId.' failed governed validation.');
                }
                $created[] = ['stable_id' => $stableId, 'version_number' => 1];
            }

            $publishedAfter = KnowledgeBaseEntry::query()->whereNotNull('published_version_id')->count();
            if ($publishedAfter !== $publishedBefore) {
                throw new DomainException('Draft import changed published knowledge and was rolled back.');
            }

            return [
                'manifest_version' => PaymentTimeKnowledgeBaseCatalog::VERSION,
                'created' => $created,
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

            foreach ($this->catalog->entries() as $definition) {
                $stableId = (string) $definition['stable_id'];
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

            return [...$applied, 'published' => $published, 'already_published' => $alreadyPublished];
        }, 3);
    }

    /** @param array<string,mixed> $definition */
    private function matches(array $definition, KnowledgeBaseVersion $version): bool
    {
        return hash_equals(
            $this->fingerprint($this->catalog->payload($definition), $this->catalog->sources($definition)),
            $this->fingerprint($this->versionPayload($version), $version->sources->map(fn ($source): array => [
                'source_id' => $source->source_id,
                'title' => $source->title,
                'url' => $source->url,
                'section_anchor' => $source->section_anchor,
                'fact_supported' => $source->fact_supported,
            ])->all()),
        );
    }

    /** @return array<string,mixed> */
    private function versionPayload(KnowledgeBaseVersion $version): array
    {
        $fields = [
            'type', 'title', 'answer_body', 'sensitivity', 'product_area', 'locale', 'roles',
            'membership_states', 'route_target_ids', 'capability_ids', 'facts_may_state',
            'facts_must_not_infer', 'next_actions', 'escalation_conditions',
            'retrieval_examples_match', 'retrieval_examples_no_match', 'evaluation_ids',
            'change_note', 'review_by', 'expires_on',
        ];

        return collect($fields)->mapWithKeys(function (string $field) use ($version): array {
            $value = $version->getAttribute($field);
            if (in_array($field, ['review_by', 'expires_on'], true)) {
                $value = $value?->format('Y-m-d');
            }

            return [$field => $value];
        })->all();
    }

    /** @param array<string,mixed> $payload @param list<array<string,mixed>> $sources */
    private function fingerprint(array $payload, array $sources): string
    {
        foreach ($payload as $field => $value) {
            if (is_array($value)) {
                $payload[$field] = array_values(array_unique(array_filter(array_map(
                    static fn (mixed $item): string => trim((string) $item),
                    $value,
                ))));
            } elseif (is_string($value)) {
                $payload[$field] = trim($value);
            }
        }
        $sources = array_values(array_map(static fn (array $source): array => [
            'source_id' => trim((string) ($source['source_id'] ?? '')),
            'title' => trim((string) ($source['title'] ?? '')),
            'url' => filled($source['url'] ?? null) ? trim((string) $source['url']) : null,
            'section_anchor' => filled($source['section_anchor'] ?? null) ? trim((string) $source['section_anchor']) : null,
            'fact_supported' => trim((string) ($source['fact_supported'] ?? '')),
        ], $sources));

        return hash('sha256', json_encode(
            ['payload' => $payload, 'sources' => $sources],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    private function authorize(User $actor): void
    {
        if (! $actor->canManageKnowledgeBase()) {
            throw new DomainException('The import actor is not authorized to manage the knowledge base.');
        }
    }
}
