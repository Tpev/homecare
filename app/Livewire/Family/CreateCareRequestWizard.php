<?php

namespace App\Livewire\Family;

use App\Models\CareRequest;
use App\Models\CareTask;
use App\Support\FunnelTracker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CreateCareRequestWizard extends Component
{
    public int $step = 1;
    public int $totalSteps = 4;

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
    }

    public function nextStep(): void
    {
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

    public function publish(): void
    {
        $this->validateAll();

        $careRequest = DB::transaction(function () {
            $careRequest = CareRequest::query()->create([
                'family_user_id' => auth()->id(),
                'title' => trim($this->title),
                'additional_info' => trim($this->additional_info) ?: null,
                'scope_of_work' => trim($this->scope_of_work),
                'time_expectations' => trim($this->time_expectations),
                'home_access_notes' => trim($this->home_access_notes),
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
                'full_name' => trim($this->recipient_full_name),
                'date_of_birth' => $this->recipient_date_of_birth ?: null,
                'gender' => $this->recipient_gender ?: null,
                'mobility_level' => $this->recipient_mobility_level ?: null,
                'relationship_to_family' => trim($this->recipient_relationship_to_family),
                'care_notes' => trim($this->recipient_care_notes) ?: null,
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
            $this->rulesForBasics(),
            $this->rulesForRecipient(),
            $this->rulesForThirdParty()
        ));
    }

    private function validateStep(int $step): void
    {
        $rules = match ($step) {
            1 => $this->rulesForBasics(),
            2 => $this->rulesForRecipient(),
            3 => $this->rulesForThirdParty(),
            default => [],
        };

        if ($rules !== []) {
            $this->validate($rules);
        }
    }

    private function rulesForBasics(): array
    {
        $rules = [
            'request_type' => ['required', Rule::in([CareRequest::TYPE_ONE_TIME, CareRequest::TYPE_RECURRING])],
            'title' => ['required', 'string', 'min:8', 'max:140'],
            'additional_info' => ['required', 'string', 'min:30', 'max:3000'],
            'scope_of_work' => ['required', 'string', 'min:30', 'max:3000'],
            'time_expectations' => ['required', 'string', 'min:8', 'max:255'],
            'home_access_notes' => ['required', 'string', 'min:8', 'max:3000'],
            'preferred_response_hours' => ['required', 'integer', 'min:1', 'max:72'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', Rule::in(array_keys($this->usStates))],
            'zip' => ['required', 'string', 'max:15'],
            'selectedTasks' => ['required', 'array', 'min:1'],
            'selectedTasks.*' => ['integer', Rule::exists('care_tasks', 'id')],
            'taskNotes.*' => ['nullable', 'string', 'max:500'],
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
        return [
            'recipient_full_name' => ['required', 'string', 'max:120'],
            'recipient_date_of_birth' => ['nullable', 'date', 'before:today'],
            'recipient_gender' => ['nullable', Rule::in(array_column($this->genderOptions, 'value'))],
            'recipient_mobility_level' => ['nullable', Rule::in(array_column($this->mobilityOptions, 'value'))],
            'recipient_relationship_to_family' => ['required', 'string', 'max:120'],
            'recipient_care_notes' => ['required', 'string', 'min:20', 'max:2000'],
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

    public function render()
    {
        return view('livewire.family.create-care-request-wizard');
    }
}
