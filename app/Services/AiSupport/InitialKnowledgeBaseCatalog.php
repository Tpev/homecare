<?php

namespace App\Services\AiSupport;

use DateTimeImmutable;
use DomainException;

class InitialKnowledgeBaseCatalog
{
    public const VERSION = 'initial-kb-v1';

    public const APPROVED_STABLE_IDS = [
        'KB-SUP-001',
        'KB-SUP-002',
        'KB-SUP-003',
        'KB-FAM-001',
        'KB-FAM-002',
        'KB-FAM-003',
        'KB-FAM-004',
        'KB-FAM-005',
        'KB-CGV-001',
        'KB-CGV-002',
        'KB-CGV-003',
        'KB-CGV-004',
    ];

    private const CONTENT_FIELDS = [
        'type',
        'title',
        'answer_body',
        'sensitivity',
        'product_area',
        'locale',
        'roles',
        'membership_states',
        'route_target_ids',
        'capability_ids',
        'facts_may_state',
        'facts_must_not_infer',
        'next_actions',
        'escalation_conditions',
        'retrieval_examples_match',
        'retrieval_examples_no_match',
        'evaluation_ids',
        'change_note',
        'review_by',
        'expires_on',
    ];

    private ?array $validatedManifest = null;

    public function __construct(private readonly NavigationTargetRegistry $navigation) {}

    /** @return array{version:string,approved_at:string,entries:list<array<string,mixed>>} */
    public function manifest(): array
    {
        if ($this->validatedManifest !== null) {
            return $this->validatedManifest;
        }

        $path = resource_path('ai-support/knowledge-base/v1.php');
        if (! is_file($path)) {
            throw new DomainException('Initial knowledge-base manifest is missing.');
        }

        $manifest = require $path;
        if (! is_array($manifest)) {
            throw new DomainException('Initial knowledge-base manifest must return an array.');
        }

        $this->validatedManifest = $this->validate($manifest);

        return $this->validatedManifest;
    }

    /** @return list<array<string,mixed>> */
    public function entries(): array
    {
        return $this->manifest()['entries'];
    }

    /** @return array<string,mixed> */
    public function entry(string $stableId): array
    {
        foreach ($this->entries() as $entry) {
            if ($entry['stable_id'] === $stableId) {
                return $entry;
            }
        }

        throw new DomainException('Unknown initial knowledge-base stable ID: '.$stableId);
    }

    /** @param array<string,mixed> $entry @return array<string,mixed> */
    public function payload(array $entry): array
    {
        return collect(self::CONTENT_FIELDS)
            ->mapWithKeys(fn (string $field): array => [$field => $entry[$field] ?? null])
            ->all();
    }

    /** @param array<string,mixed> $entry @return list<array<string,mixed>> */
    public function sources(array $entry): array
    {
        return array_values((array) ($entry['sources'] ?? []));
    }

