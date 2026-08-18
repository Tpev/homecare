<?php

namespace App\Services\AiSupport;

use DomainException;
use Illuminate\Support\Collection;

class FamilyIntentCatalog
{
    public const VERSION = 'family-intents-v1';

    private ?array $manifest = null;

    /** @return array{version:string,generated_on:string,source:string,source_sha256:string,records:list<array<string,mixed>>} */
    public function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $manifest = require resource_path('ai-support/intents/family-v1.php');
        $records = array_values((array) ($manifest['records'] ?? []));
        if (($manifest['version'] ?? null) !== self::VERSION
            || count($records) !== 324
            || count(array_unique(array_column($records, 'intent_id'))) !== 324) {
            throw new DomainException('The executable Family intent catalog must contain exactly 324 unique records.');
        }
        $sourcePath = base_path((string) ($manifest['source'] ?? ''));
        if (! is_file($sourcePath)
            || ! hash_equals((string) ($manifest['source_sha256'] ?? ''), hash_file('sha256', $sourcePath))) {
            throw new DomainException('The executable Family intent catalog is stale. Regenerate it from the coverage registry.');
        }

        $mapped = 0;
        foreach ($records as $record) {
            $id = (string) ($record['intent_id'] ?? '');
            $contracts = (array) ($record['contracts'] ?? []);
            $phrases = (array) ($record['phrases'] ?? []);
            $stages = (array) ($record['capability_stages'] ?? []);
            if (! preg_match('/^FAM-[A-Z]+-[0-9]{3}$/', $id)
                || ! in_array((string) ($record['priority'] ?? ''), ['critical', 'high', 'standard'], true)
                || ($record['roles'] ?? null) !== ['family']
                || ($record['membership_states'] ?? null) !== ['active']
                || count((array) ($phrases['ordinary'] ?? [])) < 3
                || (array) ($phrases['imperfect'] ?? []) === []
                || ! in_array('Understand', (array) ($stages['current'] ?? []), true)
                || ! array_key_exists('unsupported_behavior', (array) ($record['disposition'] ?? []))
                || count((array) ($record['evaluation_ids'] ?? [])) < 4
                || ! in_array((string) ($record['rollout_state'] ?? ''), ['backlog', 'building', 'pilot', 'released'], true)) {
                throw new DomainException("Invalid executable Family intent record: {$id}.");
            }
            foreach (['reader', 'destinations', 'guided_task', 'prefill', 'tool', 'verifier', 'human_transfer'] as $key) {
                if (! array_key_exists($key, $contracts)) {
                    throw new DomainException("{$id} is missing the {$key} contract reference.");
                }
            }
            if ((array) ($record['kb_stable_ids'] ?? []) !== []) {
                $mapped++;
            }
        }
        if ($mapped !== 197) {
            throw new DomainException('The executable Family intent catalog must explicitly map all 197 Batch 4 intents.');
        }

        return $this->manifest = [
            'version' => self::VERSION,
            'generated_on' => (string) ($manifest['generated_on'] ?? ''),
            'source' => (string) ($manifest['source'] ?? ''),
            'source_sha256' => (string) ($manifest['source_sha256'] ?? ''),
            'records' => $records,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function records(): array
    {
        return $this->manifest()['records'];
    }

    /** @return array<string,mixed>|null */
    public function find(string $intentId): ?array
    {
        $record = collect($this->records())->firstWhere('intent_id', strtoupper(trim($intentId)));

        return is_array($record) ? $record : null;
    }

    /** @return Collection<int,array<string,mixed>> */
    public function forRole(string $role, string $membershipState = 'active'): Collection
    {
        return collect($this->records())
            ->filter(fn (array $record): bool => in_array($role, (array) $record['roles'], true)
                && in_array($membershipState, (array) $record['membership_states'], true))
            ->values();
    }

    /** @return array<string,int> */
    public function coverageSummary(): array
    {
        $records = collect($this->records());

        return [
            'total' => $records->count(),
            'kb_mapped' => $records->filter(fn (array $record): bool => (array) $record['kb_stable_ids'] !== [])->count(),
            'pilot' => $records->where('rollout_state', 'pilot')->count(),
            'backlog' => $records->where('rollout_state', 'backlog')->count(),
            'read' => $records->filter(fn (array $record): bool => in_array('Read', (array) data_get($record, 'capability_stages.current', []), true))->count(),
            'guide' => $records->filter(fn (array $record): bool => in_array('Guide', (array) data_get($record, 'capability_stages.current', []), true))->count(),
            'prepare' => $records->filter(fn (array $record): bool => in_array('Prepare', (array) data_get($record, 'capability_stages.current', []), true))->count(),
            'execute' => $records->filter(fn (array $record): bool => in_array('Execute', (array) data_get($record, 'capability_stages.current', []), true))->count(),
            'human' => $records->filter(fn (array $record): bool => in_array('Human', (array) data_get($record, 'capability_stages.current', []), true))->count(),
        ];
    }
}
