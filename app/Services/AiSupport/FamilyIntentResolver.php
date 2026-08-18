<?php

namespace App\Services\AiSupport;

class FamilyIntentResolver
{
    public const STATUS_RECOGNIZED = 'recognized';

    public const STATUS_CLARIFY = 'clarify';

    public const STATUS_UNMATCHED = 'unmatched';

    public function __construct(
        private readonly FamilyIntentCatalog $catalog,
        private readonly FamilyIntentEvaluationCatalog $implemented,
    ) {}

    /** @return array{status:string,intent_id:?string,candidate_ids:list<string>,confidence:float,source:string} */
    public function resolve(string $message): array
    {
        $preparationIntent = $this->preparationIntent($message);
        if ($preparationIntent !== null) {
            return [
                'status' => self::STATUS_RECOGNIZED,
                'intent_id' => $preparationIntent,
                'candidate_ids' => [$preparationIntent],
                'confidence' => 1.0,
                'source' => 'deterministic_preparation',
            ];
        }

        $implementedId = $this->implemented->intentIdFor($message);
        if ($implementedId !== null) {
            return [
                'status' => self::STATUS_RECOGNIZED,
                'intent_id' => $implementedId,
                'candidate_ids' => [$implementedId],
                'confidence' => 1.0,
                'source' => 'deterministic_handler',
            ];
        }

        $normalized = $this->normalize($message);
        if ($normalized === '') {
            return $this->unmatched();
        }
        $tokens = $this->tokens($message);
        $ranked = collect($this->catalog->records())
            ->map(function (array $record) use ($normalized, $tokens): array {
                $scores = [];
                foreach ((array) data_get($record, 'phrases.ordinary', []) as $phrase) {
                    $scores[] = $this->score($normalized, $tokens, (string) $phrase);
                }
                foreach ((array) data_get($record, 'phrases.imperfect', []) as $phrase) {
                    $scores[] = $this->score($normalized, $tokens, (string) $phrase);
                }

                return [
                    'intent_id' => (string) $record['intent_id'],
                    'score' => max($scores ?: [0.0]),
                ];
            })
            ->sortByDesc('score')
            ->values();

        $first = $ranked->get(0, ['score' => 0.0, 'intent_id' => null]);
        $second = $ranked->get(1, ['score' => 0.0, 'intent_id' => null]);
        $confidence = min(1.0, (float) $first['score']);
        $margin = (float) $first['score'] - (float) $second['score'];
        if ($first['score'] >= 0.88 || ($first['score'] >= 0.64 && $margin >= 0.16)) {
            return [
                'status' => self::STATUS_RECOGNIZED,
                'intent_id' => (string) $first['intent_id'],
                'candidate_ids' => [(string) $first['intent_id']],
                'confidence' => $confidence,
                'source' => 'catalog_lexical',
            ];
        }
        // Short, context-free phrases create noisy near-neighbours across the 324-intent
        // catalog. Only interrupt the normal support path when the lexical evidence is
        // strong enough to present two genuinely useful choices.
        if ($first['score'] >= 0.60) {
            return [
                'status' => self::STATUS_CLARIFY,
                'intent_id' => null,
                'candidate_ids' => collect($ranked)->take(2)->pluck('intent_id')->all(),
                'confidence' => $confidence,
                'source' => 'catalog_close_neighbor',
            ];
        }

        return $this->unmatched();
    }

    /** @param list<string> $messageTokens */
    private function score(string $normalizedMessage, array $messageTokens, string $phrase): float
    {
        $normalizedPhrase = $this->normalize($phrase);
        if ($normalizedMessage === $normalizedPhrase) {
            return 1.0;
        }
        $phraseTokens = $this->tokens($phrase);
        if ($phraseTokens === [] || $messageTokens === []) {
            return 0.0;
        }
        $intersection = count(array_intersect($messageTokens, $phraseTokens));
        $coverage = $intersection / count($phraseTokens);
        $precision = $intersection / count($messageTokens);
        $union = count(array_unique(array_merge($messageTokens, $phraseTokens)));
        $jaccard = $intersection / max(1, $union);

        return ($coverage * 0.5) + ($precision * 0.3) + ($jaccard * 0.2);
    }

