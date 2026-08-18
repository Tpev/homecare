<?php

namespace App\Services\AiSupport;

use DomainException;

class ProfileRequestKnowledgeBaseCatalog
{
    public const VERSION = 'profile-request-kb-v1';

    public const APPROVED_STABLE_IDS = [
        'KB-B5-PROFILE-001', 'KB-B5-PROFILE-002', 'KB-B5-PROFILE-003',
        'KB-B5-PROFILE-004', 'KB-B5-PROFILE-005', 'KB-B5-PROFILE-006',
        'KB-B5-PROFILE-007', 'KB-B5-PROFILE-008', 'KB-B5-PROFILE-009',
        'KB-B5-REQUEST-001', 'KB-B5-REQUEST-002', 'KB-B5-REQUEST-003',
        'KB-B5-REQUEST-004', 'KB-B5-REQUEST-005', 'KB-B5-REQUEST-006',
        'KB-B5-REQUEST-007', 'KB-B5-REQUEST-008', 'KB-B5-REQUEST-009',
        'KB-B5-REQUEST-010', 'KB-B5-REQUEST-011',
    ];

    private ?array $manifest = null;

    public function __construct(private readonly NavigationTargetRegistry $navigation) {}

    /** @return array{version:string,approved_at:string,entries:list<array<string,mixed>>} */
    public function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $manifest = require resource_path('ai-support/knowledge-base/profile-request-v1.php');
        $entries = array_values((array) ($manifest['entries'] ?? []));
        if (($manifest['version'] ?? null) !== self::VERSION
            || array_column($entries, 'stable_id') !== self::APPROVED_STABLE_IDS) {
            throw new DomainException('Batch 5 profile and request knowledge inventory is invalid.');
        }

        $mappedIntents = [];
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
                || count((array) ($definition['evaluation_ids'] ?? [])) !== 5
                || count(array_unique((array) $definition['evaluation_ids'])) !== 5) {
                throw new DomainException($stableId.' has an invalid role, source, intent, or evaluation contract.');
            }
            foreach ((array) $definition['intent_ids'] as $intentId) {
                if (! preg_match('/^FAM-(?:PROFILE|REQUEST)-[0-9]{3}$/', (string) $intentId)) {
                    throw new DomainException($stableId.' contains an invalid Batch 5 intent ID.');
                }
                $mappedIntents[(string) $intentId] = true;
            }
            foreach ((array) $definition['route_target_ids'] as $targetId) {
                if (str_starts_with((string) $targetId, 'handoff:')) {
                    continue;
                }
                $target = $this->navigation->definition((string) $targetId);
                if (! $target || ! in_array('family', (array) ($target['roles'] ?? []), true)) {
                    throw new DomainException($stableId.' references an unavailable Family target: '.$targetId.'.');
                }
            }
        }

        $required = [
            ...array_map(static fn (int $number): string => sprintf('FAM-PROFILE-%03d', $number), range(1, 26)),
            ...array_map(static fn (int $number): string => sprintf('FAM-REQUEST-%03d', $number), range(1, 45)),
        ];
        if (array_diff($required, array_keys($mappedIntents)) !== []) {
            throw new DomainException('Batch 5 knowledge does not map every care-profile and request-lifecycle intent.');
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
            throw new DomainException('Unknown Batch 5 knowledge stable ID: '.$stableId.'.');
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
