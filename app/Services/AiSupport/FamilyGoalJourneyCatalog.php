<?php

namespace App\Services\AiSupport;

use DomainException;

class FamilyGoalJourneyCatalog
{
    public const VERSION = 'family-goal-journeys-v1';

    /** @var array<string,mixed>|null */
    private ?array $manifest = null;

    /** @return array<string,mixed> */
    public function all(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest['journeys'];
        }

        $manifest = require resource_path('ai-support/journeys/family-v1.php');
        $journeys = (array) ($manifest['journeys'] ?? []);
        if (($manifest['version'] ?? null) !== self::VERSION || count($journeys) !== 10) {
            throw new DomainException('The Family goal journey catalog must contain exactly ten versioned journeys.');
        }
        foreach ($journeys as $id => $journey) {
            if (! preg_match('/^[a-z][a-z0-9_]+$/', (string) $id)
                || blank($journey['label'] ?? null)
                || blank($journey['default_step'] ?? null)
                || (int) ($journey['progress_total'] ?? 0) < 1) {
                throw new DomainException('Invalid Family goal journey definition: '.$id.'.');
            }
        }

        $this->manifest = $manifest;

        return $journeys;
    }

    /** @return array<string,mixed>|null */
    public function find(string $journeyType): ?array
    {
        $journey = $this->all()[$journeyType] ?? null;

        return is_array($journey) ? $journey : null;
    }

    /** @param array<string,mixed> $intent */
    public function forIntent(array $intent): ?string
    {
        $domain = (string) ($intent['domain'] ?? '');
        $intentText = mb_strtolower((string) ($intent['intent'] ?? ''));

        if ($domain === 'payments' && preg_match('/\b(?:card|payment method|credit card)\b/iu', $intentText)) {
            return 'payment_method';
        }

        foreach ($this->all() as $type => $journey) {
            if (in_array($domain, (array) ($journey['domains'] ?? []), true)) {
                return $type;
            }
        }

        return null;
    }

    public function label(string $journeyType): string
    {
        return (string) ($this->find($journeyType)['label'] ?? 'Get help in LoLo');
    }
}
