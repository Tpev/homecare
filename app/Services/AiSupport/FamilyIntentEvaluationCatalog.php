<?php

namespace App\Services\AiSupport;

use DomainException;

class FamilyIntentEvaluationCatalog
{
    public const VERSION = 'family-guided-evals-v1';

    public const HANDLER_PAYMENT_METHOD = 'family_payment_method';

    public const HANDLER_PRICING = 'family_pricing';

    private const EXPECTED_INTENT_IDS = [
        'FAM-PAY-002', 'FAM-PAY-003', 'FAM-PAY-004', 'FAM-PAY-005', 'FAM-PAY-006', 'FAM-PAY-008',
        'FAM-START-003', 'FAM-START-004', 'FAM-START-017',
        'FAM-PROFILE-002', 'FAM-PROFILE-003', 'FAM-PROFILE-004',
        'FAM-REQUEST-034', 'FAM-REQUEST-035',
        'FAM-MATCH-013', 'FAM-MATCH-017',
        'FAM-PAY-012', 'FAM-PAY-013', 'FAM-PAY-014', 'FAM-PAY-016', 'FAM-PAY-017', 'FAM-PAY-018', 'FAM-PAY-019', 'FAM-PAY-020', 'FAM-PAY-021', 'FAM-PAY-023', 'FAM-PAY-026', 'FAM-PAY-029',
        'FAM-VISIT-001', 'FAM-VISIT-003', 'FAM-VISIT-009', 'FAM-VISIT-010', 'FAM-VISIT-011', 'FAM-VISIT-018', 'FAM-VISIT-019', 'FAM-VISIT-020', 'FAM-VISIT-023', 'FAM-VISIT-026',
        'FAM-REGULAR-001', 'FAM-REGULAR-009', 'FAM-REGULAR-013', 'FAM-REGULAR-017', 'FAM-REGULAR-024',
        'FAM-COMMS-001', 'FAM-COMMS-002',
        'FAM-HISTORY-001', 'FAM-HISTORY-004',
    ];

    public function __construct(
        private readonly AiSupportGuidedTaskService $guidedTasks,
        private readonly FamilyGuidedAssistanceService $familyGuidance,
        private readonly AiSupportPricingTruth $pricing,
    ) {}

