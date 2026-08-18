<?php

namespace App\Services\AiSupport;

use DomainException;

class FamilyOperationsKnowledgeBaseCatalog
{
    public const VERSION = 'family-operations-kb-v1';

    public const APPROVED_STABLE_IDS = [
        'KB-FOP-PAY-001', 'KB-FOP-PAY-002', 'KB-FOP-PAY-003', 'KB-FOP-PAY-004', 'KB-FOP-PAY-005',
        'KB-FOP-PAY-006', 'KB-FOP-PAY-007', 'KB-FOP-PAY-008', 'KB-FOP-PAY-009',
        'KB-FOP-REQ-001', 'KB-FOP-REQ-002', 'KB-FOP-REQ-003', 'KB-FOP-REQ-004', 'KB-FOP-REQ-005',
        'KB-FOP-REQ-006', 'KB-FOP-REQ-007', 'KB-FOP-REQ-008', 'KB-FOP-REQ-009', 'KB-FOP-REQ-010',
        'KB-FOP-VIS-001', 'KB-FOP-VIS-002', 'KB-FOP-VIS-003', 'KB-FOP-VIS-004', 'KB-FOP-VIS-005',
        'KB-FOP-VIS-006', 'KB-FOP-VIS-007', 'KB-FOP-VIS-008', 'KB-FOP-VIS-009', 'KB-FOP-VIS-010',
        'KB-FOP-PRO-001', 'KB-FOP-PRO-002', 'KB-FOP-PRO-003', 'KB-FOP-PRO-004',
        'KB-FOP-PRO-005', 'KB-FOP-PRO-006', 'KB-FOP-PRO-007', 'KB-FOP-PRO-008',
        'KB-FOP-ACC-001', 'KB-FOP-ACC-002', 'KB-FOP-ACC-003',
        'KB-FOP-COM-001', 'KB-FOP-COM-002', 'KB-FOP-COM-003', 'KB-FOP-COM-004',
        'KB-FOP-REG-001', 'KB-FOP-REG-002', 'KB-FOP-REG-003', 'KB-FOP-REG-004',
        'KB-FOP-HIS-001', 'KB-FOP-HIS-002',
    ];

    public const REVISION_STABLE_IDS = ['KB-FAM-004'];

    private ?array $manifest = null;

    public function __construct(private readonly NavigationTargetRegistry $navigation) {}

    /** @return array{version:string,approved_at:string,entries:list<array<string,mixed>>,revisions:list<array<string,mixed>>} */
    public function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $manifest = require resource_path('ai-support/knowledge-base/family-operations-v1.php');
        if (($manifest['version'] ?? null) !== self::VERSION) {
            throw new DomainException('Family operations knowledge manifest version is invalid.');
        }

        $entries = array_values((array) ($manifest['entries'] ?? []));
        $revisions = array_values((array) ($manifest['revisions'] ?? []));
        if (array_column($entries, 'stable_id') !== self::APPROVED_STABLE_IDS
            || array_column($revisions, 'stable_id') !== self::REVISION_STABLE_IDS) {
            throw new DomainException('Family operations knowledge inventory does not match the approved stable IDs.');
        }

        foreach (array_merge($entries, $revisions) as $definition) {
            $stableId = (string) ($definition['stable_id'] ?? 'unknown');
            foreach (['title', 'answer_body', 'product_area', 'review_by'] as $field) {
                if (trim((string) ($definition[$field] ?? '')) === '') {
                    throw new DomainException($stableId.' is missing '.$field.'.');
                }
            }
            if (($definition['roles'] ?? null) !== ['family']
                || count((array) ($definition['evaluation_ids'] ?? [])) !== 5
                || count(array_unique((array) $definition['evaluation_ids'])) !== 5
                || (array) ($definition['sources'] ?? []) === []
                || (array) ($definition['intent_ids'] ?? []) === []) {
                throw new DomainException($stableId.' requires Family scope, intent links, five evaluations, and a source.');
            }
            foreach ((array) $definition['intent_ids'] as $intentId) {
                if (! preg_match('/^FAM-[A-Z]+-[0-9]{3}$/', (string) $intentId)) {
                    throw new DomainException($stableId.' contains an invalid Family intent ID.');
                }
            }
            foreach ((array) $definition['route_target_ids'] as $targetId) {
                $target = $this->navigation->definition((string) $targetId);
                if (! $this->navigation->has((string) $targetId)
                    || ! in_array('family', (array) ($target['roles'] ?? []), true)) {
                    throw new DomainException($stableId.' references an unavailable Family navigation target: '.$targetId.'.');
                }
            }
        }

        return $this->manifest = [
            'version' => self::VERSION,
            'approved_at' => (string) $manifest['approved_at'],
            'entries' => $entries,
            'revisions' => $revisions,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function entries(): array
    {
        return $this->manifest()['entries'];
    }

    /** @return list<array<string,mixed>> */
    public function revisions(): array
    {
        return $this->manifest()['revisions'];
    }

    /** @return list<array<string,mixed>> */
    public function allDefinitions(): array
    {
        return array_merge($this->entries(), $this->revisions());
    }

    /** @return array<string,mixed> */
    public function definition(string $stableId): array
    {
        $definition = collect($this->allDefinitions())->firstWhere('stable_id', $stableId);
        if (! is_array($definition)) {
            throw new DomainException('Unknown Family operations knowledge stable ID: '.$stableId.'.');
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
