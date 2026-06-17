<?php

namespace App\Livewire\Family;

use App\Models\Lead;
use App\Services\Ops\OpsAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CallbackRequest extends Component
{
    public int $step = 1;

    public string $full_name = '';

    public string $phone = '';

    public string $email = '';

    public string $zip = '';

    public string $service_type = '';

    public string $recipient_relationship = '';

    public string $start_time = '';

    public string $visit_frequency = '';

    public string $visit_length = '';

    public string $callback_time = '';

    public string $notes = '';

    public bool $consent_to_contact = false;

    public bool $submitted = false;

    public string $landing_url = '';

    public ?string $landing_referrer_url = null;

    /** @var array<string, string> */
    public array $tracking = [];

    /** @var array<int, string> */
    public array $serviceOptions = [
        'Companion care',
        'Meal prep',
        'Errands and rides',
        'Light housekeeping',
        'Not sure yet',
    ];

    /** @var array<int, string> */
    public array $relationshipOptions = [
        'Myself',
        'Parent or older relative',
        'Spouse or partner',
        'Friend or neighbor',
        'Someone else',
    ];

    /** @var array<string, string> */
    public array $startOptions = [
        'asap' => 'As soon as possible',
        'this_week' => 'This week',
        'next_few_weeks' => 'In the next few weeks',
        'planning_ahead' => 'Planning ahead',
    ];

    /** @var array<string, string> */
    public array $frequencyOptions = [
        'one_visit' => 'One visit',
        'few_times_week' => 'A few times a week',
        'daily_or_most_days' => 'Daily or most days',
        'not_sure' => 'Not sure yet',
    ];

    /** @var array<string, string> */
    public array $visitLengthOptions = [
        'two_to_three_hours' => '2-3 hours',
        'half_day' => 'Half day',
        'full_day' => 'Full day',
        'not_sure' => 'Not sure yet',
    ];

    /** @var array<string, string> */
    public array $callbackOptions = [
        'today' => 'Today if possible',
        'today_afternoon' => 'Today afternoon',
        'tomorrow_morning' => 'Tomorrow morning',
        'tomorrow_afternoon' => 'Tomorrow afternoon',
        'this_week' => 'Later this week',
    ];

    public function mount(): void
    {
        $serviceType = (string) request()->query('service_type', '');
        $timePreference = (string) request()->query('time_preference', '');

        $this->landing_url = request()->fullUrl();
        $this->landing_referrer_url = request()->headers->get('referer');
        $this->tracking = $this->trackingFromRequest(request());

        if (in_array($serviceType, $this->serviceOptions, true)) {
            $this->service_type = $serviceType;
        }

        if (array_key_exists($timePreference, $this->callbackOptions)) {
            $this->callback_time = $timePreference;
        }

        $this->zip = trim((string) request()->query('zip', ''));
    }

    public function choose(string $field, string $value): void
    {
        if (! in_array($value, $this->optionValuesFor($field), true)) {
            return;
        }

        $this->{$field} = $value;
        $this->resetErrorBag($field);
        $this->step = min($this->contactStep(), $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function submit(): void
    {
        foreach ($this->questionSteps() as $index => $question) {
            $field = $question['field'];

            if (! filled($this->{$field})) {
                $this->step = $index + 1;
                $this->addError($field, 'Choose an option to continue.');

                return;
            }
        }

        $validated = $this->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'min:7', 'max:30'],
            'email' => ['nullable', 'email', 'max:160'],
            'zip' => ['required', 'string', 'max:12'],
            'service_type' => ['required', 'string', Rule::in($this->serviceOptions)],
            'recipient_relationship' => ['required', 'string', Rule::in($this->relationshipOptions)],
            'start_time' => ['required', 'string', Rule::in(array_keys($this->startOptions))],
            'visit_frequency' => ['required', 'string', Rule::in(array_keys($this->frequencyOptions))],
            'visit_length' => ['required', 'string', Rule::in(array_keys($this->visitLengthOptions))],
            'callback_time' => ['required', 'string', Rule::in(array_keys($this->callbackOptions))],
            'notes' => ['nullable', 'string', 'max:1200'],
            'consent_to_contact' => ['accepted'],
        ]);

        $source = $this->leadSource();
        $sourceDetail = $this->leadSourceDetail();

        $lead = Lead::query()->create([
            'lead_type' => 'family',
            'name' => trim($validated['full_name']),
            'email' => filled($validated['email']) ? trim($validated['email']) : null,
            'phone' => trim($validated['phone']),
            'location' => filled($validated['zip']) ? trim($validated['zip']) : null,
            'zip' => filled($validated['zip']) ? trim($validated['zip']) : null,
            'data' => [
                'source' => 'family_callback_page',
                'intent' => 'callback_request',
                'service_type' => $validated['service_type'],
                'recipient_relationship' => $validated['recipient_relationship'],
                'start_time' => $validated['start_time'],
                'start_time_label' => $this->startOptions[$validated['start_time']],
                'visit_frequency' => $validated['visit_frequency'],
                'visit_frequency_label' => $this->frequencyOptions[$validated['visit_frequency']],
                'visit_length' => $validated['visit_length'],
                'visit_length_label' => $this->visitLengthOptions[$validated['visit_length']],
                'callback_time' => $validated['callback_time'],
                'callback_time_label' => $this->callbackOptions[$validated['callback_time']],
                'notes' => filled($validated['notes']) ? trim($validated['notes']) : null,
                'starting_rate' => '$30/hr',
                'consent_to_contact' => true,
                'tracking' => $this->tracking,
                'meta_pixel_event' => 'Lead',
            ],
            'status' => 'new',
            'source' => $source,
            'source_detail' => $sourceDetail,
            'contact_role' => $validated['recipient_relationship'],
            'priority' => $validated['start_time'] === 'asap' ? Lead::PRIORITY_HIGH : Lead::PRIORITY_NORMAL,
            'external_source' => $source === 'meta_ads' ? 'meta_ads' : null,
            'source_url' => $this->landing_url ?: request()->fullUrl(),
            'referrer_url' => $this->landing_referrer_url ?: request()->headers->get('referer'),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        app(OpsAlertService::class)->notifyCallbackRequestCreated($lead);

        $this->submitted = true;

        $this->dispatch(
            'lolo-callback-submitted',
            lead_id: $lead->id,
            event_name: 'Lead',
            content_name: 'Family callback request',
            content_category: 'home_care_callback',
            value: 45,
            currency: 'USD',
        );
    }

    public function render()
    {
        $contactStep = $this->contactStep();

        return view('livewire.family.callback-request', [
            'contactStep' => $contactStep,
            'currentQuestion' => $this->currentQuestion(),
            'progressPercent' => (int) round(($this->step / $contactStep) * 100),
            'summary' => $this->summary(),
        ]);
    }

    public function contactStep(): int
    {
        return count($this->questionSteps()) + 1;
    }

    /**
     * @return array<int, array{field: string, eyebrow: string, title: string, body: string, options: array<int, array{value: string, title: string, body: string}>}>
     */
    private function questionSteps(): array
    {
        return [
            [
                'field' => 'service_type',
                'eyebrow' => 'Question 1',
                'title' => 'What kind of help would be most useful first?',
                'body' => 'Choose the closest fit. We can refine the care plan during the callback.',
                'options' => [
                    ['value' => 'Companion care', 'title' => 'Companionship', 'body' => 'Conversation, check-ins, walks, and calm time at home.'],
                    ['value' => 'Meal prep', 'title' => 'Meals and routines', 'body' => 'Simple meals, reminders, and everyday rhythm.'],
                    ['value' => 'Errands and rides', 'title' => 'Errands or rides', 'body' => 'Groceries, appointments, and getting around.'],
                    ['value' => 'Light housekeeping', 'title' => 'Light housekeeping', 'body' => 'A tidy home, laundry help, and small resets.'],
                    ['value' => 'Not sure yet', 'title' => 'Not sure yet', 'body' => 'Talk it through with a LoLo care coordinator.'],
                ],
            ],
            [
                'field' => 'recipient_relationship',
                'eyebrow' => 'Question 2',
                'title' => 'Who is the care for?',
                'body' => 'This helps us understand the family context before calling.',
                'options' => [
                    ['value' => 'Myself', 'title' => 'Myself', 'body' => 'A little backup for your own routines.'],
                    ['value' => 'Parent or older relative', 'title' => 'Parent or older relative', 'body' => 'Help for a loved one aging at home.'],
                    ['value' => 'Spouse or partner', 'title' => 'Spouse or partner', 'body' => 'Support for someone close at home.'],
                    ['value' => 'Friend or neighbor', 'title' => 'Friend or neighbor', 'body' => 'Helping someone in your community.'],
                    ['value' => 'Someone else', 'title' => 'Someone else', 'body' => 'Another situation we can discuss.'],
                ],
            ],
            [
                'field' => 'start_time',
                'eyebrow' => 'Question 3',
                'title' => 'When would support ideally start?',
                'body' => 'LoLo is not an emergency service, but timing helps us prioritize the callback.',
                'options' => [
                    ['value' => 'asap', 'title' => 'As soon as possible', 'body' => 'Help would be useful right away.'],
                    ['value' => 'this_week', 'title' => 'This week', 'body' => 'You are looking for a near-term option.'],
                    ['value' => 'next_few_weeks', 'title' => 'In the next few weeks', 'body' => 'You are starting to plan.'],
                    ['value' => 'planning_ahead', 'title' => 'Planning ahead', 'body' => 'You want to understand options early.'],
                ],
            ],
            [
                'field' => 'visit_frequency',
                'eyebrow' => 'Question 4',
                'title' => 'How often might help be needed?',
                'body' => 'A rough answer is enough for the first conversation.',
                'options' => [
                    ['value' => 'one_visit', 'title' => 'One visit', 'body' => 'Start with one clear first step.'],
                    ['value' => 'few_times_week', 'title' => 'A few times a week', 'body' => 'Regular support without a heavy commitment.'],
                    ['value' => 'daily_or_most_days', 'title' => 'Daily or most days', 'body' => 'A steadier rhythm of help at home.'],
                    ['value' => 'not_sure', 'title' => 'Not sure yet', 'body' => 'You want guidance before deciding.'],
                ],
            ],
            [
                'field' => 'visit_length',
                'eyebrow' => 'Question 5',
                'title' => 'How long should a first visit feel?',
                'body' => 'This helps us discuss realistic caregiver fit and budget.',
                'options' => [
                    ['value' => 'two_to_three_hours', 'title' => '2-3 hours', 'body' => 'A short check-in, meal, errand, or companion visit.'],
                    ['value' => 'half_day', 'title' => 'Half day', 'body' => 'More time for routines, errands, and company.'],
                    ['value' => 'full_day', 'title' => 'Full day', 'body' => 'Longer non-medical support during the day.'],
                    ['value' => 'not_sure', 'title' => 'Not sure yet', 'body' => 'We can help think through the first visit.'],
                ],
            ],
            [
                'field' => 'callback_time',
                'eyebrow' => 'Question 6',
                'title' => 'When is the best time for LoLo to call?',
                'body' => 'Last step after this: your contact details.',
                'options' => [
                    ['value' => 'today', 'title' => 'Today if possible', 'body' => 'Call as soon as a coordinator is available.'],
                    ['value' => 'today_afternoon', 'title' => 'Today afternoon', 'body' => 'A later call today works better.'],
                    ['value' => 'tomorrow_morning', 'title' => 'Tomorrow morning', 'body' => 'Morning is best.'],
                    ['value' => 'tomorrow_afternoon', 'title' => 'Tomorrow afternoon', 'body' => 'Afternoon is best.'],
                    ['value' => 'this_week', 'title' => 'Later this week', 'body' => 'You are not in a rush.'],
                ],
            ],
        ];
    }

    /**
     * @return array{field: string, eyebrow: string, title: string, body: string, options: array<int, array{value: string, title: string, body: string}>}|null
     */
    private function currentQuestion(): ?array
    {
        return $this->questionSteps()[$this->step - 1] ?? null;
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function summary(): array
    {
        return array_values(array_filter([
            $this->service_type ? ['label' => 'Support', 'value' => $this->service_type] : null,
            $this->recipient_relationship ? ['label' => 'For', 'value' => $this->recipient_relationship] : null,
            $this->start_time ? ['label' => 'Timing', 'value' => $this->startOptions[$this->start_time]] : null,
            $this->visit_frequency ? ['label' => 'Frequency', 'value' => $this->frequencyOptions[$this->visit_frequency]] : null,
            $this->visit_length ? ['label' => 'Visit length', 'value' => $this->visitLengthOptions[$this->visit_length]] : null,
            $this->callback_time ? ['label' => 'Callback', 'value' => $this->callbackOptions[$this->callback_time]] : null,
        ]));
    }

    /**
     * @return array<int, string>
     */
    private function optionValuesFor(string $field): array
    {
        return match ($field) {
            'service_type' => $this->serviceOptions,
            'recipient_relationship' => $this->relationshipOptions,
            'start_time' => array_keys($this->startOptions),
            'visit_frequency' => array_keys($this->frequencyOptions),
            'visit_length' => array_keys($this->visitLengthOptions),
            'callback_time' => array_keys($this->callbackOptions),
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    private function trackingFromRequest(Request $request): array
    {
        $keys = [
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
            'fbclid',
            'gclid',
            'msclkid',
            'campaign_id',
            'adset_id',
            'ad_id',
            'placement',
            'site_source_name',
        ];

        $tracking = [];

        foreach ($keys as $key) {
            $value = $request->query($key);

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $tracking[$key] = Str::limit(trim($value), 255, '');
        }

        return $tracking;
    }

    private function leadSource(): string
    {
        $utmSource = strtolower((string) ($this->tracking['utm_source'] ?? ''));

        if (
            str_contains($utmSource, 'meta')
            || str_contains($utmSource, 'facebook')
            || str_contains($utmSource, 'instagram')
            || array_key_exists('fbclid', $this->tracking)
        ) {
            return 'meta_ads';
        }

        if (str_contains($utmSource, 'google') || array_key_exists('gclid', $this->tracking)) {
            return 'google_ads';
        }

        if ($utmSource !== '') {
            return Str::limit($utmSource, 40, '');
        }

        return 'callback_page';
    }

    private function leadSourceDetail(): ?string
    {
        $parts = array_filter([
            $this->tracking['utm_campaign'] ?? null,
            $this->tracking['utm_term'] ?? null,
            $this->tracking['utm_content'] ?? null,
        ], fn (?string $value) => filled($value));

        if ($parts === []) {
            return null;
        }

        return Str::limit(implode(' / ', $parts), 255, '');
    }
}
