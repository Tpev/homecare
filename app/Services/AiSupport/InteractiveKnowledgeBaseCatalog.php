<?php

namespace App\Services\AiSupport;

use DomainException;

class InteractiveKnowledgeBaseCatalog
{
    public const VERSION = 'interactive-kb-v1';

    public const APPROVED_STABLE_IDS = [
        'KB-CARE-001', 'KB-CARE-002', 'KB-CARE-003', 'KB-CARE-004',
        'KB-CARE-005', 'KB-CARE-006', 'KB-CARE-007', 'KB-CARE-008',
        'KB-CARE-009', 'KB-CARE-010', 'KB-CARE-011', 'KB-CARE-012',
    ];

    private ?array $manifest = null;

    /** @return array{version:string,approved_at:string,entries:list<array<string,mixed>>} */
    public function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }
        $manifest = require resource_path('ai-support/knowledge-base/interactive-v1.php');
        if (($manifest['version'] ?? null) !== self::VERSION) {
            throw new DomainException('Interactive knowledge manifest version is invalid.');
        }
        $entries = array_values((array) ($manifest['entries'] ?? []));
        if (array_column($entries, 'stable_id') !== self::APPROVED_STABLE_IDS) {
            throw new DomainException('Interactive knowledge inventory does not match the approved stable IDs.');
        }
        foreach ($entries as $entry) {
            foreach (['title', 'answer_body', 'product_area', 'review_by'] as $field) {
                if (trim((string) ($entry[$field] ?? '')) === '') {
                    throw new DomainException($entry['stable_id'].' is missing '.$field.'.');
                }
            }
            if (count((array) ($entry['evaluation_ids'] ?? [])) !== 5
                || (array) ($entry['sources'] ?? []) === []) {
                throw new DomainException($entry['stable_id'].' requires five evaluations and an authoritative source.');
            }
        }

        return $this->manifest = [
            'version' => self::VERSION,
            'approved_at' => (string) $manifest['approved_at'],
            'entries' => $entries,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function entries(): array
    {
        return $this->manifest()['entries'];
    }

    /** @param array<string,mixed> $entry @return array<string,mixed> */
    public function payload(array $entry): array
    {
        return collect($entry)->except(['stable_id', 'sources'])->all();
    }

    /** @param array<string,mixed> $entry @return list<array<string,mixed>> */
    public function sources(array $entry): array
    {
        return array_values((array) $entry['sources']);
    }
}
