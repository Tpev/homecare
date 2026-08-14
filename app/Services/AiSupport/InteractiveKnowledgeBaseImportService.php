<?php

namespace App\Services\AiSupport;

use App\Models\KnowledgeBaseEntry;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class InteractiveKnowledgeBaseImportService
{
    public function __construct(
        private readonly InteractiveKnowledgeBaseCatalog $catalog,
        private readonly KnowledgeBaseWorkflowService $workflow,
    ) {}

    /** @return array<string,mixed> */
    public function plan(): array
    {
        $existing = KnowledgeBaseEntry::query()
            ->whereIn('stable_id', InteractiveKnowledgeBaseCatalog::APPROVED_STABLE_IDS)
            ->get()->keyBy('stable_id');
        $creates = [];
        $noops = [];
        $conflicts = [];
        foreach ($this->catalog->entries() as $entry) {
            $current = $existing->get($entry['stable_id']);
            if (! $current) {
                $creates[] = $entry['stable_id'];
            } elseif ($current->deleted_at) {
                $conflicts[] = ['stable_id' => $entry['stable_id'], 'reason' => 'stable_id_is_deleted_or_tombstoned'];
            } else {
                // Once governed, an entry may legitimately differ from its seed manifest.
                $noops[] = $entry['stable_id'];
            }
        }

        return [
            'manifest_version' => InteractiveKnowledgeBaseCatalog::VERSION,
            'creates' => $creates,
            'noops' => $noops,
            'conflicts' => $conflicts,
            'counts' => ['creates' => count($creates), 'noops' => count($noops), 'conflicts' => count($conflicts)],
        ];
    }

    /** @return array<string,mixed> */
    public function apply(User $actor): array
    {
        if (! $actor->canManageKnowledgeBase()) {
            throw new DomainException('The import actor is not authorized to manage the knowledge base.');
        }

        return DB::transaction(function () use ($actor): array {
            $plan = $this->plan();
            if ($plan['conflicts'] !== []) {
                throw new DomainException('Interactive knowledge import refused because a stable ID is tombstoned.');
            }
            $publishedBefore = KnowledgeBaseEntry::query()->whereNotNull('published_version_id')->count();
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
                    throw new DomainException($stableId.' failed governed validation.');
                }
                $created[] = [
                    'stable_id' => $stableId,
                    'version_id' => $entry->workingVersion->id,
                    'version_number' => $entry->workingVersion->version_number,
                ];
            }
            $publishedAfter = KnowledgeBaseEntry::query()->whereNotNull('published_version_id')->count();
            if ($publishedAfter !== $publishedBefore) {
                throw new DomainException('Draft import changed published knowledge and was rolled back.');
            }

            return [
                'manifest_version' => InteractiveKnowledgeBaseCatalog::VERSION,
                'created' => $created,
                'noops' => $plan['noops'],
                'published_count_before' => $publishedBefore,
                'published_count_after' => $publishedAfter,
            ];
        }, 3);
    }
}
