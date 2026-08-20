<?php

namespace App\Services\AiSupport;

use DomainException;

class MarketplaceCareKnowledgeBaseCatalog
{
    public const VERSION = 'marketplace-care-kb-v1';

    public const APPROVED_STABLE_IDS = [
        'KB-B67-MATCH-001', 'KB-B67-MATCH-002', 'KB-B67-MATCH-003',
        'KB-B67-MATCH-004', 'KB-B67-MATCH-005', 'KB-B67-MATCH-006',
        'KB-B67-MATCH-007', 'KB-B67-MATCH-008', 'KB-B67-MATCH-009',
        'KB-B67-VISIT-001', 'KB-B67-VISIT-002', 'KB-B67-VISIT-003',
        'KB-B67-VISIT-004', 'KB-B67-VISIT-005', 'KB-B67-VISIT-006',
        'KB-B67-VISIT-007', 'KB-B67-VISIT-008', 'KB-B67-VISIT-009',
        'KB-B67-VISIT-010', 'KB-B67-VISIT-011', 'KB-B67-VISIT-012',
        'KB-B67-VISIT-013', 'KB-B67-VISIT-014',
        'KB-B67-REGULAR-001', 'KB-B67-REGULAR-002', 'KB-B67-REGULAR-003',
        'KB-B67-REGULAR-004', 'KB-B67-REGULAR-005', 'KB-B67-REGULAR-006',
        'KB-B67-REGULAR-007', 'KB-B67-REGULAR-008', 'KB-B67-REGULAR-009',
    ];

    private ?array $manifest = null;

    public function __construct(private readonly NavigationTargetRegistry $navigation) {}

    /** @return array{version:string,approved_at:string,entries:list<array<string,mixed>>} */
    public function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $manifest = require resource_path('ai-support/knowledge-base/marketplace-care-v1.php');
        $entries = array_values((array) ($manifest['entries'] ?? []));
        if (($manifest['version'] ?? null) !== self::VERSION
            || array_column($entries, 'stable_id') !== self::APPROVED_STABLE_IDS) {
            throw new DomainException('Batches 6 and 7 knowledge inventory is invalid.');
        }

        $mapped = [];
        foreach ($entries as $definition) {
            $stableId = (string) ($definition['stable_id'] ?? 'unknown');
            foreach (['title', 'answer_body', 'product_area', 'review_by'] as $field) {
                if (trim((string) ($definition[$field] ?? '')) === '') {
                    throw new DomainException($stableId.' is missing '.$field.'.');
                }
            }
            if (($definition['roles'] ?? null) !== ['family']
                || ($definition['membership_states'] ?? null) !== ['active']
                || (array) ($definition['sources'] ?? []) === []
                || (array) ($definition['intent_ids'] ?? []) === []
                || count((array) ($definition['evaluation_ids'] ?? [])) !== 5) {
                throw new DomainException($stableId.' has an invalid role, source, intent, or evaluation contract.');
            }
            foreach ((array) $definition['intent_ids'] as $intentId) {
                if (! preg_match('/^FAM-(?:MATCH|VISIT|REGULAR)-[0-9]{3}$/', (string) $intentId)) {
                    throw new DomainException($stableId.' contains an invalid Batches 6 and 7 intent ID.');
                }
                $mapped[(string) $intentId] = true;
            }
            foreach ((array) $definition['route_target_ids'] as $targetId) {
                $target = $this->navigation->definition((string) $targetId);
                if (! $target || ! in_array('family', (array) ($target['roles'] ?? []), true)) {
                    throw new DomainException($stableId.' references an unavailable Family target: '.$targetId.'.');
                }
            }
        }

        $required = [
            ...array_map(static fn (int $n): string => sprintf('FAM-MATCH-%03d', $n), range(1, 25)),
            ...array_map(static fn (int $n): string => sprintf('FAM-VISIT-%03d', $n), range(1, 35)),
            ...array_map(static fn (int $n): string => sprintf('FAM-REGULAR-%03d', $n), range(1, 26)),
        ];
        if (array_diff($required, array_keys($mapped)) !== []) {
            throw new DomainException('Batches 6 and 7 knowledge does not map every Match, Visit, and Regular Care intent.');
        }

        return $this->manifest = [
            'version' => (string) $manifest['version'],
            'approved_at' => (string) ($manifest['approved_at'] ?? ''),
            'entries' => $entries,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function entries(): array
    {
        return $this->manifest()['entries'];
    }

    /** @return array<string,mixed> */
    public function definition(string $stableId): array
    {
        $definition = collect($this->entries())->firstWhere('stable_id', $stableId);
        if (! is_array($definition)) {
            throw new DomainException('Unknown Batches 6 and 7 knowledge stable ID: '.$stableId.'.');
        }

        return $definition;
    }

    /** @param array<string,mixed> $definition @return array<string,mixed> */
    public function payload(array $definition): array
    {
        return collect($definition)->except(['stable_id', 'sources', 'intent_ids'])->all();
    }

    /** @param array<string,mixed> $definition @return list<array<string,mixed>> */
    public function sources(array $definition): array
    {
        return array_values((array) $definition['sources']);
    }
}
