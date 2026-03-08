<?php

namespace App\Services\AiCopilot;

use App\Contracts\AiCopilotResponder;
use App\Models\CareRequest;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class RuleBasedCopilotResponder implements AiCopilotResponder
{
    public function __construct(
        private readonly DraftNormalizer $normalizer,
        private readonly MissingFieldsResolver $missingFieldsResolver
    ) {
    }

    /** @var array<string,string> */
    private array $questions = [
        'request_type' => 'Is this a one-time job or a recurring schedule?',
        'title' => 'I can generate the title for you. If you prefer, tell me one key thing about this job.',
        'additional_info' => 'Any additional details that would help a caregiver succeed?',
        'scope_of_work' => 'What should the caregiver actually do during the shift? A short description is enough.',
        'time_expectations' => 'Any timing expectations (arrive early, strict routine, etc.)?',
        'home_access_notes' => 'How should the caregiver access the home?',
        'address_line1' => 'What is the service address line 1?',
        'city' => 'Which city is care needed in?',
        'state' => 'Which US state is this request in?',
        'zip' => 'What is the ZIP code?',
        'task_ids' => 'No need to be perfect: what kind of help is needed most (companionship, meal prep, transportation, reminders, housekeeping)?',
        'requested_start_at' => 'What date and time should care start?',
        'requested_end_at' => 'What date and time should care end?',
        'recurring_days' => 'Which days of week should this repeat?',
        'recurring_start_time' => 'What time does each recurring shift start?',
        'recurring_end_time' => 'What time does each recurring shift end?',
        'recurring_starts_on' => 'What date should recurring care begin?',
        'recipient.full_name' => 'What is the full name of the person receiving care?',
        'recipient.relationship_to_family' => 'What is your relationship to the care recipient?',
        'recipient.care_notes' => 'Any important care notes (mobility, reminders, routines)?',
        'third_party_contact.full_name' => 'What is the third-party contact full name?',
        'third_party_contact.relationship_to_recipient' => 'What is their relationship to the recipient?',
        'third_party_contact.phone' => 'What is their phone number?',
    ];

    public function generate(array $conversation, array $draft, array $missingRequired): array
    {
        $lastUserMessage = $this->latestUserMessage($conversation);
        $currentField = $missingRequired[0] ?? null;
        $updates = $this->extractUpdates($lastUserMessage, $draft, $missingRequired, $currentField);
        $merged = $this->normalizer->merge($draft, $updates);
        $missingAfter = $this->missingFieldsResolver->requiredMissing($merged);

        $next = $missingAfter[0] ?? null;
        $question = $next ? ($this->questions[$next] ?? 'What detail should I capture next?') : 'Great, I have what I need. Would you like me to prepare the final summary for publish?';
        $assistantMessage = $updates === []
            ? $question
            : 'Got it. '.$question;

        if ($updates === [] && $this->isUnsureMessage($lastUserMessage)) {
            $assistantMessage = $this->clarifyingPromptFor($next);
        }

        return [
            'assistant_message' => $assistantMessage,
            'field_updates' => $updates,
            'field_confidence' => [],
            'needs_confirmation' => [],
            'next_question' => $question,
            'quick_replies' => $this->quickRepliesFor($next),
            'safety_flags' => [],
            'quality_hints' => [],
            'model' => 'rule-based-fallback',
        ];
    }

    /**
     * @param  array<int,array{role:string,content:string}>  $conversation
     */
    private function latestUserMessage(array $conversation): string
    {
        for ($i = count($conversation) - 1; $i >= 0; $i--) {
            if (($conversation[$i]['role'] ?? '') === 'user') {
                return (string) ($conversation[$i]['content'] ?? '');
            }
        }

        return '';
    }

    /**
     * @param  array<string,mixed>  $draft
     * @param  array<int,string>  $missingRequired
     * @return array<string,mixed>
     */
    private function extractUpdates(string $message, array $draft, array $missingRequired, ?string $currentField): array
    {
        $message = trim($message);
        if ($message === '') {
            return [];
        }

        $lower = Str::lower($message);
        $updates = [];
        $direct = $this->directFieldAnswer($currentField, $message, $draft);
        if ($direct !== []) {
            $updates = array_replace_recursive($updates, $direct);
        }

        // Auto-request type hints.
        if (in_array('request_type', $missingRequired, true)) {
            if (str_contains($lower, 'recurring') || str_contains($lower, 'every ') || str_contains($lower, 'weekly')) {
                $updates['request_type'] = CareRequest::TYPE_RECURRING;
            } elseif (str_contains($lower, 'tomorrow') || str_contains($lower, 'next week') || str_contains($lower, 'today')) {
                $updates['request_type'] = CareRequest::TYPE_ONE_TIME;
            }
        }

        // Task extraction by keywords.
        $tasks = [];
        if (str_contains($lower, 'meal prep') || str_contains($lower, 'meal preparation')) {
            $tasks[] = 'Meal preparation';
        }
        if (str_contains($lower, 'companion') || str_contains($lower, 'companionship')) {
            $tasks[] = 'Companionship';
        }
        if (str_contains($lower, 'transport')) {
            $tasks[] = 'Transportation';
        }
        if (str_contains($lower, 'housekeeping') || str_contains($lower, 'clean')) {
            $tasks[] = 'Light housekeeping';
        }
        if ($tasks !== []) {
            $updates['tasks'] = $tasks;
        }

        // Parse simple date/time signals for one-time requests.
        $isOneTime = ($updates['request_type'] ?? Arr::get($draft, 'request_type')) === CareRequest::TYPE_ONE_TIME;
        if ($isOneTime) {
            $date = null;
            if (str_contains($lower, 'tomorrow')) {
                $date = Carbon::tomorrow();
            } elseif (str_contains($lower, 'next week')) {
                $date = Carbon::now()->addWeek();
            } elseif (str_contains($lower, 'today')) {
                $date = Carbon::today();
            }

            $times = $this->extractTimes($lower);
            if ($date && count($times) >= 2) {
                $start = $date->copy()->setTimeFromTimeString($times[0]);
                $end = $date->copy()->setTimeFromTimeString($times[1]);

                // User intent heuristic: if end is before start, assume the second time may be PM.
                if ($end->lessThanOrEqualTo($start)) {
                    $end = $end->copy()->addHours(12);
                }
                if ($end->lessThanOrEqualTo($start)) {
                    $end = $end->copy()->addDay();
                }

                $updates['requested_start_at'] = $start->format('Y-m-d H:i:s');
                $updates['requested_end_at'] = $end->format('Y-m-d H:i:s');
            }
        }

        // Relationship hints.
        if (! Arr::get($draft, 'recipient.relationship_to_family')) {
            if (str_contains($lower, 'my mom') || str_contains($lower, 'mother')) {
                Arr::set($updates, 'recipient.relationship_to_family', 'Mother');
            } elseif (str_contains($lower, 'my dad') || str_contains($lower, 'father')) {
                Arr::set($updates, 'recipient.relationship_to_family', 'Father');
            } elseif (str_contains($lower, 'spouse') || str_contains($lower, 'wife') || str_contains($lower, 'husband')) {
                Arr::set($updates, 'recipient.relationship_to_family', 'Spouse');
            }
        }

        // City/state hints.
        if (! Arr::get($draft, 'city') && str_contains($lower, 'raleigh')) {
            $updates['city'] = 'Raleigh';
        }
        if (! Arr::get($draft, 'state') && (str_contains($lower, 'north carolina') || str_contains($lower, ' nc'))) {
            $updates['state'] = 'NC';
        }

        // Auto-fill human-friendly title when missing.
        if (! Arr::get($draft, 'title') && in_array('title', $missingRequired, true)) {
            if (str_contains($lower, "i don't know") || str_contains($lower, 'dont know') || str_contains($lower, 'idk')) {
                $updates['title'] = $this->titleFromContext($updates, $draft);
            } elseif ($tasks !== []) {
                $updates['title'] = $this->titleFromContext($updates, $draft);
            } elseif (($currentField === 'title') && ! $this->isWeakTitleCandidate($message) && mb_strlen($message) >= 10) {
                $updates['title'] = Str::title($message);
            }
        }

        // When message is vague but includes "help", set broad scope to keep momentum.
        if (! Arr::get($draft, 'scope_of_work') && str_contains($lower, 'help')) {
            $updates['scope_of_work'] = 'General non-medical home care support based on family instructions.';
        }

        // Additional info and scope from message when missing.
        if (! Arr::get($draft, 'additional_info') && mb_strlen($message) >= 24) {
            $updates['additional_info'] = $message;
        }
        if (! Arr::get($draft, 'scope_of_work') && $tasks !== []) {
            $updates['scope_of_work'] = implode(', ', $tasks);
        }

        return $updates;
    }

    /**
     * @param  array<string,mixed>  $draft
     * @return array<string,mixed>
     */
    private function directFieldAnswer(?string $field, string $message, array $draft): array
    {
        if ($field === null) {
            return [];
        }

        $lower = Str::lower(trim($message));
        $isUnsure = $this->isUnsureMessage($message);

        return match ($field) {
            'request_type' => $this->directRequestType($lower),
            'time_expectations' => $this->directTimeExpectations($message, $isUnsure),
            'home_access_notes' => $this->directHomeAccess($message, $isUnsure),
            'scope_of_work' => $this->directScope($message, $isUnsure),
            'additional_info' => $this->directAdditionalInfo($message, $isUnsure),
            'task_ids' => $this->directTasks($lower, $message, $isUnsure),
            'recipient.relationship_to_family' => $this->directRelationship($lower),
            'title' => $this->directTitle($message, $draft, $isUnsure),
            default => [],
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function directRequestType(string $lower): array
    {
        if (str_contains($lower, 'recurr') || str_contains($lower, 'weekly') || str_contains($lower, 'every ')) {
            return ['request_type' => CareRequest::TYPE_RECURRING];
        }

        if (str_contains($lower, 'one time') || str_contains($lower, 'one-time') || str_contains($lower, 'once')
            || str_contains($lower, 'tomorrow') || str_contains($lower, 'next week') || str_contains($lower, 'appointment')) {
            return ['request_type' => CareRequest::TYPE_ONE_TIME];
        }

        return [];
    }

    /**
     * @return array<string,mixed>
     */
    private function directTimeExpectations(string $message, bool $isUnsure): array
    {
        if ($isUnsure || str_contains(Str::lower($message), 'not really') || str_contains(Str::lower($message), 'nope')) {
            return ['time_expectations' => 'No strict timing expectations.'];
        }

        return mb_strlen(trim($message)) >= 4 ? ['time_expectations' => trim($message)] : [];
    }

    /**
     * @return array<string,mixed>
     */
    private function directHomeAccess(string $message, bool $isUnsure): array
    {
        if ($isUnsure) {
            return ['home_access_notes' => 'Access details will be shared with the hired caregiver.'];
        }

        return mb_strlen(trim($message)) >= 4 ? ['home_access_notes' => trim($message)] : [];
    }

    /**
     * @return array<string,mixed>
     */
    private function directScope(string $message, bool $isUnsure): array
    {
        if ($isUnsure) {
            return ['scope_of_work' => 'General non-medical support and supervision based on family instructions.'];
        }

        if (str_contains(Str::lower($message), 'just in case')) {
            return ['scope_of_work' => 'Caregiver stays present for supervision and reassurance while family is away.'];
        }

        return mb_strlen(trim($message)) >= 8 ? ['scope_of_work' => trim($message)] : [];
    }

    /**
     * @return array<string,mixed>
     */
    private function directAdditionalInfo(string $message, bool $isUnsure): array
    {
        if ($isUnsure) {
            return ['additional_info' => 'Family will share details in chat before hire.'];
        }

        return mb_strlen(trim($message)) >= 8 ? ['additional_info' => trim($message)] : [];
    }

    /**
     * @return array<string,mixed>
     */
    private function directTasks(string $lower, string $message, bool $isUnsure): array
    {
        $tasks = [];
        if (str_contains($lower, 'meal')) {
            $tasks[] = 'Meal preparation';
        }
        if (str_contains($lower, 'companion') || str_contains($lower, 'stay with') || str_contains($lower, 'just in case')) {
            $tasks[] = 'Companionship';
        }
        if (str_contains($lower, 'transport') || str_contains($lower, 'drive')) {
            $tasks[] = 'Transportation';
        }
        if (str_contains($lower, 'house') || str_contains($lower, 'clean')) {
            $tasks[] = 'Light housekeeping';
        }
        if (str_contains($lower, 'reminder') || str_contains($lower, 'medication')) {
            $tasks[] = 'Medication reminders';
        }

        if ($tasks !== []) {
            return ['tasks' => array_values(array_unique($tasks))];
        }

        if ($isUnsure) {
            return ['tasks' => ['Companionship']];
        }

        if (mb_strlen(trim($message)) >= 4) {
            return ['tasks' => ['Companionship']];
        }

        return [];
    }

    /**
     * @return array<string,mixed>
     */
    private function directRelationship(string $lower): array
    {
        if (str_contains($lower, 'mom') || str_contains($lower, 'mother')) {
            return ['recipient' => ['relationship_to_family' => 'Mother']];
        }
        if (str_contains($lower, 'dad') || str_contains($lower, 'father')) {
            return ['recipient' => ['relationship_to_family' => 'Father']];
        }
        if (str_contains($lower, 'wife') || str_contains($lower, 'husband') || str_contains($lower, 'spouse')) {
            return ['recipient' => ['relationship_to_family' => 'Spouse']];
        }
        if (str_contains($lower, 'grandma') || str_contains($lower, 'grandmother')) {
            return ['recipient' => ['relationship_to_family' => 'Grandmother']];
        }
        if (str_contains($lower, 'grandpa') || str_contains($lower, 'grandfather')) {
            return ['recipient' => ['relationship_to_family' => 'Grandfather']];
        }

        return [];
    }

    /**
     * @param  array<string,mixed>  $draft
     * @return array<string,mixed>
     */
    private function directTitle(string $message, array $draft, bool $isUnsure): array
    {
        if ($isUnsure || $this->isWeakTitleCandidate($message)) {
            return ['title' => $this->titleFromContext([], $draft)];
        }

        if (mb_strlen(trim($message)) >= 10) {
            return ['title' => Str::title(trim($message))];
        }

        return ['title' => $this->titleFromContext([], $draft)];
    }

    /**
     * @return array<int,string>
     */
    private function extractTimes(string $text): array
    {
        preg_match_all('/\b(\d{1,2})(?:[:\.](\d{2}))?\s*(am|pm)\b/i', $text, $matches, PREG_SET_ORDER);
        $times = [];
        foreach ($matches as $match) {
            $hour = (int) $match[1];
            $minute = isset($match[2]) && $match[2] !== '' ? (int) $match[2] : 0;
            $ampm = Str::lower((string) $match[3]);

            if ($hour === 12) {
                $hour = $ampm === 'am' ? 0 : 12;
            } elseif ($ampm === 'pm') {
                $hour += 12;
            }

            $times[] = sprintf('%02d:%02d:00', $hour, $minute);
        }

        return $times;
    }

    /**
     * @param  array<string,mixed>  $updates
     * @param  array<string,mixed>  $draft
     */
    private function titleFromContext(array $updates, array $draft): string
    {
        $tasks = Arr::get($updates, 'tasks', Arr::get($draft, 'tasks', []));
        $relationship = (string) Arr::get($updates, 'recipient.relationship_to_family', Arr::get($draft, 'recipient.relationship_to_family', ''));

        if (is_array($tasks) && $tasks !== []) {
            $first = (string) $tasks[0];
            if (count($tasks) > 1) {
                $base = $first.' and '.$tasks[1].' support';
                return $relationship !== '' ? $base.' for '.$relationship : $base;
            }

            $base = $first.' support request';
            return $relationship !== '' ? $base.' for '.$relationship : $base;
        }

        return $relationship !== '' ? 'Home care support for '.$relationship : 'Home care support request';
    }

    /**
     * @return array<int,string>
     */
    private function quickRepliesFor(?string $field): array
    {
        return match ($field) {
            'request_type' => ['One-time', 'Recurring'],
            'state' => ['NC', 'SC', 'VA'],
            'task_ids' => ['Companionship', 'Meal preparation', 'Transportation', 'Medication reminders'],
            'recipient.relationship_to_family' => ['Parent', 'Spouse', 'Self', 'Grandparent'],
            'recurring_days' => ['Mon Wed Fri', 'Tue Thu', 'Weekends'],
            default => [],
        };
    }

    private function isUnsureMessage(string $message): bool
    {
        $lower = Str::lower($message);
        return str_contains($lower, "i don't know")
            || str_contains($lower, 'dont know')
            || str_contains($lower, 'idk')
            || str_contains($lower, 'not sure')
            || str_contains($lower, 'not really')
            || str_contains($lower, 'whatever')
            || trim($lower) === '?'
            || trim($lower) === 'what?'
            || trim($lower) === 'what ?';
    }

    private function isWeakTitleCandidate(string $title): bool
    {
        $lower = trim(Str::lower($title));
        return $lower === ''
            || in_array($lower, ['not really', 'i don\'t know', 'dont know', 'idk', 'test', 'testtest', 'testestest', 'whatever'], true)
            || str_contains($lower, 'what');
    }

    private function clarifyingPromptFor(?string $field): string
    {
        return match ($field) {
            'task_ids' => 'No problem. Just tell me the main help needed: companionship, meal prep, transportation, reminders, or housekeeping.',
            'time_expectations' => 'No worries. You can say "no strict timing" or share something simple like "arrive 10 minutes early".',
            'home_access_notes' => 'You can keep this simple: door code, key under lockbox, or "I will share access details later".',
            'recipient.full_name' => 'No problem. What is your mom\'s full name?',
            default => 'No problem. A short answer is enough and I will handle the structure.',
        };
    }
}
