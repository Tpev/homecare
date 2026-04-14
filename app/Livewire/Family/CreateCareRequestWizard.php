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
    public array $recurring_days = [];
    public string $recurring_start_time = '';
    public string $recurring_end_time = '';
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
    public array $dayOptions = [
        ['label' => 'Sun', 'value' => 0],
        ['label' => 'Mon', 'value' => 1],
        ['label' => 'Tue', 'value' => 2],
        ['label' => 'Wed', 'value' => 3],
        ['label' => 'Thu', 'value' => 4],
        ['label' => 'Fri', 'value' => 5],
        ['label' => 'Sat', 'value' => 6],
    ];

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

        $this->city = (string) ($user->city ?? '');
        $this->state = (string) ($user->state ?? '');

        $this->loadSavedProfiles();
        $this->applyHomepageQuickRequestDraft();

        $lastRequest = CareRequest::query()
            ->where('family_user_id', $user->id)
            ->with(['recipient:id,care_request_id,full_name,relationship_to_family', 'thirdPartyContact:id,care_request_id,full_name'])
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
            if (trim($this->requested_start_at) === '' || trim($this->requested_end_at) === '') {
                return null;
            }

            try {
                $start = Carbon::parse($this->requested_start_at);
                $end = Carbon::parse($this->requested_end_at);
            } catch (Throwable) {
                return null;
            }

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
            $this->recurring_days = [];
            $this->recurring_start_time = '';
            $this->recurring_end_time = '';
            $this->recurring_starts_on = '';
            $this->recurring_ends_on = '';
        } else {
            $this->requested_start_at = '';
            $this->requested_end_at = '';
            $this->recurring_days = collect($request->recurring_days ?? [])->map(fn ($day) => (int) $day)->values()->all();
            $this->recurring_start_time = $this->normalizeTimeForInput((string) ($request->recurring_start_time ?? ''));
            $this->recurring_end_time = $this->normalizeTimeForInput((string) ($request->recurring_end_time ?? ''));
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
                'recurring_starts_on',
                'recurring_ends_on',
            ]);
            return;
        }

        $this->reset(['requested_start_at', 'requested_end_at']);
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
        $this->validateAll();

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
                'full_name' => trim($this->recipient_full_name) !== '' ? trim($this->recipient_full_name) : 'Care recipient',
                'date_of_birth' => $this->recipient_date_of_birth ?: null,
                'gender' => $this->recipient_gender ?: null,
                'mobility_level' => $this->recipient_mobility_level ?: null,
                'relationship_to_family' => trim($this->recipient_relationship_to_family) !== '' ? trim($this->recipient_relationship_to_family) : 'Family member',
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
            $rules['requested_start_at'] = ['required', 'date', 'after:now'];
            $rules['requested_end_at'] = ['required', 'date', 'after:requested_start_at'];
        } else {
            $rules['recurring_days'] = ['required', 'array', 'min:1'];
            $rules['recurring_days.*'] = ['integer', Rule::in(range(0, 6))];
            $rules['recurring_start_time'] = ['required', 'date_format:H:i'];
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

    private function rulesForRecipient(): array
    {
        $recipientNameRules = $this->request_mode === self::MODE_FAST_TRACK
            ? ['required', 'string', 'min:2', 'max:120']
            : ['nullable', 'string', 'max:120'];

        return [
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
        $this->recipient_full_name = (string) ($draft['recipient_full_name'] ?? '');
        $this->selectedTasks = collect($draft['selectedTasks'] ?? [])->map(fn ($id) => (int) $id)->values()->all();
        $this->additional_info = (string) ($draft['additional_info'] ?? '');
        $this->requested_start_at = $this->normalizeDateTimeForInput((string) ($draft['requested_start_at'] ?? ''));
        $this->requested_end_at = $this->normalizeDateTimeForInput((string) ($draft['requested_end_at'] ?? ''));
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
                'full_name' => trim($this->recipient_full_name) !== '' ? trim($this->recipient_full_name) : null,
                'date_of_birth' => $this->recipient_date_of_birth ?: null,
                'gender' => $this->recipient_gender ?: null,
                'mobility_level' => $this->recipient_mobility_level ?: null,
                'relationship_to_family' => trim($this->recipient_relationship_to_family) !== '' ? trim($this->recipient_relationship_to_family) : null,
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
        $endMinutes = $this->timeStringToMinutes($this->recurring_end_time);

        if ($startMinutes === null || $endMinutes === null || $endMinutes <= $startMinutes) {
            return null;
        }

        return round(($endMinutes - $startMinutes) / 60, 2);
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

    private function normalizeDateTimeForInput(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        try {
            return Carbon::parse($trimmed)->format('Y-m-d\TH:i');
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