    /** @return list<string> */
    private function tokens(string $value): array
    {
        $stop = ['a', 'about', 'an', 'and', 'are', 'can', 'could', 'do', 'for', 'help', 'how', 'i', 'is', 'it', 'me', 'my', 'of', 'please', 'the', 'to', 'want', 'with'];

        return collect(preg_split('/[^a-z0-9]+/', $this->normalize($value)) ?: [])
            ->filter(fn (string $token): bool => mb_strlen($token) >= 2 && ! in_array($token, $stop, true))
            ->map(fn (string $token): string => match ($token) {
                'cards', 'creditcard' => 'card',
                'caregivers' => 'caregiver',
                'requests' => 'request',
                'visits' => 'visit',
                'messages' => 'message',
                'payments' => 'payment',
                'profiles' => 'profile',
                default => $token,
            })
            ->unique()->values()->all();
    }

    private function normalize(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower(strip_tags($value))));
    }

    private function preparationIntent(string $message): ?string
    {
        $value = $this->normalize($message);
        $prepareVerb = preg_match('/\b(?:prepare|draft|write|send|create|copy|duplicate|reuse|update|change|correct|question|dispute)\b/', $value) === 1;
        if (! $prepareVerb) {
            return null;
        }
        if (preg_match('/\b(?:draft|write|prepare|send)\b.*\bmessage\b|\bmessage\b.*\b(?:caregiver|applicant)\b/', $value)) {
            return 'FAM-COMMS-003';
        }
        if (preg_match('/\b(?:copy|duplicate)\b.*\brequest\b/', $value)) {
            return 'FAM-REQUEST-040';
        }
        if (preg_match('/\b(?:reuse|same as|last)\b.*\brequest\b/', $value)) {
            return 'FAM-REQUEST-020';
        }
        if (preg_match('/\b(?:create|new)\b.*\b(?:care receiver|recipient)\b.*\bprofile\b/', $value)) {
            return 'FAM-PROFILE-003';
        }
        if (preg_match('/\b(?:update|change|edit|correct)\b.*\bprofile\b|\b(?:mobility|routine|safety|communication|allerg)\w*\b.*\b(?:note|profile|information)\w*\b/', $value)) {
            return match (true) {
                str_contains($value, 'mobility') => 'FAM-PROFILE-011',
                str_contains($value, 'communicat') => 'FAM-PROFILE-009',
                str_contains($value, 'safety') => 'FAM-PROFILE-013',
                str_contains($value, 'routine'), str_contains($value, 'allerg') => 'FAM-PROFILE-012',
                default => 'FAM-PROFILE-008',
            };
        }
        if (preg_match('/\b(?:submitted|reported)\b.*\b(?:hour|time)s?\b|\b(?:hour|time)s?\b.*\b(?:correction|dispute|wrong|change)\b/', $value)) {
            return str_contains($value, 'dispute') ? 'FAM-VISIT-028' : 'FAM-VISIT-022';
        }
        if (preg_match('/\b(?:report|prepare|create)\b.*\b(?:bug|broken|accessibility|complaint|support|privacy)\b/', $value)) {
            return match (true) {
                str_contains($value, 'accessibility') => 'FAM-SUPPORT-009',
                str_contains($value, 'privacy') => 'FAM-SUPPORT-015',
                str_contains($value, 'complaint') => 'FAM-SUPPORT-011',
                default => 'FAM-SUPPORT-008',
            };
        }

        return null;
    }

    /** @return array{status:string,intent_id:null,candidate_ids:list<string>,confidence:float,source:string} */
    private function unmatched(): array
    {
        return [
            'status' => self::STATUS_UNMATCHED,
            'intent_id' => null,
            'candidate_ids' => [],
            'confidence' => 0.0,
            'source' => 'unmatched',
        ];
    }
}