    /** @return array{version:string,frozen_on:string,cases:list<array<string,mixed>>,negative_cases:list<array<string,string>>} */
    public function manifest(): array
    {
        $manifest = require resource_path('ai-support/evaluations/family-guided-v1.php');
        if (($manifest['version'] ?? null) !== self::VERSION) {
            throw new DomainException('Family intent evaluation version is invalid.');
        }

        $cases = array_values((array) ($manifest['cases'] ?? []));
        $negativeCases = array_values((array) ($manifest['negative_cases'] ?? []));
        $ids = array_map(static fn (array $case): string => (string) ($case['intent_id'] ?? ''), $cases);
        $expected = self::EXPECTED_INTENT_IDS;
        sort($ids);
        sort($expected);

        if ($ids !== $expected || count($ids) !== count(array_unique($ids))) {
            throw new DomainException('Family intent evaluation must cover each of the 47 Batch 1/2/4 registry IDs exactly once.');
        }

        $handlers = [
            self::HANDLER_PAYMENT_METHOD,
            self::HANDLER_PRICING,
            FamilyGuidedAssistanceService::INTENT_OVERVIEW,
            FamilyGuidedAssistanceService::INTENT_REQUESTS,
            FamilyGuidedAssistanceService::INTENT_VISITS,
            FamilyGuidedAssistanceService::INTENT_TIMESHEETS,
            FamilyGuidedAssistanceService::INTENT_PAYMENT_ATTENTION,
            FamilyGuidedAssistanceService::INTENT_PROFILES,
            FamilyGuidedAssistanceService::INTENT_MESSAGES,
            FamilyGuidedAssistanceService::INTENT_HISTORY,
            FamilyGuidedAssistanceService::INTENT_REGULAR_CARE,
        ];

        foreach ($cases as $case) {
            $phrases = array_values(array_filter((array) ($case['phrases'] ?? []), fn (mixed $phrase): bool => trim((string) $phrase) !== ''));
            if (! in_array($case['batch'] ?? null, [1, 2, 4], true)
                || ! preg_match('/^[a-z][a-z_]+$/', (string) ($case['domain'] ?? ''))
                || ! in_array($case['handler'] ?? null, $handlers, true)
                || count($phrases) < 3
                || trim((string) ($case['runtime_evidence'] ?? '')) === '') {
                throw new DomainException('Invalid Family intent evaluation case: '.($case['intent_id'] ?? 'unknown').'.');
            }
        }

        $negativeIds = array_map(static fn (array $case): string => (string) ($case['id'] ?? ''), $negativeCases);
        if ($negativeCases === [] || count($negativeIds) !== count(array_unique($negativeIds))) {
            throw new DomainException('Family intent negative cases must be present and unique.');
        }
        foreach ($negativeCases as $case) {
            if (! preg_match('/^NEG-[A-Z0-9-]+$/', (string) ($case['id'] ?? ''))
                || trim((string) ($case['message'] ?? '')) === '') {
                throw new DomainException('Invalid Family intent negative case.');
            }
        }

        return [
            'version' => self::VERSION,
            'frozen_on' => (string) ($manifest['frozen_on'] ?? ''),
            'cases' => $cases,
            'negative_cases' => $negativeCases,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function cases(): array
    {
        return $this->manifest()['cases'];
    }

    public function classify(string $message): ?string
    {
        if ($this->guidedTasks->isPaymentMethodIntent($message)) {
            return self::HANDLER_PAYMENT_METHOD;
        }

        if ($this->pricing->isPricingQuestion($message)) {
            return self::HANDLER_PRICING;
        }

        return $this->familyGuidance->intentFor($message);
    }

    public function intentIdFor(string $message): ?string
    {
        $handler = $this->classify($message);
        if ($handler === null) {
            return null;
        }

        $messageTokens = $this->tokens($message);
        $ranked = collect($this->cases())
            ->where('handler', $handler)
            ->map(function (array $case) use ($message, $messageTokens): array {
                $score = collect((array) $case['phrases'])
                    ->map(fn (string $phrase): float => $this->phraseScore($message, $messageTokens, $phrase))
                    ->max() ?? 0.0;

                return ['intent_id' => (string) $case['intent_id'], 'score' => $score];
            })
            ->sortByDesc('score')
            ->values();

        return $ranked->first()['intent_id'] ?? null;
    }

    /** @param list<string> $messageTokens */
    private function phraseScore(string $message, array $messageTokens, string $phrase): float
    {
        $normalizedMessage = $this->normalize($message);
        $normalizedPhrase = $this->normalize($phrase);
        if ($normalizedMessage === $normalizedPhrase) {
            return 10.0;
        }

        $phraseTokens = $this->tokens($phrase);
        $intersection = count(array_intersect($messageTokens, $phraseTokens));
        $union = max(1, count(array_unique(array_merge($messageTokens, $phraseTokens))));

        return ($intersection / max(1, count($phraseTokens))) + ($intersection / $union);
    }

    /** @return list<string> */
    private function tokens(string $value): array
    {
        $stop = ['a', 'an', 'and', 'are', 'can', 'do', 'for', 'help', 'i', 'is', 'it', 'me', 'my', 'of', 'please', 'the', 'to', 'with'];

        return collect(preg_split('/[^a-z0-9]+/', $this->normalize($value)) ?: [])
            ->filter(fn (string $token): bool => mb_strlen($token) >= 2 && ! in_array($token, $stop, true))
            ->unique()->values()->all();
    }

    private function normalize(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower($value)));
    }
}
