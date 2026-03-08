<?php

namespace App\Services\AiCopilot;

use App\Models\CareRequest;
use Illuminate\Support\Arr;

class MissingFieldsResolver
{
    /**
     * @param  array<string,mixed>  $draft
     * @return array<int,string>
     */
    public function requiredMissing(array $draft): array
    {
        $missing = [];

        // 1) Core intent first.
        if (! $this->hasValue($draft, 'request_type')) {
            $missing[] = 'request_type';
            return $missing;
        }

        $type = (string) Arr::get($draft, 'request_type', '');
        if ($type === CareRequest::TYPE_ONE_TIME) {
            foreach (['requested_start_at', 'requested_end_at'] as $path) {
                if (! $this->hasValue($draft, $path)) {
                    $missing[] = $path;
                }
            }
        }

        if ($type === CareRequest::TYPE_RECURRING) {
            foreach (['recurring_days', 'recurring_start_time', 'recurring_end_time', 'recurring_starts_on'] as $path) {
                if (! $this->hasValue($draft, $path)) {
                    $missing[] = $path;
                }
            }
        }

        // 2) Core matching context.
        foreach ([
            'task_ids',
            'address_line1',
            'city',
            'state',
            'zip',
            'recipient.full_name',
            'recipient.relationship_to_family',
            'recipient.care_notes',
            'scope_of_work',
            'additional_info',
            'time_expectations',
            'home_access_notes',
        ] as $path) {
            if (! $this->hasValue($draft, $path)) {
                $missing[] = $path;
            }
        }

        // 3) Optional branch when booked by third party.
        if ((bool) Arr::get($draft, 'include_third_party_contact', false)) {
            foreach ([
                'third_party_contact.full_name',
                'third_party_contact.relationship_to_recipient',
                'third_party_contact.phone',
            ] as $path) {
                if (! $this->hasValue($draft, $path)) {
                    $missing[] = $path;
                }
            }
        }

        // 4) Title is last because it can be generated automatically.
        if (! $this->hasValue($draft, 'title')) {
            $missing[] = 'title';
        }

        return array_values(array_unique($missing));
    }

    /**
     * @param  array<string,mixed>  $draft
     */
    private function hasValue(array $draft, string $path): bool
    {
        $value = Arr::get($draft, $path);
        if (is_array($value)) {
            return $value !== [];
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return $value !== null;
    }
}
