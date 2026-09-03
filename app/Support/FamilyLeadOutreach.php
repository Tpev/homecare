<?php

namespace App\Support;

use App\Models\Lead;
use Illuminate\Support\Carbon;

class FamilyLeadOutreach
{
    public const MAX_ATTEMPTS = 7;

    public const CALLABLE_STAGES = [
        'new',
        'attempting_contact',
        'callback_scheduled',
        'nurture',
    ];

    public const TERMINAL_STAGES = [
        'converted',
        'unreachable',
        'not_fit',
        'lost',
        'closed',
    ];

    /** @return array<string, string> */
    public static function outcomeOptions(): array
    {
        return [
            'no_answer' => 'No answer',
            'voicemail_left' => 'Voicemail left',
            'callback_requested' => 'Callback requested',
            'connected_qualified' => 'Connected — qualified',
            'assessment_booked' => 'Assessment booked',
            'not_ready' => 'Not ready — nurture',
            'not_eligible' => 'Not eligible / out of area',
            'wrong_number' => 'Wrong number',
            'duplicate' => 'Duplicate lead',
            'do_not_contact' => 'Do not contact',
        ];
    }

    public static function outcomeLabel(string $outcome): string
    {
        return self::outcomeOptions()[$outcome] ?? str($outcome)->replace('_', ' ')->title()->toString();
    }

    public static function isRetryable(string $outcome): bool
    {
        return in_array($outcome, ['no_answer', 'voicemail_left'], true);
    }

    public static function isConnected(string $outcome): bool
    {
        return in_array($outcome, [
            'callback_requested',
            'connected_qualified',
            'assessment_booked',
            'not_ready',
            'not_eligible',
            'do_not_contact',
        ], true);
    }

    public static function stageForOutcome(string $outcome, int $unansweredAttemptNumber): string
    {
        if (self::isRetryable($outcome)) {
            return $unansweredAttemptNumber >= self::MAX_ATTEMPTS ? 'unreachable' : 'attempting_contact';
        }

        return match ($outcome) {
            'callback_requested' => 'callback_scheduled',
            'connected_qualified' => 'qualified',
            'assessment_booked' => 'assessment_scheduled',
            'not_ready' => 'nurture',
            'not_eligible', 'wrong_number', 'duplicate' => 'not_fit',
            'do_not_contact' => 'closed',
            default => 'contacted',
        };
    }

    public static function closedReasonForOutcome(string $outcome, int $unansweredAttemptNumber): ?string
    {
        if (self::isRetryable($outcome) && $unansweredAttemptNumber >= self::MAX_ATTEMPTS) {
            return 'Unreachable after 7 call attempts';
        }

        return match ($outcome) {
            'not_eligible' => 'Not eligible / outside service area',
            'wrong_number' => 'Wrong number',
            'duplicate' => 'Duplicate lead',
            'do_not_contact' => 'Do not contact',
            default => null,
        };
    }

    public static function defaultFollowUpForOutcome(
        string $outcome,
        int $unansweredAttemptNumber,
        ?Lead $lead = null,
        ?Carbon $from = null,
    ): ?Carbon {
        $from ??= now();

        if (self::isRetryable($outcome) && $unansweredAttemptNumber < self::MAX_ATTEMPTS) {
            return self::nextRetryAt($lead, $unansweredAttemptNumber, $from);
        }

        if ($outcome === 'not_ready') {
            return $from->copy()->addWeeks(2);
        }

        return null;
    }

    public static function nextRetryAt(?Lead $lead, int $completedUnansweredAttempts, ?Carbon $from = null): Carbon
    {
        $from ??= now();
        $nextBusinessDay = $from->copy()->addWeekday()->startOfDay();
        $slots = self::cadenceSlots($lead);
        $slot = $slots[max(0, $completedUnansweredAttempts - 1) % count($slots)];
        [$hour, $minute] = array_map('intval', explode(':', $slot));

        return $nextBusinessDay->setTime($hour, $minute);
    }

    /** @return list<string> */
    public static function cadenceSlots(?Lead $lead): array
    {
        $preference = str((string) (
            data_get($lead?->data, 'form_answers.preferred_call_time')
            ?: data_get($lead?->data, 'callback_time_label')
            ?: data_get($lead?->data, 'callback_time')
        ))->lower()->toString();

        if (str_contains($preference, 'morning')) {
            return ['09:15', '16:30', '10:30', '13:15', '08:45', '17:15'];
        }

        if (str_contains($preference, 'afternoon') || str_contains($preference, 'after 4') || str_contains($preference, 'evening')) {
            return ['16:30', '11:30', '17:15', '09:30', '18:00', '13:30'];
        }

        return ['12:15', '17:15', '09:15', '14:30', '18:00', '10:30'];
    }

    public static function isCallable(Lead $lead): bool
    {
        return $lead->lead_type === Lead::TYPE_FAMILY
            && filled($lead->phone)
            && in_array($lead->status, self::CALLABLE_STAGES, true)
            && $lead->unanswered_attempt_count < self::MAX_ATTEMPTS
            && $lead->do_not_contact_at === null
            && ($lead->next_follow_up_at === null || $lead->next_follow_up_at->isPast());
    }

    public static function zoomCallHref(?string $phone): ?string
    {
        $clean = self::cleanPhone($phone);

        return $clean !== '' ? 'zoomphonecall://'.$clean : null;
    }

    public static function telHref(?string $phone): ?string
    {
        $clean = self::cleanPhone($phone);

        return $clean !== '' ? 'tel:'.$clean : null;
    }

    public static function cleanPhone(?string $phone): string
    {
        $clean = preg_replace('/[^\d+]/', '', (string) $phone) ?: '';

        if (str_starts_with($clean, '00')) {
            $clean = '+'.substr($clean, 2);
        }

        return $clean;
    }
}
