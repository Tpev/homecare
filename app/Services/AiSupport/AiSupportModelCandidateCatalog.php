<?php

namespace App\Services\AiSupport;

use DomainException;

class AiSupportModelCandidateCatalog
{
    public const VERSION = 'ai-support-model-candidates-v1';

    /** @return array{version:string,pricing_checked_on:string,currency:string,candidates:list<array<string,mixed>>} */
    public function manifest(): array
    {
        $path = resource_path('ai-support/evaluations/models-v1.php');
        if (! is_file($path)) {
            throw new DomainException('AI Support model candidate manifest is missing.');
        }

        $manifest = require $path;
        if (! is_array($manifest) || ($manifest['version'] ?? null) !== self::VERSION) {
            throw new DomainException('AI Support model candidate manifest has an invalid version.');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($manifest['pricing_checked_on'] ?? ''))) {
            throw new DomainException('AI Support model candidate pricing date is invalid.');
        }
        if (($manifest['currency'] ?? null) !== 'USD') {
            throw new DomainException('AI Support model candidate pricing must use USD.');
        }

        $candidates = array_values((array) ($manifest['candidates'] ?? []));
        if (count($candidates) < 2) {
            throw new DomainException('At least two AI Support model candidates are required.');
        }

        $seen = [];
        foreach ($candidates as $index => $candidate) {
            if (! is_array($candidate)) {
                throw new DomainException('AI Support model candidate '.($index + 1).' is invalid.');
            }

            $id = trim((string) ($candidate['id'] ?? ''));
            if (! preg_match('/^[a-z0-9.-]+$/', $id) || isset($seen[$id])) {
                throw new DomainException('AI Support model candidate ID is invalid or duplicated: '.$id.'.');
            }
            $seen[$id] = true;

            if (($candidate['provider'] ?? null) !== 'openai' || ($candidate['endpoint'] ?? null) !== 'responses') {
                throw new DomainException($id.' must use the OpenAI Responses API.');
            }
            if (trim((string) ($candidate['model'] ?? '')) === '') {
                throw new DomainException($id.' requires an exact model identifier.');
            }
            if (! in_array($candidate['reasoning_effort'] ?? null, ['none', 'minimal', 'low', 'medium', 'high'], true)) {
                throw new DomainException($id.' has an unsupported reasoning effort.');
            }
            if ((int) ($candidate['max_output_tokens'] ?? 0) < 200) {
                throw new DomainException($id.' output-token limit is too small.');
            }
            if (! is_bool($candidate['baseline_eligible'] ?? null)) {
                throw new DomainException($id.' requires an explicit baseline eligibility flag.');
            }
            foreach (['input', 'cached_input', 'output'] as $rate) {
                if (! is_numeric(data_get($candidate, 'pricing_per_million_tokens.'.$rate))
                    || (float) data_get($candidate, 'pricing_per_million_tokens.'.$rate) < 0) {
                    throw new DomainException($id.' has invalid '.$rate.' pricing.');
                }
            }
            if (! str_starts_with((string) ($candidate['source_url'] ?? ''), 'https://developers.openai.com/')) {
                throw new DomainException($id.' requires an official OpenAI source URL.');
            }
        }
        if (count(array_filter($candidates, fn (array $candidate): bool => $candidate['baseline_eligible'])) < 2) {
            throw new DomainException('At least two current candidates must be eligible for baseline recommendation.');
        }

        return [
            'version' => self::VERSION,
            'pricing_checked_on' => $manifest['pricing_checked_on'],
            'currency' => 'USD',
            'candidates' => $candidates,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function candidates(): array
    {
        return $this->manifest()['candidates'];
    }

    /** @return array<string,mixed> */
    public function candidate(string $id): array
    {
        foreach ($this->candidates() as $candidate) {
            if ($candidate['id'] === $id) {
                return $candidate;
            }
        }

        throw new DomainException('Unknown AI Support model candidate: '.$id.'.');
    }

    /** @param array<string,int> $usage */
    public function estimatedCost(array $candidate, array $usage): float
    {
        $input = max(0, (int) ($usage['input_tokens'] ?? 0));
        $cached = min($input, max(0, (int) ($usage['cached_input_tokens'] ?? 0)));
        $uncached = $input - $cached;
        $output = max(0, (int) ($usage['output_tokens'] ?? 0));
        $rates = $candidate['pricing_per_million_tokens'];

        return (($uncached * (float) $rates['input'])
            + ($cached * (float) $rates['cached_input'])
            + ($output * (float) $rates['output'])) / 1_000_000;
    }
}
