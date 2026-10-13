<?php

namespace App\Services\AiSupport;

use DomainException;

class FamilyExperienceKnowledgeBaseCatalog
{
    public const VERSION = 'family-experience-alignment-v2';

    public const STABLE_IDS = [
        ...InitialKnowledgeBaseCatalog::APPROVED_STABLE_IDS,
        ...InteractiveKnowledgeBaseCatalog::APPROVED_STABLE_IDS,
        ...FamilyOperationsKnowledgeBaseCatalog::APPROVED_STABLE_IDS,
        ...PaymentTimeKnowledgeBaseCatalog::APPROVED_STABLE_IDS,
        ...ProfileRequestKnowledgeBaseCatalog::APPROVED_STABLE_IDS,
        ...MarketplaceCareKnowledgeBaseCatalog::APPROVED_STABLE_IDS,
        ...FamilyAdministrationKnowledgeBaseCatalog::APPROVED_STABLE_IDS,
    ];

    /** Source content changed in this release, including shared-source metadata. */
    public const CHANGED_STABLE_IDS = [
        'KB-FAM-001', 'KB-FAM-002', 'KB-CARE-006', 'KB-CARE-007', 'KB-FOP-PAY-011',
        'KB-B4-PRICE-001', 'KB-B4-PAY-011',
        'KB-B5-REQUEST-001', 'KB-B5-REQUEST-002', 'KB-B5-REQUEST-005',
        'KB-B5-REQUEST-006', 'KB-B5-REQUEST-008', 'KB-B5-REQUEST-009', 'KB-B5-REQUEST-010',
        'KB-B67-MATCH-001', 'KB-B67-MATCH-002', 'KB-B67-MATCH-003', 'KB-B67-MATCH-004',
        'KB-B67-MATCH-005', 'KB-B67-MATCH-007', 'KB-B67-VISIT-009', 'KB-B67-VISIT-014',
        'KB-B67-REGULAR-001', 'KB-B67-REGULAR-002', 'KB-B67-REGULAR-007',
        'KB-B89-HISTORY-003',
    ];

    private ?array $definitions = null;

    public function __construct(
        private readonly InitialKnowledgeBaseCatalog $initial,
        private readonly InteractiveKnowledgeBaseCatalog $interactive,
        private readonly FamilyOperationsKnowledgeBaseCatalog $operations,
        private readonly PaymentTimeKnowledgeBaseCatalog $paymentTime,
        private readonly ProfileRequestKnowledgeBaseCatalog $profileRequest,
        private readonly MarketplaceCareKnowledgeBaseCatalog $marketplace,
        private readonly FamilyAdministrationKnowledgeBaseCatalog $administration,
    ) {}

    /** @return list<array{stable_id:string,payload:array<string,mixed>,sources:list<array<string,mixed>>}> */
    public function definitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $definitions = array_map(fn (string $stableId): array => $this->resolve($stableId), self::STABLE_IDS);
        if (array_column($definitions, 'stable_id') !== self::STABLE_IDS) {
            throw new DomainException('Family experience alignment inventory is invalid.');
        }

        return $this->definitions = $definitions;
    }

    /** @return array{stable_id:string,payload:array<string,mixed>,sources:list<array<string,mixed>>} */
    public function definition(string $stableId): array
    {
        $definition = collect($this->definitions())->firstWhere('stable_id', $stableId);
        if (! is_array($definition)) {
            throw new DomainException('Unknown Family experience alignment stable ID: '.$stableId.'.');
        }

        return $definition;
    }

    /** @return array{stable_id:string,payload:array<string,mixed>,sources:list<array<string,mixed>>} */
    private function resolve(string $stableId): array
    {
        [$definition, $catalog] = match (true) {
            in_array($stableId, FamilyOperationsKnowledgeBaseCatalog::REVISION_STABLE_IDS, true) => [$this->operations->definition($stableId), $this->operations],
            in_array($stableId, InitialKnowledgeBaseCatalog::APPROVED_STABLE_IDS, true) => [$this->initial->entry($stableId), $this->initial],
            str_starts_with($stableId, 'KB-CARE-') => [collect($this->interactive->entries())->firstWhere('stable_id', $stableId), $this->interactive],
            str_starts_with($stableId, 'KB-FOP-') => [$this->operations->definition($stableId), $this->operations],
            str_starts_with($stableId, 'KB-B4-') => [$this->paymentTime->definition($stableId), $this->paymentTime],
            str_starts_with($stableId, 'KB-B5-') => [$this->profileRequest->definition($stableId), $this->profileRequest],
            str_starts_with($stableId, 'KB-B67-') => [$this->marketplace->definition($stableId), $this->marketplace],
            str_starts_with($stableId, 'KB-B89-') => [$this->administration->definition($stableId), $this->administration],
            default => throw new DomainException('No source catalog owns '.$stableId.'.'),
        };

        if (! is_array($definition)) {
            throw new DomainException('Source knowledge definition is missing for '.$stableId.'.');
        }

        return [
            'stable_id' => $stableId,
            'payload' => $catalog->payload($definition),
            'sources' => $catalog->sources($definition),
        ];
    }
}
