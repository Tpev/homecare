<?php

namespace App\Services\AiSupport;

use DomainException;

class FamilyAdministrationKnowledgeBaseCatalog
{
    public const VERSION = 'family-administration-support-kb-v1';

    public const APPROVED_STABLE_IDS = [
        'KB-B89-ACCOUNT-001', 'KB-B89-ACCOUNT-002', 'KB-B89-ACCOUNT-003', 'KB-B89-ACCOUNT-004',
        'KB-B89-ACCOUNT-005', 'KB-B89-ACCOUNT-006', 'KB-B89-ACCOUNT-007', 'KB-B89-ACCOUNT-008',
        'KB-B89-ACCESS-001', 'KB-B89-ACCESS-002', 'KB-B89-ACCESS-003', 'KB-B89-ACCESS-004',
        'KB-B89-ACCESS-005', 'KB-B89-ACCESS-006', 'KB-B89-ACCESS-007',
        'KB-B89-COMMS-001', 'KB-B89-COMMS-002', 'KB-B89-COMMS-003', 'KB-B89-COMMS-004',
        'KB-B89-COMMS-005', 'KB-B89-COMMS-006', 'KB-B89-COMMS-007',
        'KB-B89-HISTORY-001', 'KB-B89-HISTORY-002', 'KB-B89-HISTORY-003', 'KB-B89-HISTORY-004',
        'KB-B89-HISTORY-005', 'KB-B89-HISTORY-006',
        'KB-B89-COVERAGE-001', 'KB-B89-COVERAGE-002', 'KB-B89-COVERAGE-003', 'KB-B89-COVERAGE-004',
        'KB-B89-COVERAGE-005', 'KB-B89-COVERAGE-006', 'KB-B89-COVERAGE-007', 'KB-B89-COVERAGE-008',
        'KB-B89-SUPPORT-001', 'KB-B89-SUPPORT-002', 'KB-B89-SUPPORT-003', 'KB-B89-SUPPORT-004',
        'KB-B89-SUPPORT-005', 'KB-B89-SUPPORT-006', 'KB-B89-SUPPORT-007', 'KB-B89-SUPPORT-008',
    ];

    private ?array $manifest = null;

    public function __construct(private readonly NavigationTargetRegistry $navigation) {}

    /** @return array{version:string,approved_at:string,entries:list<array<string,mixed>>} */
    public function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $manifest = require resource_path('ai-support/knowledge-base/family-administration-support-v1.php');
        $entries = array_values((array) ($manifest['entries'] ?? []));
        if (($manifest['version'] ?? null) !== self::VERSION
            || array_column($entries, 'stable_id') !== self::APPROVED_STABLE_IDS) {
            throw new DomainException('Batches 8 and 9 knowledge inventory is invalid.');
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
                if (! preg_match('/^FAM-(?:START|ACCOUNT|ACCESS|COMMS|HISTORY|COVERAGE|SUPPORT)-[0-9]{3}$/', (string) $intentId)) {
                    throw new DomainException($stableId.' contains an invalid Batches 8 and 9 intent ID.');
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
            ...$this->ids('START', 17),
            ...$this->ids('ACCOUNT', 20), ...$this->ids('ACCESS', 20),
            ...$this->ids('COMMS', 17), ...$this->ids('HISTORY', 15),
            ...$this->ids('COVERAGE', 26), ...$this->ids('SUPPORT', 20),
        ];
        if (array_diff($required, array_keys($mapped)) !== [] || array_diff(array_keys($mapped), $required) !== []) {
            throw new DomainException('Batches 8 and 9 knowledge must map all administration, history, coverage, support, and orientation intents.');
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
            throw new DomainException('Unknown Batches 8 and 9 knowledge stable ID: '.$stableId.'.');
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

    /** @return list<string> */
    private function ids(string $domain, int $count): array
    {
        return array_map(static fn (int $number): string => sprintf('FAM-%s-%03d', $domain, $number), range(1, $count));
    }
}
