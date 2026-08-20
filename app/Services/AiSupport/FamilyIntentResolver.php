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
        $exactIntent = $this->exactCatalogIntent($message);
        if ($exactIntent !== null) {
            return [
                'status' => self::STATUS_RECOGNIZED,
                'intent_id' => $exactIntent,
                'candidate_ids' => [$exactIntent],
                'confidence' => 1.0,
                'source' => 'catalog_exact',
            ];
        }

        $batchSevenIntent = $this->batchSevenIntent($message);
        if ($batchSevenIntent !== null) {
            return [
                'status' => self::STATUS_RECOGNIZED,
                'intent_id' => $batchSevenIntent,
                'candidate_ids' => [$batchSevenIntent],
                'confidence' => 1.0,
                'source' => 'deterministic_batch67',
            ];
        }

        $batchFiveIntent = $this->batchFiveIntent($message);
        if ($batchFiveIntent !== null) {
            return [
                'status' => self::STATUS_RECOGNIZED,
                'intent_id' => $batchFiveIntent,
                'candidate_ids' => [$batchFiveIntent],
                'confidence' => 1.0,
                'source' => 'deterministic_batch5',
            ];
        }

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

        $batchNineIntent = $this->batchNineIntent($message);
        if ($batchNineIntent !== null) {
            return [
                'status' => self::STATUS_RECOGNIZED,
                'intent_id' => $batchNineIntent,
                'candidate_ids' => [$batchNineIntent],
                'confidence' => 1.0,
                'source' => 'deterministic_batch89',
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

    private function exactCatalogIntent(string $message): ?string
    {
        $normalized = $this->normalize($message);
        if ($normalized === '') {
            return null;
        }
        $matches = collect($this->catalog->records())->filter(function (array $record) use ($normalized): bool {
            $phrases = [
                ...(array) data_get($record, 'phrases.ordinary', []),
                ...(array) data_get($record, 'phrases.imperfect', []),
            ];

            return collect($phrases)->contains(fn (string $phrase): bool => $this->normalize($phrase) === $normalized);
        });

        return $matches->count() === 1 ? (string) $matches->first()['intent_id'] : null;
    }

    private function preparationIntent(string $message): ?string
    {
        $value = $this->normalize($message);
        if (preg_match('/\barchive\b.*\bprofile\b|\bprofile\b.*\barchive\b/', $value)) {
            return 'FAM-PROFILE-020';
        }
        if (preg_match('/\brestore\b.*\bprofile\b|\bprofile\b.*\brestore\b/', $value)) {
            return 'FAM-PROFILE-021';
        }
        if (preg_match('/\b(?:make|set|change|choose)\b.*\bdefault\b.*\bprofile\b|\bdefault\b.*\bprofile\b/', $value)) {
            return 'FAM-PROFILE-019';
        }
        if (preg_match('/\b(?:withdraw|cancel)\b.*\brequest\b/', $value)) {
            return 'FAM-REQUEST-038';
        }
        if (preg_match('/\b(?:reopen|restore|fresh copy)\b.*\b(?:expired|withdrawn|cancelled)?\s*request\b/', $value)) {
            return 'FAM-REQUEST-039';
        }
        $prepareVerb = preg_match('/\b(?:prepare|draft|write|send|create|copy|duplicate|reuse|update|change|edit|add|correct|question|dispute)\b/', $value) === 1;
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
        if (preg_match('/\b(?:update|change|edit|add|correct)\b.*\bprofile\b|\b(?:mobility|routine|safety|communication|allerg)\w*\b.*\b(?:note|profile|information)\w*\b/', $value)) {
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

    private function batchNineIntent(string $message): ?string
    {
        $value = $this->normalize($message);

        if (preg_match('/\b(?:24\s*\/\s*7|around[- ]the[- ]clock|continuous coverage)\b/', $value)) {
            return match (true) {
                preg_match('/\b(?:missed|unsafe|wrong|dispute|problem)\b/', $value) === 1 => 'FAM-COVERAGE-026',
                preg_match('/\b(?:receipt|refund|payment|charge)\b/', $value) === 1 => 'FAM-COVERAGE-024',
                default => 'FAM-COVERAGE-001',
            };
        }
        if (preg_match('/\b(?:queue|wait|how long)\b.*\bsupport\b|\bsupport\b.*\b(?:queue|wait|how long)\b/', $value)) {
            return 'FAM-SUPPORT-007';
        }
        if (preg_match('/\b(?:support|ticket)\b.*\bstatus\b|\bstatus\b.*\b(?:support|ticket)\b/', $value)) {
            return 'FAM-SUPPORT-005';
        }
        if (preg_match('/\b(?:privacy|delete my data|data request|what data)\b/', $value)) {
            return 'FAM-SUPPORT-013';
        }
        if (preg_match('/\b(?:change|update)\b.*\bmy name\b|\bmy name\b.*\b(?:change|update)\b/', $value)) {
            return 'FAM-ACCOUNT-007';
        }
        if (preg_match('/\b(?:verify|verification)\b.*\bemail\b|\bresend\b.*\bverification\b/', $value)) {
            return 'FAM-ACCOUNT-010';
        }
        if (preg_match('/\bwho\b.*\b(?:access|family account)\b|\b(?:family members|people with access)\b/', $value)) {
            return 'FAM-ACCESS-001';
        }
        if (preg_match('/\b(?:invite|add)\b.*\b(?:family|member|account)\b/', $value) && str_contains($value, '@')) {
            return 'FAM-ACCESS-004';
        }
        if (preg_match('/\bresend\b.*\b(?:family )?invitation\b/', $value)) {
            return 'FAM-ACCESS-007';
        }
        if (preg_match('/\bcancel\b.*\b(?:family )?invitation\b/', $value)) {
            return 'FAM-ACCESS-008';
        }
        if (preg_match('/\bremove\b.*\b(?:family )?member\b/', $value)) {
            return 'FAM-ACCESS-012';
        }
        if (preg_match('/\bleave\b.*\bfamily account\b/', $value)) {
            return 'FAM-ACCESS-013';
        }
        if (preg_match('/\bmark\b.*\ball\b.*\bnotifications?\b.*\bread\b/', $value)) {
            return 'FAM-COMMS-010';
        }
        if (preg_match('/\bmark\b.*\b(?:latest|this)\b.*\bnotification\b.*\bread\b/', $value)) {
            return 'FAM-COMMS-009';
        }
        if (preg_match('/\b(?:turn|switch|disable|enable|stop)\b.*\b(?:email|in[- ]app)?\s*notifications?\b/', $value)) {
            return str_contains($value, 'email') ? 'FAM-COMMS-015' : 'FAM-COMMS-013';
        }
        if (preg_match('/\bcare history\b.*\b(?:total|hours?|paid|spent|charge)\b|\b(?:total|hours?|paid|spent)\b.*\bcare history\b/', $value)) {
            return 'FAM-HISTORY-004';
        }
        if (preg_match('/\b(?:open|show|search|find)\b.*\bcare history\b/', $value)) {
            return 'FAM-HISTORY-001';
        }

        return null;
    }

    private function batchFiveIntent(string $message): ?string
    {
        $value = $this->normalize($message);

        if (preg_match('/\b(?:delete|remove)\b.*\bprofile\b.*\b(?:permanent|permanently|forever)\b|\b(?:permanent|permanently)\b.*\b(?:delete|remove)\b.*\bprofile\b/', $value)) {
            return 'FAM-PROFILE-026';
        }
        if (preg_match('/\b(?:fresh\s+copy|reopen|restore)\b.*\b(?:expired|withdrawn|cancelled)\b.*\brequest\b|\b(?:expired|withdrawn|cancelled)\b.*\brequest\b.*\b(?:copy|reopen|restore)\b/', $value)) {
            return 'FAM-REQUEST-039';
        }
        if (preg_match('/\b(?:change|turn|convert)\b.*\b(?:one[- ]?time|regular|recurring|type)\b.*\brequest\b|\brequest\b.*\b(?:one[- ]?time|regular|recurring)\b.*\b(?:change|convert)\b/', $value)) {
            return 'FAM-REQUEST-037';
        }
        if (preg_match('/\b(?:edit|update|change)\b.*\b(?:live|open|published)\b.*\brequest\b|\b(?:edit|update|change)\b.*\b(?:date|time|duration|task|recipient|address|note)s?\b.*\b(?:live|open|published)?\s*request\b/', $value)) {
            return 'FAM-REQUEST-036';
        }
        if (preg_match('/\b(?:withdraw|cancel|take\s+down)\b.*\b(?:open|published)?\s*(?:care\s+)?request\b/', $value)) {
            return 'FAM-REQUEST-038';
        }
        if (preg_match('/\b(?:reuse|same\s+(?:request\s+)?as\s+last|same\s+as\s+last|last\s+request)\b/', $value)) {
            return 'FAM-REQUEST-020';
        }
        if (preg_match('/\b(?:duplicate|copy|make\s+another)\b.*\brequest\b|\brequest\b.*\b(?:duplicate|copy)\b/', $value)) {
            return 'FAM-REQUEST-040';
        }
        if (preg_match('/\b(?:status|stand|where\s+does)\b.*\brequest\b|\brequest\b.*\b(?:status|stand)\b/', $value)) {
            return 'FAM-REQUEST-034';
        }
        if (preg_match('/\b(?:caregiver\s+responses?|applicants?|applied|applications?)\b.*\brequest\b|\b(?:did|any|how\s+many|show)\b.*\b(?:caregivers?\s+)?(?:apply|applied|applicants?|responses?)\b.*\brequest\b/', $value)) {
            return 'FAM-REQUEST-035';
        }
        if (preg_match('/\b(?:create|start|need|want|book)\b.*\bone[- ]?time\b.*\b(?:care\s+)?request\b|\bone[- ]?time\b.*\b(?:care\s+)?request\b/', $value)) {
            return 'FAM-START-008';
        }
        if (preg_match('/\b(?:create|start|need|want|book)\b.*\b(?:regular|recurring|weekly)\b.*\b(?:care\s+)?request\b|\b(?:regular|recurring|weekly)\b.*\b(?:care\s+)?request\b/', $value)) {
            return 'FAM-START-009';
        }

        if (preg_match('/\b(?:restore|bring\s+back)\b.*\b(?:archived\s+)?(?:care\s+(?:receiver|recipient)\s+)?profile\b/', $value)) {
            return 'FAM-PROFILE-021';
        }
        if (preg_match('/\b(?:archive|hide)\b.*\bprofile\b/', $value)) {
            return 'FAM-PROFILE-020';
        }
        if (preg_match('/\b(?:default)\b.*\bprofile\b|\bprofile\b.*\bdefault\b/', $value)) {
            return 'FAM-PROFILE-019';
        }
        if (preg_match('/\b(?:mark|make|set|complete)\b.*\bprofile\b.*\bready\b|\bprofile\b.*\b(?:complete|ready)\b/', $value)) {
            return 'FAM-PROFILE-005';
        }
        if (preg_match('/\b(?:additional|extra|emergency)\s+contact\b|\bcontact\b.*\b(?:profile|phone|email)\b/', $value)) {
            return 'FAM-PROFILE-014';
        }
        if (preg_match('/\b(?:safety|caregiver\s+qualit|caregiver\s+should\s+(?:do|avoid)|do\s+and\s+avoid)\w*\b/', $value)) {
            return 'FAM-PROFILE-013';
        }
        if (preg_match('/\b(?:routine|food|allerg|personal\s+care|overnight)\w*\b.*\b(?:profile|note|preference)s?\b|\b(?:edit|update|change)\b.*\b(?:routine|food|allerg|overnight)\w*\b/', $value)) {
            return 'FAM-PROFILE-012';
        }
        if (preg_match('/\b(?:mobility|walker|wheelchair|cane|transfer)\w*\b.*\b(?:profile|note|information)\w*\b|\bprofile\b.*\b(?:walker|wheelchair|cane|transfer)\w*\b|\b(?:edit|update|change|correct)\b.*\bmobility\b/', $value)) {
            return 'FAM-PROFILE-011';
        }
        if (preg_match('/\b(?:everyday\s+health|health\s+context|memory\s+context|everyday\s+memory)\b/', $value)) {
            return 'FAM-PROFILE-010';
        }
        if (preg_match('/\bcommunicat\w*\b.*\b(?:profile|note|preference|caregiver)\w*\b|\b(?:edit|update|change)\b.*\bcommunicat\w*\b/', $value)) {
            return 'FAM-PROFILE-009';
        }
        if (preg_match('/\b(?:description|interest|comfort|good\s+visit)\w*\b.*\b(?:profile|note)s?\b|\b(?:edit|update|change)\b.*\b(?:description|interest|comfort|good\s+visit)\w*\b/', $value)) {
            return 'FAM-PROFILE-008';
        }
        if (preg_match('/\b(?:preferred\s+name|full\s+name|date\s+of\s+birth|dob|pronoun|relationship)\w*\b.*\bprofile\b|\b(?:edit|update|change|correct)\b.*\b(?:preferred\s+name|full\s+name|date\s+of\s+birth|dob|pronoun|relationship)\w*\b/', $value)) {
            return 'FAM-PROFILE-007';
        }
        if (! preg_match('/\b(?:care\s+)?request\b/', $value)
            && preg_match('/\bcreate\b.*\b(?:care\s+(?:receiver|recipient)\s+)?profile\b|\bmake\b.*\bnew\b.*\bprofile\b|\badd\b.*\bcare\s+(?:receiver|recipient)\s+profile\b/', $value)) {
            return 'FAM-PROFILE-003';
        }

        return null;
    }

    private function batchSevenIntent(string $message): ?string
    {
        $value = $this->normalize($message);

        if (preg_match('/\b(?:replace|change)\b.*\bregular\b.*\bcaregiver\b|\bnew\s+caregiver\b.*\bregular\s+care\b/', $value)) {
            return 'FAM-REGULAR-026';
        }
        if (preg_match('/\bend\b.*\bregular\s+care\b.*\bcancel\b.*\bnext\b|\bcancel\b.*\bnext\b.*\bend\b.*\bregular\s+care\b/', $value)) {
            return 'FAM-REGULAR-023';
        }
        if (preg_match('/\b(?:end|stop)\b.*\b(?:regular|recurring|weekly)\s+care\b/', $value)) {
            return 'FAM-REGULAR-022';
        }
        if (preg_match('/\bresume\b.*\b(?:regular|recurring|weekly)\s+care\b/', $value)) {
            return 'FAM-REGULAR-021';
        }
        if (preg_match('/\bpause\b.*\b(?:regular|recurring|weekly)\s+care\b/', $value)) {
            return 'FAM-REGULAR-020';
        }
        if (preg_match('/\b(?:change|move|update)\b.*\b(?:regular|recurring|weekly)\b.*\bschedule\b/', $value)) {
            return 'FAM-REGULAR-019';
        }
        if (preg_match('/\bapprove\b.*\bcompleted\s+extra\s+visit\b|\bcompleted\s+extra\s+visit\b.*\bapprove\b/', $value)) {
            return 'FAM-REGULAR-014';
        }
        if (preg_match('/\b(?:add|request|book)\b.*\bextra\s+visit\b/', $value)) {
            return 'FAM-REGULAR-012';
        }
        if (preg_match('/\bskip\b.*\b(?:regular|weekly|recurring)?\s*(?:care\s+)?visit\b/', $value)) {
            return 'FAM-REGULAR-011';
        }
        if (preg_match('/\baccept\b.*\b(?:regular\s+care\s+)?counter(?:offer)?\b|\bcounteroffer\b.*\baccept\b/', $value)) {
            return 'FAM-REGULAR-008';
        }
        if (preg_match('/\b(?:counteroffer|counter\s+offer)\b/', $value)) {
            return 'FAM-REGULAR-007';
        }
        if (preg_match('/\b(?:set\s+up|start|offer)\b.*\b(?:regular|recurring|weekly)\s+care\b.*\b(?:caregiver|with)\b/', $value)) {
            return 'FAM-REGULAR-002';
        }
        if (preg_match('/\b(?:next|upcoming)\b.*\b(?:regular|recurring|weekly)\s+care\s+visit\b|\b(?:regular|recurring|weekly)\s+care\b.*\b(?:next|upcoming|scheduled)\s+visit\b/', $value)) {
            return 'FAM-REGULAR-009';
        }

        if (preg_match('/\b(?:book|hire)\b.*\b(?:same|again)\b.*\bcaregiver\b|\brebook\b.*\bcaregiver\b/', $value)) {
            return 'FAM-VISIT-032';
        }
        if (preg_match('/\b(?:reject|decline)\b.*\b(?:caregiver(?:\'s)?\s+change\s+request|(?:caregiver(?:\'s)?\s+)?(?:requested|proposed)?\s*(?:visit|schedule)\s+change)\b/', $value)) {
            return 'FAM-VISIT-011';
        }
        if (preg_match('/\baccept\b.*\b(?:caregiver(?:\'s)?\s+change\s+request|(?:caregiver(?:\'s)?\s+)?(?:requested|proposed)?\s*(?:visit|schedule)\s+change)\b/', $value)) {
            return 'FAM-VISIT-010';
        }
        if (preg_match('/\b(?:review|show|check)\b.*\bcaregiver(?:\'s)?\b.*\b(?:visit|schedule)\s+change\s+request\b/', $value)) {
            return 'FAM-VISIT-009';
        }
        if (preg_match('/\b(?:leave|submit|give)\b.*\b(?:[1-5]|one|two|three|four|five)\s*(?:star|stars)\b|\b(?:leave|write|submit|post)\b.*\breview\b.*\bcaregiver\b|\breview\b.*\bcaregiver\b.*\b(?:after|completed|finished)\b/', $value)) {
            return 'FAM-VISIT-030';
        }
        if (preg_match('/\bapprove\b.*\btime\s+correction\b|\btime\s+correction\b.*\bapprove\b/', $value)) {
            return 'FAM-VISIT-024';
        }
        if (preg_match('/\bapprove\b.*\b(?:submitted\s+)?hours\b|\b(?:submitted\s+)?hours\b.*\bapprove\b/', $value)) {
            return 'FAM-VISIT-020';
        }
        if (preg_match('/\b(?:mark|tell)\b.*\bvisit\b.*\b(?:complete|completed|ended)\b|\bvisit\b.*\bmark\b.*\bcomplete\b/', $value)) {
            return 'FAM-VISIT-017';
        }
        if (preg_match('/\b(?:no\s*show|didn\'?t\s+show|did\s+not\s+show)\b/', $value)) {
            return 'FAM-VISIT-014';
        }
        if (preg_match('/\b(?:cancel|cancellation)\b.*\b(?:scheduled\s+)?visit\b|\bvisit\b.*\b(?:cancel|cancellation)\b/', $value)) {
            return str_contains($value, 'request') ? 'FAM-VISIT-006' : 'FAM-VISIT-007';
        }
        if (preg_match('/\b(?:reschedule|move|change)\b.*\bvisit\b|\bvisit\b.*\b(?:reschedule|move)\b/', $value)) {
            return 'FAM-VISIT-005';
        }
        if (preg_match('/\b(?:current|today\'?s)\b.*\bvisit\b.*\b(?:status|happening|now|scheduled)\b|\bvisit\b.*\b(?:happening\s+now|current\s+status)\b/', $value)) {
            return 'FAM-VISIT-003';
        }
        if (preg_match('/\b(?:next|upcoming|today\'?s|current)\b.*\bvisit\b|\bwhen\b.*\bnext\b.*\bcaregiver\b/', $value)) {
            return 'FAM-VISIT-001';
        }

        if (preg_match('/\bhire\b.*\b(?:caregiver|applicant)\b|\bhire\s+[\p{L}][\p{L}\' -]*\b|\bselect\b.*\bcaregiver\b.*\brequest\b/u', $value)) {
            return 'FAM-MATCH-020';
        }
        if (preg_match('/\b(?:decline|reject|not\s+this)\b.*\b(?:caregiver|applicant)\b/', $value)) {
            return 'FAM-MATCH-016';
        }
        if (preg_match('/\b(?:shortlist|save)\b.*\b(?:caregiver|applicant)\b.*\b(?:later|follow|request)?\b/', $value)) {
            return 'FAM-MATCH-015';
        }
        if (preg_match('/\bcompare\b.*\b(?:caregiver|applicant)s?\b|\bwhich\b.*\bcaregiver\b.*\bchoose\b/', $value)) {
            return 'FAM-MATCH-014';
        }
        if (! str_contains($value, 'family') && preg_match('/\bcancel\b.*\binvitation\b/', $value)) {
            return 'FAM-MATCH-012';
        }
        if (preg_match('/\b(?:reinvite|invite\s+again)\b.*\bcaregiver\b/', $value)) {
            return 'FAM-MATCH-010';
        }
        if (preg_match('/\binvite\b.*\bcaregiver\b.*\brequest\b|\binvite\s+[\p{L}][\p{L}\' -]*\b.*\brequest\b|\binvite\b.*\bto\b.*\brequest\b/u', $value)) {
            return 'FAM-MATCH-008';
        }
        if (preg_match('/\b(?:send|write|message|tell)\b.*\b(?:caregiver|applicant)\b.*\b(?:message|say|tell|that)\b|\b(?:send|message|tell)\s+[\p{L}][\p{L}\' -]*\b.*\b(?:message|say|tell|that)\b|\bmessage\b.*\b(?:caregiver|applicant)\b/u', $value)) {
            return 'FAM-MATCH-018';
        }
        if (preg_match('/\b(?:who|caregiver|applicant)\b.*\b(?:applied|applications?|replied)\b/', $value)) {
            return 'FAM-MATCH-013';
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
