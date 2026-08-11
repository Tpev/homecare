<?php

namespace App\Services\AiCopilot;

use App\Models\CareRequest;
use App\Models\CareTask;
use App\Support\WeeklySchedule;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DraftNormalizer
{
    /** @var array<string,string> */
    private array $stateByName = [
        'alabama' => 'AL', 'alaska' => 'AK', 'arizona' => 'AZ', 'arkansas' => 'AR', 'california' => 'CA',
        'colorado' => 'CO', 'connecticut' => 'CT', 'delaware' => 'DE', 'district of columbia' => 'DC',
        'florida' => 'FL', 'georgia' => 'GA', 'hawaii' => 'HI', 'idaho' => 'ID', 'illinois' => 'IL',
        'indiana' => 'IN', 'iowa' => 'IA', 'kansas' => 'KS', 'kentucky' => 'KY', 'louisiana' => 'LA',
        'maine' => 'ME', 'maryland' => 'MD', 'massachusetts' => 'MA', 'michigan' => 'MI', 'minnesota' => 'MN',
        'mississippi' => 'MS', 'missouri' => 'MO', 'montana' => 'MT', 'nebraska' => 'NE', 'nevada' => 'NV',
        'new hampshire' => 'NH', 'new jersey' => 'NJ', 'new mexico' => 'NM', 'new york' => 'NY',
        'north carolina' => 'NC', 'north dakota' => 'ND', 'ohio' => 'OH', 'oklahoma' => 'OK', 'oregon' => 'OR',
        'pennsylvania' => 'PA', 'rhode island' => 'RI', 'south carolina' => 'SC', 'south dakota' => 'SD',
        'tennessee' => 'TN', 'texas' => 'TX', 'utah' => 'UT', 'vermont' => 'VT', 'virginia' => 'VA',
        'washington' => 'WA', 'west virginia' => 'WV', 'wisconsin' => 'WI', 'wyoming' => 'WY',
    ];

    /**
     * @param  array<string,mixed>  $current
     * @param  array<string,mixed>  $updates
     * @return array<string,mixed>
     */
    public function merge(array $current, array $updates): array
    {
        $merged = array_replace_recursive($current, $updates);

        $merged['request_type'] = $this->normalizeRequestType((string) Arr::get($merged, 'request_type', ''));
        $merged['state'] = $this->normalizeState((string) Arr::get($merged, 'state', ''));
        $merged['preferred_response_hours'] = $this->normalizeResponseHours(Arr::get($merged, 'preferred_response_hours'));
        $merged['include_third_party_contact'] = (bool) Arr::get($merged, 'include_third_party_contact', false);
        $merged['recurring_days'] = $this->normalizeRecurringDays(Arr::get($merged, 'recurring_days', []));
        $merged['task_ids'] = $this->normalizeTaskIds($merged);

        foreach ([
            'title', 'additional_info', 'scope_of_work', 'time_expectations', 'home_access_notes',
            'address_line1', 'address_line2', 'city', 'zip',
            'requested_start_at', 'requested_end_at', 'recurring_start_time', 'recurring_end_time',
            'recurring_starts_on', 'recurring_ends_on',
        ] as $path) {
            Arr::set($merged, $path, $this->stringOrNull(Arr::get($merged, $path)));
        }

        $recurringSchedule = WeeklySchedule::normalize(
            Arr::get($merged, 'recurring_schedule'),
            $merged['recurring_days'],
            Arr::get($merged, 'recurring_start_time'),
            Arr::get($merged, 'recurring_end_time'),
        );
        if ($recurringSchedule !== []) {
            $first = WeeklySchedule::first($recurringSchedule);
            $merged['recurring_schedule'] = $recurringSchedule;
            $merged['recurring_days'] = WeeklySchedule::days($recurringSchedule);
            $merged['recurring_start_time'] = $first['start_time'];
            $merged['recurring_end_time'] = $first['end_time'];
        }

        foreach ([
            'recipient.full_name', 'recipient.date_of_birth', 'recipient.gender',
            'recipient.mobility_level', 'recipient.relationship_to_family', 'recipient.care_notes',
            'third_party_contact.full_name', 'third_party_contact.relationship_to_recipient',
            'third_party_contact.phone', 'third_party_contact.email',
        ] as $path) {
            Arr::set($merged, $path, $this->stringOrNull(Arr::get($merged, $path)));
        }

        if (! is_array(Arr::get($merged, 'recipient'))) {
            $merged['recipient'] = [];
        }

        if (! is_array(Arr::get($merged, 'third_party_contact'))) {
            $merged['third_party_contact'] = [];
        }

        return $merged;
    }

    private function normalizeRequestType(string $value): ?string
    {
        $value = trim(Str::lower($value));
        if (in_array($value, [CareRequest::TYPE_ONE_TIME, CareRequest::TYPE_RECURRING], true)) {
            return $value;
        }

        return match ($value) {
            'one-time', 'one time', 'single', 'single_shift' => CareRequest::TYPE_ONE_TIME,
            'recurring', 'weekly', 'repeat' => CareRequest::TYPE_RECURRING,
            default => null,
        };
    }

    private function normalizeState(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (strlen($value) === 2) {
            return strtoupper($value);
        }

        return $this->stateByName[Str::lower($value)] ?? null;
    }

    private function normalizeResponseHours(mixed $value): int
    {
        $hours = (int) $value;
        if ($hours < 1) {
            return 12;
        }

        return min(72, $hours);
    }

    /**
     * @return array<int,int>
     */
    private function normalizeRecurringDays(mixed $days): array
    {
        if (! is_array($days)) {
            return [];
        }

        return collect($days)
            ->map(fn ($day) => (int) $day)
            ->filter(fn (int $day) => $day >= 0 && $day <= 6)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<string,mixed>  $draft
     * @return array<int,int>
     */
    private function normalizeTaskIds(array $draft): array
    {
        $ids = collect(Arr::get($draft, 'task_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0);

        $taskNames = Arr::get($draft, 'tasks', []);
        if (is_array($taskNames) && $taskNames !== []) {
            $map = CareTask::query()
                ->get(['id', 'name'])
                ->mapWithKeys(fn (CareTask $task) => [Str::lower($task->name) => (int) $task->id]);

            foreach ($taskNames as $taskName) {
                $normalized = Str::lower(trim((string) $taskName));
                if ($normalized === '') {
                    continue;
                }

                if ($map->has($normalized)) {
                    $ids->push((int) $map->get($normalized));

                    continue;
                }

                // Soft contains match, e.g. "meal prep" -> "Meal preparation"
                $matched = $map->first(fn (int $id, string $name) => str_contains($name, $normalized) || str_contains($normalized, $name));
                if ($matched) {
                    $ids->push((int) $matched);
                }
            }
        }

        return $ids->unique()->values()->all();
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