    /** @param array<string,mixed> $manifest @return array{version:string,approved_at:string,entries:list<array<string,mixed>>} */
    private function validate(array $manifest): array
    {
        if (($manifest['version'] ?? null) !== self::VERSION) {
            throw new DomainException('Initial knowledge-base manifest version must be '.self::VERSION.'.');
        }

        $approvedAt = trim((string) ($manifest['approved_at'] ?? ''));
        $this->date($approvedAt, 'Manifest approved_at');

        $entries = array_values((array) ($manifest['entries'] ?? []));
        if (count($entries) !== count(self::APPROVED_STABLE_IDS)) {
            throw new DomainException('Initial knowledge-base manifest must contain exactly 12 entries.');
        }

        $validated = [];
        $seen = [];
        foreach ($entries as $index => $entry) {
            if (! is_array($entry)) {
                throw new DomainException('Knowledge entry '.($index + 1).' must be an array.');
            }

            $stableId = trim((string) ($entry['stable_id'] ?? ''));
            if (! in_array($stableId, self::APPROVED_STABLE_IDS, true)) {
                throw new DomainException('Unapproved initial knowledge stable ID: '.$stableId);
            }
            if (isset($seen[$stableId])) {
                throw new DomainException('Duplicate initial knowledge stable ID: '.$stableId);
            }
            $seen[$stableId] = true;

            $allowedFields = array_merge(['stable_id', 'sources'], self::CONTENT_FIELDS);
            $unknownFields = array_diff(array_keys($entry), $allowedFields);
            if ($unknownFields !== []) {
                throw new DomainException($stableId.' contains unsupported manifest fields: '.implode(', ', $unknownFields).'.');
            }

            foreach (['title', 'answer_body', 'product_area', 'change_note'] as $field) {
                if (trim((string) ($entry[$field] ?? '')) === '') {
                    throw new DomainException($stableId.' is missing '.$field.'.');
                }
            }

            if (! in_array($entry['type'] ?? null, ['product_fact', 'task_playbook', 'navigation', 'escalation'], true)) {
                throw new DomainException($stableId.' has an unsupported type.');
            }
            if (($entry['sensitivity'] ?? null) !== 'authenticated') {
                throw new DomainException($stableId.' must use authenticated sensitivity.');
            }
            if (($entry['locale'] ?? null) !== 'en-US') {
                throw new DomainException($stableId.' must use the approved en-US locale.');
            }

            $roles = $this->uniqueStrings($entry['roles'] ?? []);
            if ($roles === [] || array_diff($roles, ['family', 'caregiver']) !== []) {
                throw new DomainException($stableId.' has an unsupported role set.');
            }
            $entry['roles'] = $roles;

            $entry['membership_states'] = $this->uniqueStrings($entry['membership_states'] ?? []);
            if ($entry['membership_states'] === []) {
                throw new DomainException($stableId.' must declare at least one membership/account state.');
            }

            $targets = $this->uniqueStrings($entry['route_target_ids'] ?? []);
            if (count($targets) !== 1) {
                throw new DomainException($stableId.' must declare exactly one approved generic semantic target.');
            }
            foreach ($targets as $targetId) {
                $definition = $this->navigation->definition($targetId);
                if (! $definition || ! $this->navigation->has($targetId)) {
                    throw new DomainException($stableId.' uses an unregistered semantic target: '.$targetId.'.');
                }
                if (array_diff($roles, $definition['roles']) !== []) {
                    throw new DomainException($stableId.' target '.$targetId.' is not authorized for every declared role.');
                }
            }
            $entry['route_target_ids'] = $targets;

            $capabilities = $this->uniqueStrings($entry['capability_ids'] ?? []);
            if ($capabilities !== ['support_answers_v1']) {
                throw new DomainException($stableId.' must remain in the support_answers_v1 read-only capability.');
            }
            $entry['capability_ids'] = $capabilities;

            foreach ([
                'facts_may_state',
                'facts_must_not_infer',
                'next_actions',
                'escalation_conditions',
                'retrieval_examples_match',
                'retrieval_examples_no_match',
            ] as $field) {
                $entry[$field] = $this->uniqueStrings($entry[$field] ?? []);
                if ($entry[$field] === []) {
                    throw new DomainException($stableId.' must contain '.$field.'.');
                }
            }

            $allowedNextActions = [
                'handoff:SUP-HANDOFF-001',
                'safety:call_911_instruction',
                'safety:non_medical_boundary',
                'clarify:english_only',
                ...array_map(fn (string $targetId): string => 'navigate:'.$targetId, $targets),
            ];
            if (array_diff($entry['next_actions'], $allowedNextActions) !== []) {
                throw new DomainException($stableId.' contains an unapproved or writable next action.');
            }
            foreach ($entry['next_actions'] as $nextAction) {
                if (str_starts_with($nextAction, 'navigate:')
                    && ! in_array(substr($nextAction, 9), $targets, true)) {
                    throw new DomainException($stableId.' contains navigation outside its registered target.');
                }
            }

            $evaluationIds = $this->uniqueStrings($entry['evaluation_ids'] ?? []);
            if (count($evaluationIds) < 5) {
                throw new DomainException($stableId.' must link at least five evaluations.');
            }
            foreach ($evaluationIds as $evaluationId) {
                if (! preg_match('/^EVAL-KB-[A-Z0-9-]+$/', $evaluationId)) {
                    throw new DomainException($stableId.' contains an invalid evaluation ID: '.$evaluationId.'.');
                }
            }
            $entry['evaluation_ids'] = $evaluationIds;

            $reviewBy = trim((string) ($entry['review_by'] ?? ''));
            if ($this->date($reviewBy, $stableId.' review_by') < $this->date($approvedAt, 'Manifest approved_at')) {
                throw new DomainException($stableId.' review_by cannot predate approval.');
            }
            $entry['review_by'] = $reviewBy;
            $entry['expires_on'] = filled($entry['expires_on'] ?? null)
                ? $this->date((string) $entry['expires_on'], $stableId.' expires_on')->format('Y-m-d')
                : null;

            $sources = array_values((array) ($entry['sources'] ?? []));
            if ($sources === []) {
                throw new DomainException($stableId.' must contain at least one source.');
            }
            foreach ($sources as $sourceIndex => $source) {
                if (! is_array($source)) {
                    throw new DomainException($stableId.' source '.($sourceIndex + 1).' must be an array.');
                }
                $sourceId = trim((string) ($source['source_id'] ?? ''));
                if (! preg_match('/^SRC-[A-Z0-9-]+$/', $sourceId)) {
                    throw new DomainException($stableId.' contains an invalid source ID: '.$sourceId.'.');
                }
                if (trim((string) ($source['title'] ?? '')) === '' || trim((string) ($source['fact_supported'] ?? '')) === '') {
                    throw new DomainException($stableId.' source '.($sourceIndex + 1).' requires title and fact_supported.');
                }
                if (filled($source['url'] ?? null) && filter_var($source['url'], FILTER_VALIDATE_URL) === false) {
                    throw new DomainException($stableId.' source '.($sourceIndex + 1).' contains an invalid URL.');
                }
                $sources[$sourceIndex] = [
                    'source_id' => $sourceId,
                    'title' => trim((string) $source['title']),
                    'url' => filled($source['url'] ?? null) ? trim((string) $source['url']) : null,
                    'section_anchor' => filled($source['section_anchor'] ?? null) ? trim((string) $source['section_anchor']) : null,
                    'fact_supported' => trim((string) $source['fact_supported']),
                ];
            }
            $entry['sources'] = $sources;

            $validated[] = $entry;
        }

        $actualIds = array_keys($seen);
        sort($actualIds);
        $approvedIds = self::APPROVED_STABLE_IDS;
        sort($approvedIds);
        if ($actualIds !== $approvedIds) {
            throw new DomainException('Initial knowledge-base manifest does not match the approved inventory.');
        }

        return [
            'version' => self::VERSION,
            'approved_at' => $approvedAt,
            'entries' => $validated,
        ];
    }

    /** @return list<string> */
    private function uniqueStrings(mixed $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (mixed $value): string => trim((string) $value),
            (array) $values,
        ))));
    }

    private function date(string $value, string $label): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
        $errors = DateTimeImmutable::getLastErrors();
        if (! $date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new DomainException($label.' must use YYYY-MM-DD.');
        }

        return $date;
    }
}
