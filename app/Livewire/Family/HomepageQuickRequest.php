<?php

namespace App\Livewire\Family;

use App\Models\CareTask;
use App\Support\FamilyQuickRequestDraft;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Component;

class HomepageQuickRequest extends Component
{
    public int $step = 1;

    public string $recipient_name = '';
    public array $selectedTasks = [];
    public string $additional_info = '';

    public string $requested_start_at = '';
    public string $requested_end_at = '';
    public string $address_line1 = '';
    public string $city = 'Raleigh';
    public string $state = 'NC';
    public string $zip = '';

    public array $taskOptions = [];

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
        $this->taskOptions = CareTask::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();

        if ($this->taskOptions === []) {
            $this->taskOptions = [
                ['id' => 1, 'name' => 'Companionship'],
                ['id' => 2, 'name' => 'Meal preparation'],
                ['id' => 3, 'name' => 'Errands'],
                ['id' => 4, 'name' => 'Light housekeeping'],
            ];
        }
    }

    public function nextStep(): void
    {
        $this->validateStep($this->step);
        $this->step = min(3, $this->step + 1);
    }

    public function previousStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function toggleTask(int $taskId): void
    {
        $selected = collect($this->selectedTasks)
            ->map(fn ($id) => (int) $id);

        if ($selected->contains($taskId)) {
            $this->selectedTasks = $selected
                ->reject(fn ($id) => $id === $taskId)
                ->values()
                ->all();

            return;
        }

        $this->selectedTasks = $selected
            ->push($taskId)
            ->unique()
            ->values()
            ->all();
    }

    public function startAccountHandoff()
    {
        $this->validateStep(3);

        FamilyQuickRequestDraft::put([
            'request_mode' => CreateCareRequestWizard::MODE_FAST_TRACK,
            'modeChosen' => true,
            'step' => 4,
            'request_type' => 'one_time',
            'recipient_full_name' => trim($this->recipient_name),
            'selectedTasks' => collect($this->selectedTasks)->map(fn ($id) => (int) $id)->values()->all(),
            'additional_info' => trim($this->additional_info),
            'requested_start_at' => $this->normalizeDateTime($this->requested_start_at),
            'requested_end_at' => $this->normalizeDateTime($this->requested_end_at),
            'address_line1' => trim($this->address_line1),
            'city' => trim($this->city),
            'state' => strtoupper($this->state),
            'zip' => trim($this->zip),
        ]);

        if (auth()->check() && auth()->user()?->role === 'family') {
            session()->flash('status', 'Your quick request draft is ready. Review once, then publish it.');

            return $this->redirect(route('family.requests.create', absolute: false), navigate: true);
        }

        session()->flash('status', 'Your request draft is saved. Create your account to make it live.');

        return $this->redirect(route('register', absolute: false), navigate: true);
    }

    public function getEstimatedHoursProperty(): ?float
    {
        if (blank($this->requested_start_at) || blank($this->requested_end_at)) {
            return null;
        }

        try {
            $start = Carbon::parse($this->requested_start_at);
            $end = Carbon::parse($this->requested_end_at);
        } catch (\Throwable) {
            return null;
        }

        if ($end->lte($start)) {
            return null;
        }

        return round($start->diffInMinutes($end) / 60, 2);
    }

    public function getEstimatedCostProperty(): ?float
    {
        if ($this->estimatedHours === null) {
            return null;
        }

        return round($this->estimatedHours * 30, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesForStepOne(): array
    {
        return [
            'recipient_name' => ['required', 'string', 'min:2', 'max:120'],
            'selectedTasks' => ['required', 'array', 'min:1'],
            'selectedTasks.*' => ['integer'],
            'additional_info' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesForStepTwo(): array
    {
        return [
            'requested_start_at' => ['required', 'date', 'after:now'],
            'requested_end_at' => ['required', 'date', 'after:requested_start_at'],
            'address_line1' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', Rule::in(array_keys($this->usStates))],
            'zip' => ['required', 'string', 'max:15'],
        ];
    }

    private function validateStep(int $step): void
    {
        if ($step === 1) {
            $this->validate($this->rulesForStepOne());
            return;
        }

        if ($step === 2 || $step === 3) {
            $this->validate(array_merge($this->rulesForStepOne(), $this->rulesForStepTwo()));
        }
    }

    private function normalizeDateTime(string $value): string
    {
        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    public function render()
    {
        return view('livewire.family.homepage-quick-request');
    }
}
