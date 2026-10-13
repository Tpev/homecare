<?php

namespace App\Services\AiSupport;

/** Keeps restrictions and questions ahead of keyword-based action matching. */
class FamilyActionIntentGuard
{
    private const ACTION = '(?:hire|hiring|invite|inviting|reject|decline|approve|approving|accept|charge|pay|cancel|withdraw|delete|archive|restore|resume|pause|end|stop|publish|publishing|post|confirm|proceed|submit|send|update|change|edit|create|book|skip|save|remove)';

    /** @return array{kind:string,intent_id:?string,guidance:?string,explanation:?string}|null */
    public function inspect(string $message): ?array
    {
        $value = mb_strtolower(str_replace(['’', '‘'], "'", strip_tags($message)));
        $value = trim((string) preg_replace('/\s+/', ' ', $value));
        $noAction = preg_match('/\b(?:don\'?t|do not|never)\s+(?:do\s+anything|make\s+(?:any\s+)?changes|go\s+ahead|take\s+(?:any\s+)?action)\b/u', $value) === 1
            || preg_match('/^(?:please\s+)?(?:no[, ]+)?(?:not yet|not now|wait|hold on)(?:[, ]+(?:not yet|not now))?(?:\s+please)?[.!]?$/u', $value) === 1;
        if (! $noAction && ! preg_match('/\b'.self::ACTION.'\b/u', $value)) {
            return null;
        }

        // Limit negation to a clause; "not this caregiver" remains an explicit
        // decline, and "cancel Monday's visit, not the plan" remains one visit.
        $negative = $noAction || preg_match('/\b(?:don\'?t|do not|never|without|not to|not ready to|not yet ready to)\b[^.!?;]{0,100}\b'.self::ACTION.'\b/u', $value) === 1
            || preg_match('/\b(?:not|no)\s+'.self::ACTION.'\b/u', $value) === 1
            || preg_match('/\b'.self::ACTION.'\b.{0,80}\bnot (?:yet|now)\b/u', $value) === 1;
        $readOnly = preg_match('/\b(?:just|only)\s+(?:(?:want|need)\s+to\s+)?(?:show|view|see|open|explain|compare|check|read|understand)\b/u', $value) === 1;
        $question = preg_match('/^(?:please\s+)?(?:how\s+(?:do|can|would|should|does)|what\s+(?:happens|would|does|is)|can\s+i|could\s+i|would\s+i|explain|tell\s+me\s+(?:how|what))\b/u', $value) === 1;
        if (! $negative && ! $readOnly && ! $question) {
            return null;
        }

        // Prefer what the person positively asks to see. Do not use a negated
        // subject ("don't cancel my plan") to override "show my applicants".
        $read = $value;
        if ($negative) {
            $clauses = preg_split('/[.!?;,]+|\b(?:but|instead|just|only|without)\b/u', $value) ?: [];
            $positive = array_filter($clauses, fn (string $clause): bool => preg_match('/\b(?:don\'?t|do not|never|without|not to|not ready to|not yet|not now)\b/u', $clause) !== 1
                && preg_match('/\b(?:show|view|see|open|explain|compare|check|read|when|what|how)\b/u', $clause) === 1);
            $read = implode(' ', $positive);
        }

        [$intent, $guidance] = match (true) {
            preg_match('/\b(?:applicants?|applications?|applied|caregiver responses?)\b/u', $read) === 1 => ['FAM-MATCH-013', FamilyGuidedAssistanceService::INTENT_REQUESTS],
            preg_match('/\b(?:timesheets?|hours|time correction)\b/u', $read) === 1 => ['FAM-VISIT-018', FamilyGuidedAssistanceService::INTENT_TIMESHEETS],
            preg_match('/\b(?:next|upcoming|current|scheduled|today)\b.*\bvisit\b/u', $read) === 1 => ['FAM-VISIT-001', FamilyGuidedAssistanceService::INTENT_VISITS],
            preg_match('/\b(?:care|payment|visit) history\b|\breceipts?\b/u', $read) === 1 => ['FAM-HISTORY-001', FamilyGuidedAssistanceService::INTENT_HISTORY],
            preg_match('/\b(?:care\s+)?profiles?\b/u', $read) === 1 => [null, FamilyGuidedAssistanceService::INTENT_PROFILES],
            preg_match('/\b(?:regular|recurring|weekly)\b/u', $read) === 1 => [null, FamilyGuidedAssistanceService::INTENT_REGULAR_CARE],
            preg_match('/\b(?:care\s+)?requests?\b/u', $read) === 1 => ['FAM-REQUEST-034', FamilyGuidedAssistanceService::INTENT_REQUESTS],
            default => [null, null],
        };

        $explanation = null;
        if ($question && ! $negative) {
            $explanation = match (true) {
                preg_match('/\bhir(?:e|ing)\b/u', $value) === 1 => 'Open Review applicants on your care request, choose a caregiver, and check the booking details and exact payment quote before confirming Hire.',
                preg_match('/\bapprov(?:e|ing)\b.*\bhours\b/u', $value) === 1 => 'Open the visit and review the submitted hours and exact payment amount. Confirm only when the hours are correct; report a time issue if they are not.',
                preg_match('/\b(?:publish|publishing|submit)\b.*\brequest\b/u', $value) === 1 => 'Review the person, help, schedule, and location in your care request before publishing. After posting, Find caregivers lets you invite people and review their replies.',
                default => null,
            };
        }

        return [
            'kind' => $negative ? 'explicit_restriction' : ($readOnly ? 'read_only_request' : 'information_question'),
            'intent_id' => $intent,
            'guidance' => $guidance,
            'explanation' => $explanation,
        ];
    }
}
