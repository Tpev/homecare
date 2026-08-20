<?php

namespace App\Services\AiSupport;

use DomainException;

class FamilyCareTypeDecisionService
{
    public const VERSION = 'family-care-type-decision-v1';

    /** @return array{path:string,reason:string,dates:list<string>}|null */
    public function decide(string $message, bool $careContext = false): ?array
    {
        $value = mb_strtolower(trim($message));
        if ($value === '') {
            return null;
        }

        if (preg_match('/\b(?:24\s*\/\s*7|round[- ]the[- ]clock|all day and all night|continuous day and night)\b/iu', $value)) {
            return ['path' => 'human_24_7', 'reason' => 'You need continuous day-and-night coverage.', 'dates' => []];
        }

        if (! $careContext && ! $this->looksLikeCareNeed($value)) {
            return null;
        }

        $weekly = preg_match('/\b(?:every|each)\s+(?:week|weekday|weekend|monday|tuesday|wednesday|thursday|friday|saturday|sunday)s?\b|\b(?:weekly|regular|recurring)\s+(?:care|visits?|help)\b|\b(?:\d+|once|twice|three)\s+(?:days?\s+)?a\s+week\b|\bongoing\b.{0,24}\b(?:week|care|visit)/iu', $value) === 1
            || preg_match('/\brepeat(?:s|ed|ing)?\s+(?:(?:each|every)\s*)?(?:week|monday|tuesday|wednesday|thursday|friday|saturday|sunday)s?\b/iu', $value) === 1;
        if (preg_match('/\b(?:not|isn\'t|is not|doesn\'t|does not)\b.{0,16}\b(?:every week|weekly|repeat)\b/iu', $value)) {
            $weekly = false;
        }
        if ($weekly) {
            return ['path' => 'recurring', 'reason' => 'You said the help repeats every week.', 'dates' => []];
        }

        $dates = $this->dateReferences($value);
        $separate = preg_match('/\b(?:separate|one[- ]off|only|but not weekly|not every week)\b/iu', $value) === 1;
        if (count($dates) >= 2 || ($separate && count($dates) >= 2)) {
            return [
                'path' => 'irregular_dates',
                'reason' => 'These sound like separate dates without a weekly pattern.',
                'dates' => $dates,
            ];
        }

        $oneTime = preg_match('/\bone[- ]time\s+care\b|\bone[- ]off\s+(?:need|care)\b|\b(?:one|single|one[- ]off|once|only|just)\b.{0,32}\b(?:visit|day|date|time|occasion|appointment|afternoon|morning|evening|sunday|monday|tuesday|wednesday|thursday|friday|saturday)\b|\b(?:one specific|not every week)\b|\b(?:tomorrow|today|this\s+(?:monday|tuesday|wednesday|thursday|friday|saturday|sunday)|next\s+(?:monday|tuesday|wednesday|thursday|friday|saturday|sunday))\b/iu', $value) === 1
            || count($dates) === 1;
        if ($oneTime) {
            return ['path' => 'one_time', 'reason' => 'You described one specific visit or date.', 'dates' => $dates];
        }

        return [
            'path' => 'clarify',
            'reason' => 'I need to know whether the help is for one date or repeats every week.',
            'dates' => [],
        ];
    }

    /** @return list<array{id:string,expected:string,message:string,care_context:bool}> */
    public function evaluationCases(): array
    {
        $manifest = require resource_path('ai-support/evaluations/family-goal-journeys-v1.php');
        if (($manifest['version'] ?? null) !== 'family-goal-journey-evals-v1') {
            throw new DomainException('The Family goal journey evaluation corpus version is invalid.');
        }

        return array_values((array) ($manifest['cases'] ?? []));
    }

    private function looksLikeCareNeed(string $value): bool
    {
        if (preg_match('/\b(?:payment|card|password|receipt|invoice|message|notification|timesheet|submitted hours|family member|profile|care plan|history|report|applicant|invite|hire|status|current visit|next visit|upcoming visit|visit issues?)\b/iu', $value)
            || preg_match('/\b(?:when\s+is|show|open|review)\b.{0,64}\b(?:care|visit|hours?|step|plan)\b/iu', $value)) {
            return false;
        }

        return preg_match('/\b(?:need|want|arrange|set up|looking for)\b.{0,48}\b(?:care|caregiver|help|visit|companionship)|\bneed\s+someone\b|\b(?:mother|father|mom|dad|parent|spouse)\s+needs?\b.{0,32}\b(?:care|help)|\b(?:care|help)\s+is\s+needed\b|\bcan\s+(?:a\s+)?caregiver\s+help\b|\bsomeone\s+(?:(?:must|should)\s+)?(?:come|be there)\b|\b(?:one[- ]time|one[- ]off|regular|recurring|weekly)\s+care\b/iu', $value) === 1;
    }

    /** @return list<string> */
    private function dateReferences(string $value): array
    {
        preg_match_all(
            '/\b(?:(?:today|tomorrow)|in\s+(?:(?:one|two|three|four|five|six)|\d+)\s+(?:days?|weeks?)|(?:(?:this|next|following)\s+)?(?:monday|tuesday|wednesday|thursday|friday|saturday|sunday)|(?:january|february|march|april|may|june|july|august|september|october|november|december)\s+\d{1,2}(?:,\s*\d{4})?|\d{1,2}[\/-]\d{1,2}(?:[\/-]\d{2,4})?)\b/iu',
            $value,
            $matches,
        );

        return array_values(array_unique(array_map(
            static fn (string $date): string => trim($date),
            (array) ($matches[0] ?? []),
        )));
    }
}
