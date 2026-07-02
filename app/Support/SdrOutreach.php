<?php

namespace App\Support;

use App\Models\Lead;
use Illuminate\Support\Str;

class SdrOutreach
{
    public const SOURCE = 'sdr_import';

    /** @return array<string, string> */
    public static function outcomeOptions(): array
    {
        return [
            'resource_requested' => 'Send one-page resource',
            'meeting_requested' => 'Wants a follow-up conversation',
            'follow_up_later' => 'Interested, follow up later',
            'gatekeeper' => 'Reached gatekeeper / transferred',
            'left_voicemail' => 'Left voicemail',
            'no_answer' => 'No answer',
            'wrong_number' => 'Wrong number',
            'not_interested' => 'Not interested',
            'do_not_call' => 'Do not call',
        ];
    }

    public static function outcomeLabel(string $outcome): string
    {
        return self::outcomeOptions()[$outcome] ?? str($outcome)->replace('_', ' ')->title()->toString();
    }

    public static function stageForOutcome(string $outcome): string
    {
        return match ($outcome) {
            'resource_requested', 'follow_up_later' => 'nurturing',
            'meeting_requested' => 'meeting_scheduled',
            'wrong_number', 'not_interested' => 'not_fit',
            'do_not_call' => 'closed',
            default => 'outreach',
        };
    }

    public static function closedReasonForOutcome(string $outcome): ?string
    {
        return match ($outcome) {
            'wrong_number' => 'Wrong number',
            'not_interested' => 'Not interested',
            'do_not_call' => 'Do not call',
            default => null,
        };
    }

    public static function defaultFollowUpForOutcome(string $outcome): ?\DateTimeInterface
    {
        return match ($outcome) {
            'resource_requested', 'follow_up_later' => now()->addDays(3),
            'gatekeeper' => now()->addDays(2),
            'left_voicemail', 'no_answer' => now()->addDay(),
            default => null,
        };
    }

    public static function zoomCallHref(?string $phone): ?string
    {
        $clean = self::cleanPhone($phone);

        return $clean ? 'zoomphonecall://'.$clean : null;
    }

    public static function telHref(?string $phone): ?string
    {
        $clean = self::cleanPhone($phone);

        return $clean ? 'tel:'.$clean : null;
    }

    public static function cleanPhone(?string $phone): string
    {
        $clean = preg_replace('/[^\d+]/', '', (string) $phone) ?: '';

        if (str_starts_with($clean, '00')) {
            $clean = '+'.substr($clean, 2);
        }

        return $clean;
    }

    /** @return list<string> */
    public static function normalizeTags(string|array|null $tags): array
    {
        if (is_array($tags)) {
            $pieces = $tags;
        } else {
            $pieces = preg_split('/[,;\n]+/', (string) $tags) ?: [];
        }

        return collect($pieces)
            ->map(fn ($tag) => Str::of((string) $tag)->trim()->lower()->replaceMatches('/\s+/', ' ')->toString())
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<string> */
    public static function leadTags(Lead $lead): array
    {
        return self::normalizeTags(data_get($lead->data, 'sdr.tags', []));
    }
}
