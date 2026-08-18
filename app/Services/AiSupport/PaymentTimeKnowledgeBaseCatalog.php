<?php

namespace App\Services\AiSupport;

use DomainException;

class PaymentTimeKnowledgeBaseCatalog
{
    public const VERSION = 'payment-time-kb-v1';

    public const APPROVED_STABLE_IDS = [
        'KB-B4-PRICE-001',
        'KB-B4-PAY-001', 'KB-B4-PAY-002', 'KB-B4-PAY-003', 'KB-B4-PAY-004',
        'KB-B4-PAY-005', 'KB-B4-PAY-006', 'KB-B4-PAY-007', 'KB-B4-PAY-008',
        'KB-B4-PAY-009', 'KB-B4-PAY-010', 'KB-B4-PAY-011',
        'KB-B4-TIME-001', 'KB-B4-TIME-002', 'KB-B4-TIME-003',
        'KB-B4-TIME-004', 'KB-B4-TIME-005', 'KB-B4-TIME-006',
    ];

    private ?array $manifest = null;

    public function __construct(private readonly NavigationTargetRegistry $navigation) {}

    /** @return array{version:string,approved_at:string,entries:list<array<string,mixed>>} */
    public function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $manifest = require resource_path('ai-support/knowledge-base/payment-time-v1.php');
        $entries = array_values((array) ($manifest['entries'] ?? []));
        if (($manifest['version'] ?? null) !== self::VERSION
            || array_column($entries, 'stable_id') !== self::APPROVED_STABLE_IDS) {
            throw new DomainException('Batch 4 payment and time knowledge inventory is invalid.');
        }

        $mappedIntents = [];
        foreach ($entries as $definition) {
            $stableId = (string) ($definition['stable_id'] ?? 'unknown');
            foreach (['title', 'answer_body', 'product_area', 'review_by'] as $field) {
                if (trim((string) ($definition[$field] ?? '')) === '') {
                    throw new DomainException($stableId.' is missing '.$field.'.');
                }
            }
            $roles = (array) ($definition['roles'] ?? []);
            if ($roles === [] || array_diff($roles, ['family', 'caregiver']) !== []
                || ! in_array('family', $roles, true)
                || ($definition['membership_states'] ?? null) !== ['active']
                || (array) ($definition['sources'] ?? []) === []
                || (array) ($definition['intent_ids'] ?? []) === []
                || count((array) ($definition['evaluation_ids'] ?? [])) !== 5
                || count(array_unique((array) $definition['evaluation_ids'])) !== 5) {
                throw new DomainException($stableId.' has an invalid role, source, intent, or evaluation contract.');
            }
            foreach ((array) $definition['intent_ids'] as $intentId) {
                if (! preg_match('/^FAM-[A-Z]+-[0-9]{3}$/', (string) $intentId)) {
                    throw new DomainException($stableId.' contains an invalid Family intent ID.');
                }
                $mappedIntents[(string) $intentId] = true;
            }
            foreach ((array) $definition['route_target_ids'] as $targetId) {
                $target = $this->navigation->definition((string) $targetId);
                if (! $this->navigation->has((string) $targetId)
                    || ! in_array('family', (array) ($target['roles'] ?? []), true)) {
                    throw new DomainException($stableId.' references an unavailable Family target: '.$targetId.'.');
                }
            }
        }

        $required = [
            ...array_map(static fn (int $number): string => sprintf('FAM-PAY-%03d', $number), range(1, 32)),
            ...array_map(static fn (int $number): string => sprintf('FAM-VISIT-%03d', $number), range(18, 29)),
            'FAM-VISIT-034',
        ];
        if (array_diff($required, array_keys($mappedIntents)) !== []) {
            throw new DomainException('Batch 4 knowledge does not map every approved payment and submitted-hours intent.');
        }

        return $this->manifest = [
            'version' => self::VERSION,
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
            throw new DomainException('Unknown Batch 4 knowledge stable ID: '.$stableId.'.');
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
