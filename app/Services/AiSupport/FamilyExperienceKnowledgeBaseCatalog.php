<?php

namespace App\Services\AiSupport;

use DomainException;

class FamilyExperienceKnowledgeBaseCatalog
{
    public const VERSION = 'family-experience-alignment-v1';

    public const STABLE_IDS = [
        'KB-FAM-001', 'KB-FAM-002',
        'KB-CARE-001', 'KB-CARE-003', 'KB-CARE-006', 'KB-CARE-011',
        'KB-FOP-COM-003', 'KB-FOP-COM-004',
        'KB-FOP-REG-001', 'KB-FOP-REG-002', 'KB-FOP-REG-003', 'KB-FOP-REG-004',
        'KB-FOP-VIS-002', 'KB-FOP-VIS-010', 'KB-FOP-HIS-002', 'KB-FOP-ORI-001',
        'KB-FOP-PAY-011',
        'KB-B4-PRICE-001', 'KB-B4-PAY-006', 'KB-B4-PAY-011',
        'KB-B5-REQUEST-001', 'KB-B5-REQUEST-002', 'KB-B5-REQUEST-004', 'KB-B5-REQUEST-008', 'KB-B5-REQUEST-009',
        'KB-B67-MATCH-001', 'KB-B67-MATCH-004', 'KB-B67-MATCH-007',
        'KB-B67-VISIT-001', 'KB-B67-VISIT-004', 'KB-B67-VISIT-005', 'KB-B67-VISIT-010', 'KB-B67-VISIT-013', 'KB-B67-VISIT-014',
        'KB-B67-REGULAR-001', 'KB-B67-REGULAR-002', 'KB-B67-REGULAR-003',
        'KB-B67-REGULAR-004', 'KB-B67-REGULAR-005', 'KB-B67-REGULAR-006',
        'KB-B67-REGULAR-007', 'KB-B67-REGULAR-008', 'KB-B67-REGULAR-009',
        'KB-B89-ACCOUNT-005', 'KB-B89-ACCESS-004',
        'KB-B89-HISTORY-003', 'KB-B89-HISTORY-004', 'KB-B89-HISTORY-006',
        'KB-B89-COVERAGE-001', 'KB-B89-COVERAGE-002', 'KB-B89-COVERAGE-004',
        'KB-B89-COVERAGE-005', 'KB-B89-COVERAGE-006',
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
            str_starts_with($stableId, 'KB-FAM-') => [$this->initial->entry($stableId), $this->initial],
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
