<?php

namespace App\Livewire\Family;

use App\Models\CareRequest;
use App\Models\CareTask;
use App\Models\FamilyHouseholdProfile;
use App\Models\FamilyRecipientProfile;
use App\Support\FamilyQuickRequestDraft;
use App\Support\FunnelTracker;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CreateCareRequestWizard extends Component
{
    public const MODE_FAST_TRACK = 'fast_track';
    public const MODE_COMPLETE_SETUP = 'complete_setup';
    public const CARE_FOR_SELF = 'self';
    public const CARE_FOR_OTHER = 'other';

    public int $step = 1;
    public int $totalSteps = 4;
    public bool $modeChosen = false;
    public ?int $lastRequestId = null;
    public array $lastRequestSummary = [];
    public bool $prefillApplied = false;
    public bool $savedProfilesApplied = false;
    public bool $hasSavedHouseholdProfile = false;
    public bool $hasSavedRecipientProfile = false;

    public string $request_mode = self::MODE_FAST_TRACK;

    public string $title = '';
    public string $additional_info = '';
    public string $scope_of_work = '';
    public string $time_expectations = '';
    public string $home_access_notes = '';
    public int $preferred_response_hours = 12;
    public string $request_type = CareRequest::TYPE_ONE_TIME;
    public string $requested_start_at = '';
    public string $requested_end_at = '';
    public string $requested_start_date = '';
    public string $requested_start_time = '';
    public string $requested_duration_minutes = '60';
    public array $recurring_days = [];
    public string $recurring_start_time = '';
    public string $recurring_end_time = '';
    public string $recurring_duration_minutes = '60';
    public string $recurring_starts_on = '';
    public string $recurring_ends_on = '';
    public string $address_line1 = '';
    public string $address_line2 = '';
    public string $city = '';
    public string $state = '';
    public string $zip = '';
    public array $selectedTasks = [];
    public array $taskNotes = [];

    public string $recipient_full_name = '';
    public string $recipient_date_of_birth = '';
    public string $recipient_gender = '';
    public string $recipient_mobility_level = '';
    public string $recipient_relationship_to_family = '';
    public string $recipient_care_notes = '';
    public string $care_for = self::CARE_FOR_OTHER;

    public bool $includeThirdPartyContact = false;
    public string $third_party_full_name = '';
    public string $third_party_relationship_to_recipient = '';
    public string $third_party_phone = '';
    public string $third_party_email = '';

    public array $taskOptions = [];
    public array $requestModeOptions = [
        ['label' => 'Fast Track (recommended)', 'value' => self::MODE_FAST_TRACK],
        ['label' => 'Complete Setup', 'value' => self::MODE_COMPLETE_SETUP],
    ];
    public array $requestTypeOptions = [
        ['label' => 'One-time job', 'value' => CareRequest::TYPE_ONE_TIME],
        ['label' => 'Recurring job', 'value' => CareRequest::TYPE_RECURRING],
    ];
    public array $careForOptions = [
        ['label' => 'Me', 'value' => self::CARE_FOR_SELF],
        ['label' => 'A family member', 'value' => self::CARE_FOR_OTHER],
    ];
    public array $dayOptions = [
        ['label' => 'Sun', 'value' => 0],
        ['label' => 'Mon', 'value' => 1],
        ['label' => 'Tue', 'value' => 2],
        ['label' => 'Wed', 'value' => 3],
        ['label' => 'Thu', 'value' => 4],
        ['label' => 'Fri', 'value' => 5],
        ['label' => 'Sat', 'value' => 6],
    ];

    public array $durationOptions = [];

    public array $genderOptions = [
        ['label' => 'Female', 'value' => 'female'],
        ['label' => 'Male', 'value' => 'male'],
        ['label' => 'Non-binary', 'value' => 'non_binary'],
        ['label' => 'Prefer not to say', 'value' => 'prefer_not_to_say'],
    ];

    public array $mobilityOptions = [
        ['label' => 'Independent', 'value' => 'independent'],
        ['label' => 'Needs standby support', 'value' => 'standby_support'],
        ['label' => 'Needs transfer assistance', 'value' => 'transfer_assistance'],
        ['label' => 'Wheelchair user', 'value' => 'wheelchair_user'],
        ['label' => 'Bedbound', 'value' => 'bedbound'],
    ];

    public array $usStates = [
        'AL' => 'Alabama', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California', 'CO' => 'Colorado',
        'CT' => 'Connecticut', 'DE' => 'Delaware', 'FL' => 'Florida', 'GA' => 'Georgia', 'ID' => 'Idaho',
        'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky',
        'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan',
        'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska',
        'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
        'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon',
        'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina', 'SD' => 'South Dakota',
        'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia',
        'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
        'DC' => 'District of Columbia',
    ];

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->can('create', CareRequest::class), 403);

        $this->taskOptions = CareTask::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();

        $this->durationOptions = $this->buildDurationOptions();

        $this->city = (string) ($user->city ?? '');
        $this->state = (string) ($user->state ?? '');

        $this->loadSavedProfiles();
        $this->applyHomepageQuickRequestDraft();

        $lastRequest = CareRequest::query()
            ->where('family_user_id', $user->id)
            ->with(['recipient:id,care_request_id,recipient_is_requester,full_name,relationship_to_family', 'thirdPartyContact:id,care_request_id,full_name'])
            ->latest('id')
            ->first();

        if ($lastRequest) {
            $this->lastRequestId = (int) $lastRequest->id;
            $this->lastRequestSummary = [
                'title' => (string) $lastRequest->title,
                'location' => trim(((string) $lastRequest->city).', '.((string) $lastRequest->state)),
                'request_type' => (string) $lastRequest->request_type,
                'recipient' => (string) ($lastRequest->recipient?->full_name ?? 'Care recipient'),
            ];
        }
    }

    public function getResolvedTitleProperty(): string
    {
        return trim($this->title) !== '' ? trim($this->title) : $this->buildDefaultTitle();
    }

    public function getRecipientIsRequesterProperty(): bool
    {
        return $this->care_for === self::CARE_FOR_SELF;
    }

    public function getResolvedRecipientNameProperty(): string
    {
        if ($this->recipientIsRequester) {
            return trim((string) (auth()->user()?->name ?? '')) ?: 'Care recipient';
        }

        return trim($this->recipient_full_name) !== '' ? trim($this->recipient_full_name) : 'Care recipient';
    }

    public function getResolvedRecipientRelationshipProperty(): string
    {
        if ($this->recipientIsRequester) {
            return 'Self';
        }

        return trim($this->recipient_relationship_to_family) !== ''
            ? trim($this->recipient_relationship_to_family)
            : 'Family member';
    }

    public function getEstimateHourlyRateProperty(): float
    {
        $configuredRate = config('marketplace.family_estimate_hourly_rate');

        if (is_numeric($configuredRate) && (float) $configuredRate > 0) {
            return round((float) $configuredRate, 2);
        }

        return 30.00;
    }

    public function getEstimatedHoursProperty(): ?float
    {
        if ($this->request_type === CareRequest::TYPE_ONE_TIME) {
            $range = $this->oneTimeScheduleRange();
            if ($range === null) {
                return null;
            }

            [$start, $end] = $range;
            if ($end->lte($start)) {
                return null;
            }

            return round($start->diffInMinutes($end) / 60, 2);
        }

        $hoursPerShift = $this->recurringHoursPerShift();
        if ($hoursPerShift === null) {
            return null;
        }

        $daysCount = count($this->normalizedRecurringDays());
        if ($daysCount < 1) {
            return null;
        }

        return round($hoursPerShift * $daysCount, 2);
    }

    public function getEstimatedCostProperty(): ?float
    {
        if ($this->estimatedHours === null) {
            return null;
        }

        return round($this->estimatedHours * $this->estimateHourlyRate, 2);
    }

    public function getMinimumStartDateProperty(): string
    {
        return now()->toDateString();
    }

    public function getScheduleSummaryProperty(): string
    {
        if ($this->request_type === CareRequest::TYPE_RECURRING) {
            $days = collect($this->dayOptions)
                ->whereIn('value', $this->normalizedRecurringDays())
                ->pluck('label')
                ->implode(', ');

            $time = $this->formatTimeLabel($this->recurring_start_time);
            $duration = $this->durationLabel((int) $this->recurring_duration_minutes);
            $startsOn = null;
            if (trim($this->recurring_starts_on) !== '') {
                try {
                    $startsOn = Carbon::parse($this->recurring_starts_on)->format('M j, Y');
                } catch (Throwable) {
                    $startsOn = null;
                }
            }

            if ($days === '' || $time === null) {
                return 'Choose days and start time';
            }

            return trim($days.' at '.$time.' for '.$duration.($startsOn ? ' starting '.$startsOn : ''));
        }

        $range = $this->oneTimeScheduleRange();
        if ($range === null) {
            return 'Choose a day and start time';
        }

        [$start, $end] = $range;

        return $start->format('M j, Y').' at '.$start->format('g:i A').' for '.$this->durationLabel((int) $start->diffInMinutes($end));
    }

    public function getProgressPercentProperty(): int
    {
        return (int) round(($this->step / max($this->totalSteps, 1)) * 100);
    }

    public function prefillFromLastRequest(): void
    {
        if (! $this->lastRequestId) {
            return;
        }

        $request = CareRequest::query()
            ->where('family_user_id', auth()->id())
            ->with(['tasks:id,name', 'recipient', 'thirdPartyContact'])
            ->find($this->lastRequestId);

        if (! $request) {
            return;
        }

        $this->request_type = (string) ($request->request_type ?: CareRequest::TYPE_ONE_TIME);
        $this->title = (string) ($request->title ?? '');
        $this->additional_info = (string) ($request->additional_info ?? '');
        $this->scope_of_work = (string) ($request->scope_of_work ?? '');
        $this->time_expectations = (string) ($request->time_expectations ?? '');
        $this->home_access_notes = (string) ($request->home_access_notes ?? '');
        $this->preferred_response_hours = (int) ($request->preferred_response_hours ?: 12);

        if ($this->request_type === CareRequest::TYPE_ONE_TIME) {
            $this->requested_start_at = '';
            $this->requested_end_at = '';
            $this->requested_start_date = '';
            $this->requested_start_time = '';
            $this->requested_duration_minutes = '60';
            $this->recurring_days = [];
            $this->recurring_start_time = '';
            $this->recurring_end_time = '';
            $this->recurring_duration_minutes = '60';
            $this->recurring_starts_on = '';
            $this->recurring_ends_on = '';
        } else {
            $this->requested_start_at = '';
            $this->requested_end_at = '';
            $this->requested_start_date = '';
            $this->requested_start_time = '';
            $this->requested_duration_minutes = '60';
            $this->recurring_days = collect($request->recurring_days ?? [])->map(fn ($day) => (int) $day)->values()->all();
            $this->recurring_start_time = $this->normalizeTimeForInput((string) ($request->recurring_start_time ?? ''));
            $this->recurring_end_time = $this->normalizeTimeForInput((string) ($request->recurring_end_time ?? ''));
            $this->recurring_duration_minutes = (string) ($this->durationMinutesBetweenTimes($this->recurring_start_time, $this->recurring_end_time) ?? 60);
            $this->recurring_starts_on = '';
            $this->recurring_ends_on = '';
        }

        $this->address_line1 = (string) ($request->address_line1 ?? '');
        $this->address_line2 = (string) ($request->address_line2 ?? '');
        $this->city = (string) ($request->city ?? '');
        $this->state = (string) ($request->state ?? '');
        $this->zip = (string) ($request->zip ?? '');

        $this->selectedTasks = $request->tasks->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $this->taskNotes = [];
        foreach ($request->tasks as $task) {
            $note = trim((string) ($task->pivot?->task_note ?? ''));
            if ($note !== '') {
                $this->taskNotes[(int) $task->id] = $note;
            }
        }

        $this->recipient_full_name = (string) ($request->recipient?->full_name ?? '');
        $this->recipient_date_of_birth = (string) ($request->recipient?->date_of_birth ?? '');
        $this->recipient_gender = (string) ($request->recipient?->gender ?? '');
        $this->recipient_mobility_level = (string) ($request->recipient?->mobility_level ?? '');
        $this->recipient_relationship_to_family = (string) ($request->recipient?->relationship_to_family ?? '');
        $this->recipient_care_notes = (string) ($request->recipient?->care_notes ?? '');
        $this->care_for = $this->careForFromRecipient(
            (bool) ($request->recipient?->recipient_is_requester ?? false),
            $this->recipient_relationship_to_family
        );
        if ($this->recipientIsRequester && trim($this->recipient_full_name) === '') {
            $this->recipient_full_name = trim((string) (auth()->user()?->name ?? ''));
        }

        $this->includeThirdPartyContact = $request->thirdPartyContact !== null;
        $this->third_party_full_name = (string) ($request->thirdPartyContact?->full_name ?? '');
        $this->third_party_relationship_to_recipient = (string) ($request->thirdPartyContact?->relationship_to_recipient ?? '');
        $this->third_party_phone = (string) ($request->thirdPartyContact?->phone ?? '');
        $this->third_party_email = (string) ($request->thirdPartyContact?->email ?? '');

        $this->prefillApplied = true;
        session()->flash('status', 'Last request loaded. Update schedule and publish.');
    }

    public function applySavedProfiles(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $user->load(['familyHouseholdProfile', 'familyRecipientProfile']);

        $household = $user->familyHouseholdProfile;
        if ($household) {
            $this->address_line1 = (string) ($household->address_line1 ?? '');
            $this->address_line2 = (string) ($household->address_line2 ?? '');
            $this->city = (string) ($household->city ?? '');
            $this->state = (string) ($household->state ?? '');
            $this->zip = (string) ($household->zip ?? '');
            $this->home_access_notes = (string) ($household->home_access_notes ?? '');
            $this->time_expectations = (string) ($household->time_expectations ?? '');
            $this->preferred_response_hours = (int) ($household->preferred_response_hours ?: 12);
        }

        $recipient = $user->familyRecipientProfile;
        if ($recipient) {
            $this->recipient_full_name = (string) ($recipient->full_name ?? '');
            $this->recipient_date_of_birth = $recipient->date_of_birth?->toDateString() ?? '';
            $this->recipient_gender = (string) ($recipient->gender ?? '');
            $this->recipient_mobility_level = (string) ($recipient->mobility_level ?? '');
            $this->recipient_relationship_to_family = (string) ($recipient->relationship_to_family ?? '');
            $this->recipient_care_notes = (string) ($recipient->care_notes ?? '');
            $this->care_for = $this->careForFromRecipient(
                (bool) ($recipient->recipient_is_requester ?? false),
                $this->recipient_relationship_to_family
            );
            if ($this->recipientIsRequester && trim($this->recipient_full_name) === '') {
                $this->recipient_full_name = trim((string) ($user->name ?? ''));
            }
            $this->includeThirdPartyContact = (bool) ($recipient->include_third_party_contact ?? false);
            $this->third_party_full_name = (string) ($recipient->third_party_full_name ?? '');
            $this->third_party_relationship_to_recipient = (string) ($recipient->third_party_relationship_to_recipient ?? '');
            $this->third_party_phone = (string) ($recipient->third_party_phone ?? '');
            $this->third_party_email = (string) ($recipient->third_party_email ?? '');
        }

        $this->savedProfilesApplied = true;
        session()->flash('status', 'Saved household and recipient profiles loaded.');
    }

    public function nextStep(): void
    {
        if (! $this->modeChosen) {
            $this->modeChosen = true;
        }

        $this->syncScheduleFields();
        $this->validateStep($this->step);
        $this->step = min($this->step + 1, $this->totalSteps);
    }

    public function previousStep(): void
    {
        $this->step = max($this->step - 1, 1);
    }

    public function updatedRequestType(string $value): void
    {
        if ($value === CareRequest::TYPE_ONE_TIME) {
            $this->reset([
                'recurring_days',
                'recurring_start_time',
                'recurring_end_time',
                'recurring_duration_minutes',
                'recurring_starts_on',
                'recurring_ends_on',
            ]);
            $this->recurring_duration_minutes = '60';
            return;
        }

        $this->reset(['requested_start_at', 'requested_end_at', 'requested_start_date', 'requested_start_time']);
        $this->requested_duration_minutes = '60';
    }

    public function updatedCareFor(string $value): void
    {
        if ($value === self::CARE_FOR_SELF) {
            $this->recipient_full_name = trim((string) (auth()->user()?->name ?? ''));
            $this->recipient_relationship_to_family = 'Self';
            return;
        }

        if ($this->recipient_relationship_to_family === 'Self') {
            $this->recipient_relationship_to_family = '';
        }

        if ($this->recipient_full_name === trim((string) (auth()->user()?->name ?? ''))) {
            $this->recipient_full_name = '';
        }
    }

    public function chooseFastTrack(): void
    {
        $this->request_mode = self::MODE_FAST_TRACK;
        $this->modeChosen = true;
    }

    public function chooseCompleteSetup(): void
    {
        $this->request_mode = self::MODE_COMPLETE_SETUP;
        $this->modeChosen = true;
    }

    public function publish(): void
    {
        $this->syncScheduleFields();
        $this->validateAll();
        $this->syncScheduleFields();

        $careRequest = DB::transaction(function () {
            $careRequest = CareRequest::query()->create([
                'family_user_id' => auth()->id(),
                'title' => $this->resolvedTitle,
                'additional_info' => trim($this->additional_info) ?: null,
                'scope_of_work' => $this->buildDefaultScope(),
                'time_expectations' => trim($this->time_expectations) !== '' ? trim($this->time_expectations) : 'Flexible schedule. Exact timing can be confirmed in chat.',
                'home_access_notes' => trim($this->home_access_notes) !== '' ? trim($this->home_access_notes) : 'Home access details will be shared after hire.',
                'preferred_response_hours' => $this->preferred_response_hours,
                'status' => CareRequest::STATUS_OPEN,
                'request_type' => $this->request_type,
                'requested_start_at' => $this->request_type === CareRequest::TYPE_ONE_TIME ? $this->requested_start_at : null,
                'requested_end_at' => $this->request_type === CareRequest::TYPE_ONE_TIME ? $this->requested_end_at : null,
                'recurring_days' => $this->request_type === CareRequest::TYPE_RECURRING ? $this->normalizedRecurringDays() : null,
                'recurring_start_time' => $this->request_type === CareRequest::TYPE_RECURRING ? $this->recurring_start_time : null,
                'recurring_end_time' => $this->request_type === CareRequest::TYPE_RECURRING ? $this->recurring_end_time : null,
                'recurring_starts_on' => $this->request_type === CareRequest::TYPE_RECURRING ? $this->recurring_starts_on : null,
                'recurring_ends_on' => $this->request_type === CareRequest::TYPE_RECURRING ? ($this->recurring_ends_on ?: null) : null,
                'address_line1' => trim($this->address_line1),
                'address_line2' => trim($this->address_line2) ?: null,
                'city' => trim($this->city),
                'state' => strtoupper($this->state),
                'zip' => trim($this->zip),
            ]);

            $attachPayload = [];
            foreach ($this->selectedTasks as $taskId) {
                $note = trim((string) ($this->taskNotes[$taskId] ?? ''));
                $attachPayload[(int) $taskId] = ['task_note' => $note !== '' ? $note : null];
            }
            $careRequest->tasks()->sync($attachPayload);

            $careRequest->recipient()->create([
                'recipient_is_requester' => $this->recipientIsRequester,
                'full_name' => $this->resolvedRecipientName,
                'date_of_birth' => $this->recipient_date_of_birth ?: null,
                'gender' => $this->recipient_gender ?: null,
                'mobility_level' => $this->recipient_mobility_level ?: null,
                'relationship_to_family' => $this->resolvedRecipientRelationship,
                'care_notes' => trim($this->recipient_care_notes) !== '' ? trim($this->recipient_care_notes) : (trim($this->additional_info) ?: null),
            ]);

            if ($this->includeThirdPartyContact) {
                $careRequest->thirdPartyContact()->create([
                    'full_name' => trim($this->third_party_full_name),
                    'relationship_to_recipient' => trim($this->third_party_relationship_to_recipient),
                    'phone' => trim($this->third_party_phone),
                    'email' => trim($this->third_party_email) ?: null,
                ]);
            }

            return $careRequest;
        });

        $this->saveFamilyProfiles();

        FunnelTracker::track('care_request_published', auth()->user(), $careRequest, [
            'request_type' => $careRequest->request_type,
            'tasks_count' => count($this->selectedTasks),
        ]);

        session()->flash('status', 'Care request is live. Caregivers can now apply.');
        $this->redirect(route('family.requests.show', $careRequest->id, false), navigate: true);
    }

    private function validateAll(): void
    {
        $this->validate(array_merge(
            $this->rulesForNeedAndServices(),
            $this->rulesForScheduleAndLocation(),
            $this->rulesForRecipient(),
            $this->rulesForThirdParty()
        ));
    }

    public function messages(): array
    {
        return [
            'selectedTasks.required' => 'Choose at least one kind of help.',
            'selectedTasks.min' => 'Choose at least one kind of help.',
            'requested_start_date.required' => 'Choose the day care should start.',
            'requested_start_time.required' => 'Choose the start time.',
            'requested_duration_minutes.required' => 'Choose how long the visit should last.',
            'requested_duration_minutes.in' => 'Choose a duration from the list.',
            'requested_start_at.after' => 'Start time must be in the future.',
            'recurring_days.required' => 'Choose at least one day of the week.',
            'recurring_days.min' => 'Choose at least one day of the week.',
            'recurring_start_time.required' => 'Choose the start time.',
            'recurring_duration_minutes.required' => 'Choose how long each visit should last.',
            'recurring_duration_minutes.in' => 'Choose a duration from the list.',
            'recurring_starts_on.required' => 'Choose the first day care should start.',
            'recurring_starts_on.after_or_equal' => 'The first day cannot be in the past.',
            'recurring_end_time.after' => 'Choose a shorter duration or an earlier start time.',
            'address_line1.required' => 'Enter the care address.',
            'city.required' => 'Enter the city.',
            'state.required' => 'Choose the state.',
            'zip.required' => 'Enter the ZIP code.',
            'recipient_full_name.required' => 'Enter the name of the person receiving care.',
        ];
    }

    private function validateStep(int $step): void
    {
        $rules = match ($step) {
            1 => $this->rulesForNeedAndServices(),
            2 => $this->rulesForScheduleAndLocation(),
            3 => array_merge($this->rulesForRecipient(), $this->rulesForThirdParty()),
            default => [],
        };

        if ($rules !== []) {
            $this->validate($rules);
        }
    }

    private function rulesForNeedAndServices(): array
    {
        $additionalInfoRules = $this->request_mode === self::MODE_COMPLETE_SETUP
            ? ['required', 'string', 'min:12', 'max:3000']
            : ['nullable', 'string', 'max:3000'];

        return [
            'request_type' => ['required', Rule::in([CareRequest::TYPE_ONE_TIME, CareRequest::TYPE_RECURRING])],
            'request_mode' => ['required', Rule::in([self::MODE_FAST_TRACK, self::MODE_COMPLETE_SETUP])],
            'title' => ['nullable', 'string', 'max:140'],
            'additional_info' => $additionalInfoRules,
            'scope_of_work' => ['nullable', 'string', 'max:3000'],
            'time_expectations' => ['nullable', 'string', 'max:255'],
            'home_access_notes' => ['nullable', 'string', 'max:3000'],
            'selectedTasks' => ['required', 'array', 'min:1'],
            'selectedTasks.*' => ['integer', Rule::exists('care_tasks', 'id')],
            'taskNotes.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function rulesForScheduleAndLocation(): array
    {
        $rules = [
            'request_type' => ['required', Rule::in([CareRequest::TYPE_ONE_TIME, CareRequest::TYPE_RECURRING])],
            'preferred_response_hours' => ['required', 'integer', 'min:1', 'max:72'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', Rule::in(array_keys($this->usStates))],
            'zip' => ['required', 'string', 'max:15'],
        ];

        if ($this->request_type === CareRequest::TYPE_ONE_TIME) {
            $rules['requested_start_date'] = ['required', 'date'];
            $rules['requested_start_time'] = ['required', 'date_format:H:i'];
            $rules['requested_duration_minutes'] = ['required', 'integer', Rule::in($this->durationMinuteValues())];
            $rules['requested_start_at'] = ['required', 'date', 'after:now'];
            $rules['requested_end_at'] = ['required', 'date', 'after:requested_start_at'];
        } else {
            $rules['recurring_days'] = ['required', 'array', 'min:1'];
            $rules['recurring_days.*'] = ['integer', Rule::in(range(0, 6))];
            $rules['recurring_start_time'] = ['required', 'date_format:H:i'];
            $rules['recurring_duration_minutes'] = ['required', 'integer', Rule::in($this->durationMinuteValues())];
            $rules['recurring_end_time'] = ['required', 'date_format:H:i', 'after:recurring_start_time'];
            $rules['recurring_starts_on'] = ['required', 'date', 'after_or_equal:today'];
            $rules['recurring_ends_on'] = ['nullable', 'date', 'after_or_equal:recurring_starts_on'];
        }

        return $rules;
    }

    private function normalizedRecurringDays(): array
    {
        return collect($this->recurring_days)
            ->map(fn ($day) => (int) $day)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function syncScheduleFields(): void
    {
        if ($this->request_type === CareRequest::TYPE_ONE_TIME) {
            if ((trim($this->requested_start_date) === '' || trim($this->requested_start_time) === '')
                && trim($this->requested_start_at) !== ''
            ) {
                $this->setOneTimeScheduleFromRange($this->requested_start_at, $this->requested_end_at);
            }

            $range = $this->oneTimeScheduleRange();
            if ($range === null) {
                return;
            }

            [$start, $end] = $range;
            $this->requested_start_at = $start->format('Y-m-d H:i:s');
            $this->requested_end_at = $end->format('Y-m-d H:i:s');

            return;
        }

        if (trim($this->recurring_start_time) !== ''
            && trim($this->recurring_end_time) !== ''
            && (int) $this->recurring_duration_minutes === 60
        ) {
            $derivedDuration = $this->durationMinutesBetweenTimes($this->recurring_start_time, $this->recurring_end_time);
            if ($derivedDuration !== null && $this->durationIsAllowed($derivedDuration)) {
                $this->recurring_duration_minutes = (string) $derivedDuration;
            }
        }

        $startMinutes = $this->timeStringToMinutes($this->recurring_start_time);
        $duration = $this->normalizedDurationMinutes($this->recurring_duration_minutes);

        if ($startMinutes === null || $duration === null) {
            return;
        }

        $endMinutes = $startMinutes + $duration;
        if ($endMinutes >= (24 * 60)) {
            $this->recurring_end_time = '';
            return;
        }

        $this->recurring_end_time = $this->minutesToTimeString($endMinutes);
    }

    /**
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}|null
     */
    private function oneTimeScheduleRange(): ?array
    {
        if (trim($this->requested_start_date) !== '' && trim($this->requested_start_time) !== '') {
            $start = $this->parseLocalDateAndTime($this->requested_start_date, $this->requested_start_time);
            $duration = $this->normalizedDurationMinutes($this->requested_duration_minutes);

            if ($start === null || $duration === null) {
                return null;
            }

            return [$start, $start->copy()->addMinutes($duration)];
        }

        if (trim($this->requested_start_at) === '' || trim($this->requested_end_at) === '') {
            return null;
        }

        try {
            $start = Carbon::parse($this->requested_start_at);
            $end = Carbon::parse($this->requested_end_at);
        } catch (Throwable) {
            return null;
        }

        return [$start, $end];
    }

    private function setOneTimeScheduleFromRange(string $startValue, string $endValue): void
    {
        $startValue = trim($startValue);
        if ($startValue === '') {
            $this->requested_start_at = '';
            $this->requested_end_at = '';
            $this->requested_start_date = '';
            $this->requested_start_time = '';
            $this->requested_duration_minutes = '60';

            return;
        }

        try {
            $start = Carbon::parse($startValue);
        } catch (Throwable) {
            return;
        }

        $duration = 60;
        if (trim($endValue) !== '') {
            try {
                $end = Carbon::parse($endValue);
                $diff = (int) $start->diffInMinutes($end, false);
                if ($this->durationIsAllowed($diff)) {
                    $duration = $diff;
                }
            } catch (Throwable) {
                $duration = 60;
            }
        }

        $this->requested_start_at = $start->format('Y-m-d H:i:s');
        $this->requested_end_at = $start->copy()->addMinutes($duration)->format('Y-m-d H:i:s');
        $this->requested_start_date = $start->toDateString();
        $this->requested_start_time = $start->format('H:i');
        $this->requested_duration_minutes = (string) $duration;
    }

    private function rulesForRecipient(): array
    {
        $recipientNameRules = $this->recipientIsRequester
            ? ['nullable', 'string', 'max:120']
            : ($this->request_mode === self::MODE_FAST_TRACK
            ? ['required', 'string', 'min:2', 'max:120']
            : ['nullable', 'string', 'max:120']);

        return [
            'care_for' => ['required', Rule::in([self::CARE_FOR_SELF, self::CARE_FOR_OTHER])],
            'recipient_full_name' => $recipientNameRules,
            'recipient_date_of_birth' => ['nullable', 'date', 'before:today'],
            'recipient_gender' => ['nullable', Rule::in(array_column($this->genderOptions, 'value'))],
            'recipient_mobility_level' => ['nullable', Rule::in(array_column($this->mobilityOptions, 'value'))],
            'recipient_relationship_to_family' => ['nullable', 'string', 'max:120'],
            'recipient_care_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function rulesForThirdParty(): array
    {
        if (! $this->includeThirdPartyContact) {
            return ['includeThirdPartyContact' => ['boolean']];
        }

        return [
            'includeThirdPartyContact' => ['boolean'],
            'third_party_full_name' => ['required', 'string', 'max:120'],
            'third_party_relationship_to_recipient' => ['required', 'string', 'max:120'],
            'third_party_phone' => ['required', 'string', 'max:30'],
            'third_party_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    private function buildDefaultTitle(): string
    {
        $taskPart = collect($this->selectedTaskLabels())->take(2)->implode(' and ');

        $timingPart = match ($this->request_type) {
            CareRequest::TYPE_RECURRING => 'Recurring care support',
            default => 'One-time care support',
        };

        if ($taskPart !== '') {
            return $timingPart.' for '.$taskPart;
        }

        return $timingPart.' request';
    }

    private function buildDefaultScope(): string
    {
        if (trim($this->scope_of_work) !== '') {
            return trim($this->scope_of_work);
        }

        $tasks = $this->selectedTaskLabels();

        if ($tasks !== []) {
            return 'Primary tasks: '.implode(', ', $tasks).'.';
        }

        return 'Non-medical home care support based on family instructions.';
    }

    private function careForFromRecipient(bool $recipientIsRequester, ?string $relationship): string
    {
        if ($recipientIsRequester || strtolower(trim((string) $relationship)) === 'self') {
            return self::CARE_FOR_SELF;
        }

        return self::CARE_FOR_OTHER;
    }

    private function loadSavedProfiles(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $user->load(['familyHouseholdProfile', 'familyRecipientProfile']);
        $this->hasSavedHouseholdProfile = $user->familyHouseholdProfile !== null;
        $this->hasSavedRecipientProfile = $user->familyRecipientProfile !== null;
    }

    private function applyHomepageQuickRequestDraft(): void
    {
        $draft = FamilyQuickRequestDraft::pull();

        if (! $draft) {
            return;
        }

        $this->request_mode = (string) ($draft['request_mode'] ?? self::MODE_FAST_TRACK);
        $this->modeChosen = (bool) ($draft['modeChosen'] ?? true);
        $this->step = (int) ($draft['step'] ?? 4);
        $this->request_type = (string) ($draft['request_type'] ?? CareRequest::TYPE_ONE_TIME);
        $careFor = (string) ($draft['care_for'] ?? self::CARE_FOR_OTHER);
        $this->care_for = in_array($careFor, [self::CARE_FOR_SELF, self::CARE_FOR_OTHER], true)
            ? $careFor
            : self::CARE_FOR_OTHER;
        $this->recipient_full_name = (string) ($draft['recipient_full_name'] ?? '');
        if ($this->recipientIsRequester && trim($this->recipient_full_name) === '') {
            $this->recipient_full_name = trim((string) (auth()->user()?->name ?? ''));
            $this->recipient_relationship_to_family = 'Self';
        }
        $this->selectedTasks = collect($draft['selectedTasks'] ?? [])->map(fn ($id) => (int) $id)->values()->all();
        $this->additional_info = (string) ($draft['additional_info'] ?? '');
        $this->setOneTimeScheduleFromRange(
            (string) ($draft['requested_start_at'] ?? ''),
            (string) ($draft['requested_end_at'] ?? '')
        );
        $this->address_line1 = (string) ($draft['address_line1'] ?? '');
        $this->city = (string) ($draft['city'] ?? $this->city);
        $this->state = (string) ($draft['state'] ?? $this->state);
        $this->zip = (string) ($draft['zip'] ?? '');

        session()->flash('status', 'Your homepage request draft is loaded. Review it, then publish when ready.');
    }

    private function saveFamilyProfiles(): void
    {
        $familyUserId = (int) auth()->id();

        FamilyHouseholdProfile::query()->updateOrCreate(
            ['family_user_id' => $familyUserId],
            [
                'address_line1' => trim($this->address_line1) !== '' ? trim($this->address_line1) : null,
                'address_line2' => trim($this->address_line2) !== '' ? trim($this->address_line2) : null,
                'city' => trim($this->city) !== '' ? trim($this->city) : null,
                'state' => trim($this->state) !== '' ? strtoupper(trim($this->state)) : null,
                'zip' => trim($this->zip) !== '' ? trim($this->zip) : null,
                'home_access_notes' => trim($this->home_access_notes) !== '' ? trim($this->home_access_notes) : null,
                'time_expectations' => trim($this->time_expectations) !== '' ? trim($this->time_expectations) : null,
                'preferred_response_hours' => (int) $this->preferred_response_hours,
            ]
        );

        FamilyRecipientProfile::query()->updateOrCreate(
            ['family_user_id' => $familyUserId],
            [
                'recipient_is_requester' => $this->recipientIsRequester,
                'full_name' => $this->resolvedRecipientName !== 'Care recipient' ? $this->resolvedRecipientName : null,
                'date_of_birth' => $this->recipient_date_of_birth ?: null,
                'gender' => $this->recipient_gender ?: null,
                'mobility_level' => $this->recipient_mobility_level ?: null,
                'relationship_to_family' => $this->resolvedRecipientRelationship,
                'care_notes' => trim($this->recipient_care_notes) !== '' ? trim($this->recipient_care_notes) : null,
                'include_third_party_contact' => (bool) $this->includeThirdPartyContact,
                'third_party_full_name' => trim($this->third_party_full_name) !== '' ? trim($this->third_party_full_name) : null,
                'third_party_relationship_to_recipient' => trim($this->third_party_relationship_to_recipient) !== '' ? trim($this->third_party_relationship_to_recipient) : null,
                'third_party_phone' => trim($this->third_party_phone) !== '' ? trim($this->third_party_phone) : null,
                'third_party_email' => trim($this->third_party_email) !== '' ? trim($this->third_party_email) : null,
            ]
        );

        $this->hasSavedHouseholdProfile = true;
        $this->hasSavedRecipientProfile = true;
    }

    private function recurringHoursPerShift(): ?float
    {
        $startMinutes = $this->timeStringToMinutes($this->recurring_start_time);
        $duration = $this->normalizedDurationMinutes($this->recurring_duration_minutes);

        if ($startMinutes === null || $duration === null) {
            return null;
        }

        return round($duration / 60, 2);
    }

    private function buildDurationOptions(): array
    {
        return collect($this->durationMinuteValues())
            ->map(fn (int $minutes) => [
                'label' => $this->durationLabel($minutes),
                'value' => (string) $minutes,
            ])
            ->all();
    }

    private function durationMinuteValues(): array
    {
        return range(60, 720, 30);
    }

    private function durationIsAllowed(int $minutes): bool
    {
        return in_array($minutes, $this->durationMinuteValues(), true);
    }

    private function normalizedDurationMinutes(string|int|null $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $minutes = (int) $value;

        return $this->durationIsAllowed($minutes) ? $minutes : null;
    }

    private function durationLabel(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;
        $hhmm = sprintf('%02d:%02d', $hours, $remainingMinutes);

        if ($remainingMinutes === 0) {
            return $hhmm.' ('.$hours.' '.($hours === 1 ? 'hour' : 'hours').')';
        }

        return $hhmm.' ('.$hours.'h '.$remainingMinutes.'m)';
    }

    private function durationMinutesBetweenTimes(string $startTime, string $endTime): ?int
    {
        $startMinutes = $this->timeStringToMinutes($startTime);
        $endMinutes = $this->timeStringToMinutes($endTime);

        if ($startMinutes === null || $endMinutes === null || $endMinutes <= $startMinutes) {
            return null;
        }

        return $endMinutes - $startMinutes;
    }

    private function timeStringToMinutes(string $time): ?int
    {
        $value = trim($time);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{2}):(\d{2})(?::\d{2})?$/', $value, $matches) !== 1) {
            return null;
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];

        if ($hours > 23 || $minutes > 59) {
            return null;
        }

        return ($hours * 60) + $minutes;
    }

    private function minutesToTimeString(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return sprintf('%02d:%02d', $hours, $remainingMinutes);
    }

    private function parseLocalDateAndTime(string $date, string $time): ?Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d H:i', trim($date).' '.trim($time), config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }

    private function formatTimeLabel(string $time): ?string
    {
        $normalized = $this->normalizeTimeForInput($time);
        if ($normalized === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('H:i', $normalized)->format('g:i A');
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeTimeForInput(string $time): string
    {
        $trimmed = trim($time);
        if ($trimmed === '') {
            return '';
        }

        if (preg_match('/^\d{2}:\d{2}$/', $trimmed) === 1) {
            return $trimmed;
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $trimmed) === 1) {
            return substr($trimmed, 0, 5);
        }

        try {
            return Carbon::parse($trimmed)->format('H:i');
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @return array<int, string>
     */
    private function selectedTaskLabels(): array
    {
        $selected = collect($this->selectedTasks)
            ->map(fn ($value) => (int) $value)
            ->all();

        return collect($this->taskOptions)
            ->whereIn('id', $selected)
            ->pluck('name')
            ->values()
            ->all();
    }

    public function render()
    {
        return view('livewire.family.create-care-request-wizard');
    }
}
