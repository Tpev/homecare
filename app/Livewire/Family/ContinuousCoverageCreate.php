<?php

namespace App\Livewire\Family;

use App\Models\CareRecipientProfile;
use App\Models\CareTask;
use App\Models\ContinuousCoveragePlan;
use App\Services\ContinuousCoverage\ContinuousCoverageScheduleService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Support\MarketplacePricing;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class ContinuousCoverageCreate extends Component
{
    public int $step = 1;

    public string $title = '';

    public string $recipientName = '';

    public string $relationshipToFamily = 'Loved one';

    public ?int $selectedCareRecipientProfileId = null;

    public string $startsOn = '';

    public string $endsOn = '';

    public string $timezone = 'America/New_York';

    public string $coveragePattern = ContinuousCoveragePlan::PATTERN_AROUND_THE_CLOCK;

    public int $shiftLengthMinutes = 720;

    public string $shiftLengthChoice = '720';

    public string $customShiftLengthHours = '4';

    public string $coverageStartTime = '07:00';

    public string $coverageEndTime = '07:00';

    public array $customWindows = [['day' => 1, 'start' => '07:00', 'end' => '19:00']];

    public string $careNotes = '';

    public array $taskIds = [];

    public string $addressLine1 = '';

    public string $addressLine2 = '';

    public string $city = '';

    public string $state = '';

    public string $zip = '';

    #[Locked]
    public float $hourlyRate = 30.0;

    public string $replacementConfirmationMode = ContinuousCoveragePlan::CONFIRM_FAMILY;

    public bool $marketplaceApplicationsEnabled = false;

    public function mount(MarketplacePricing $pricing): void
    {
        abort_unless(auth()->user()?->role === 'family', 403);
        $this->startsOn = now($this->timezone)->addDay()->toDateString();
        $this->hourlyRate = $pricing->hourlyRateForFamily(auth()->user(), 30.0);
    }

    public function addWindow(): void
    {
        $this->customWindows[] = ['day' => 1, 'start' => '07:00', 'end' => '19:00'];
    }

    public function selectCareRecipientProfile(int $profileId): void
    {
        $account = app(FamilyAccountContext::class)->account(auth()->user());
        $profile = CareRecipientProfile::query()
            ->forFamilyAccount($account)
            ->where('status', CareRecipientProfile::STATUS_READY)
            ->whereNotNull('latest_ready_version_id')
            ->findOrFail($profileId);

        $this->selectedCareRecipientProfileId = $profile->id;
        $this->recipientName = $profile->full_name ?: $profile->preferred_name;
        $this->relationshipToFamily = $profile->relationship_to_family ?: 'Loved one';
        if (trim($this->careNotes) === '') {
            $this->careNotes = (string) ($profile->about_them ?? '');
        }
    }

    public function clearCareRecipientProfile(): void
    {
        $this->selectedCareRecipientProfileId = null;
    }

    public function removeWindow(int $index): void
    {
        if (count($this->customWindows) <= 1) {
            return;
        }
        unset($this->customWindows[$index]);
        $this->customWindows = array_values($this->customWindows);
    }

    public function updatedCoveragePattern(string $pattern): void
    {
        if ($pattern === ContinuousCoveragePlan::PATTERN_OVERNIGHT && $this->coverageStartTime === $this->coverageEndTime) {
            $this->coverageStartTime = '19:00';
            $this->coverageEndTime = '07:00';
        }
    }

    public function previousStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function nextStep(ContinuousCoverageScheduleService $schedule): void
    {
        if ($this->step === 1) {
            $this->validate([
                'title' => ['required', 'string', 'max:120'],
                'recipientName' => ['required', 'string', 'max:120'],
                'relationshipToFamily' => ['required', 'string', 'max:80'],
                'addressLine1' => ['required', 'string', 'max:150'],
                'addressLine2' => ['nullable', 'string', 'max:150'],
                'city' => ['required', 'string', 'max:100'],
                'state' => ['required', 'string', 'size:2'],
                'zip' => ['required', 'string', 'max:15'],
            ]);
        } elseif ($this->step === 2) {
            $rules = [
                'startsOn' => ['required', 'date', 'after_or_equal:today'],
                'endsOn' => ['nullable', 'date', 'after_or_equal:startsOn'],
                'timezone' => ['required', 'timezone'],
                'coveragePattern' => ['required', Rule::in([
                    ContinuousCoveragePlan::PATTERN_AROUND_THE_CLOCK,
                    ContinuousCoveragePlan::PATTERN_OVERNIGHT,
                    ContinuousCoveragePlan::PATTERN_CUSTOM,
                ])],
                'coverageStartTime' => ['required', 'date_format:H:i'],
                'coverageEndTime' => ['required', 'date_format:H:i'],
            ] + $this->shiftStructureRules();
            if ($this->coveragePattern === ContinuousCoveragePlan::PATTERN_CUSTOM) {
                $rules += [
                    'customWindows' => ['required', 'array', 'min:1', 'max:42'],
                    'customWindows.*.day' => ['required', 'integer', 'between:0,6'],
                    'customWindows.*.start' => ['required', 'date_format:H:i'],
                    'customWindows.*.end' => ['required', 'date_format:H:i'],
                ];
            }
            $this->validate($rules);
            $this->applyResolvedShiftLength();
            $analysis = $schedule->analyzePlanInput([
                'coverage_pattern' => $this->coveragePattern,
                'shift_length_minutes' => $this->shiftLengthMinutes,
                'coverage_start_time' => $this->coverageStartTime,
                'coverage_end_time' => $this->coverageEndTime,
                'custom_windows' => $this->customWindows,
            ]);
            if ($analysis['has_overlaps']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'customWindows' => 'Coverage windows overlap. Adjust the schedule before continuing.',
                ]);
            }
        } elseif ($this->step === 3) {
            $this->validate([
                'careNotes' => ['nullable', 'string', 'max:4000'],
                'taskIds' => ['array'],
                'taskIds.*' => ['integer', 'exists:care_tasks,id'],
                'replacementConfirmationMode' => ['required', Rule::in([
                    ContinuousCoveragePlan::CONFIRM_FAMILY,
                    ContinuousCoveragePlan::CONFIRM_APPROVED_BACKUP,
                ])],
                'marketplaceApplicationsEnabled' => ['boolean'],
            ]);
        }

        $this->step = min(4, $this->step + 1);
    }

    public function save(
        ContinuousCoverageScheduleService $schedule,
        MarketplacePricing $pricing,
    ): void {
        $rules = [
            'title' => ['required', 'string', 'max:120'],
            'recipientName' => ['required', 'string', 'max:120'],
            'relationshipToFamily' => ['required', 'string', 'max:80'],
            'startsOn' => ['required', 'date', 'after_or_equal:today'],
            'endsOn' => ['nullable', 'date', 'after_or_equal:startsOn'],
            'timezone' => ['required', 'timezone'],
            'coveragePattern' => ['required', Rule::in([
                ContinuousCoveragePlan::PATTERN_AROUND_THE_CLOCK,
                ContinuousCoveragePlan::PATTERN_OVERNIGHT,
                ContinuousCoveragePlan::PATTERN_CUSTOM,
            ])],
            'coverageStartTime' => ['required', 'date_format:H:i'],
            'coverageEndTime' => ['required', 'date_format:H:i'],
            'careNotes' => ['nullable', 'string', 'max:4000'],
            'taskIds' => ['array'],
            'taskIds.*' => ['integer', 'exists:care_tasks,id'],
            'addressLine1' => ['required', 'string', 'max:150'],
            'addressLine2' => ['nullable', 'string', 'max:150'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'size:2'],
            'zip' => ['required', 'string', 'max:15'],
            'replacementConfirmationMode' => ['required', Rule::in([
                ContinuousCoveragePlan::CONFIRM_FAMILY,
                ContinuousCoveragePlan::CONFIRM_APPROVED_BACKUP,
            ])],
            'marketplaceApplicationsEnabled' => ['boolean'],
            'selectedCareRecipientProfileId' => ['nullable', 'integer'],
        ] + $this->shiftStructureRules();
        if ($this->coveragePattern === ContinuousCoveragePlan::PATTERN_CUSTOM) {
            $rules += [
                'customWindows' => ['required', 'array', 'min:1', 'max:42'],
                'customWindows.*.day' => ['required', 'integer', 'between:0,6'],
                'customWindows.*.start' => ['required', 'date_format:H:i'],
                'customWindows.*.end' => ['required', 'date_format:H:i'],
            ];
        }
        $this->validate($rules);
        $this->applyResolvedShiftLength();
        $this->hourlyRate = $pricing->hourlyRateForFamily(auth()->user(), 30.0);

        $tasks = CareTask::query()->whereKey($this->taskIds)->get(['id', 'name'])
            ->map(fn (CareTask $task) => ['id' => $task->id, 'name' => $task->name])->all();
        $plan = $schedule->createPlan(auth()->user(), [
            'title' => trim($this->title),
            'timezone' => $this->timezone,
            'starts_on' => $this->startsOn,
            'ends_on' => $this->endsOn,
            'coverage_pattern' => $this->coveragePattern,
            'shift_length_minutes' => $this->shiftLengthMinutes,
            'coverage_start_time' => $this->coverageStartTime,
            'coverage_end_time' => $this->coverageEndTime,
            'custom_windows' => $this->coveragePattern === ContinuousCoveragePlan::PATTERN_CUSTOM ? $this->customWindows : [],
            'recipient_snapshot' => [
                'full_name' => trim($this->recipientName),
                'relationship_to_family' => trim($this->relationshipToFamily),
                'care_notes' => trim($this->careNotes) ?: null,
            ],
            'address_snapshot' => [
                'address_line1' => trim($this->addressLine1),
                'address_line2' => trim($this->addressLine2) ?: null,
                'city' => trim($this->city),
                'state' => strtoupper(trim($this->state)),
                'zip' => trim($this->zip),
            ],
            'task_snapshot' => $tasks,
            'care_notes' => trim($this->careNotes) ?: null,
            'hourly_rate' => $this->hourlyRate,
            'replacement_confirmation_mode' => $this->replacementConfirmationMode,
            'marketplace_applications_enabled' => $this->marketplaceApplicationsEnabled,
            'care_recipient_profile_id' => $this->selectedCareRecipientProfileId,
        ]);

        session()->flash('status', 'Continuous Coverage plan created. Build the family-approved care team next.');
        $this->redirectRoute('family.continuous-coverage.show', $plan, navigate: true);
    }

    public function render(ContinuousCoverageScheduleService $schedule)
    {
        try {
            $shiftLengthMinutes = $this->resolvedShiftLengthMinutes();
            if ($shiftLengthMinutes === null) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'customShiftLengthHours' => 'Choose a shift length that divides 24 hours evenly.',
                ]);
            }
            $analysis = $schedule->analyzePlanInput([
                'coverage_pattern' => $this->coveragePattern,
                'shift_length_minutes' => $shiftLengthMinutes,
                'coverage_start_time' => $this->coverageStartTime,
                'coverage_end_time' => $this->coverageEndTime,
                'custom_windows' => $this->customWindows,
            ]);
        } catch (\Illuminate\Validation\ValidationException) {
            $analysis = [
                'weekly_minutes' => 0,
                'shift_count' => 0,
                'overlap_minutes' => 0,
                'uncovered_minutes' => 0,
                'has_overlaps' => false,
                'has_gaps' => false,
            ];
        }

        $account = app(FamilyAccountContext::class)->account(auth()->user());

        return view('livewire.family.continuous-coverage-create', [
            'taskOptions' => CareTask::query()->orderBy('name')->get(['id', 'name']),
            'careRecipientProfiles' => CareRecipientProfile::query()
                ->forFamilyAccount($account)
                ->where('status', CareRecipientProfile::STATUS_READY)
                ->whereNotNull('latest_ready_version_id')
                ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$account->default_care_recipient_profile_id ?: 0])
                ->orderBy('preferred_name')
                ->get(),
            'weeklyHours' => round($analysis['weekly_minutes'] / 60, 1),
            'shiftCount' => $analysis['shift_count'],
            'scheduleAnalysis' => $analysis,
        ]);
    }

    private function shiftStructureRules(): array
    {
        if ($this->coveragePattern !== ContinuousCoveragePlan::PATTERN_AROUND_THE_CLOCK) {
            return [];
        }

        $rules = [
            'shiftLengthChoice' => ['required', Rule::in(['720', '480', '360', 'custom'])],
        ];

        if ($this->shiftLengthChoice === 'custom') {
            $rules['customShiftLengthHours'] = [
                'bail',
                'required',
                'numeric',
                'between:1,12',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $minutes = (float) $value * 60;
                    $roundedMinutes = (int) round($minutes);
                    if ($roundedMinutes < 60 || abs($minutes - $roundedMinutes) > 0.0001 || 1440 % $roundedMinutes !== 0) {
                        $fail('Choose a shift length that divides 24 hours evenly, such as 4, 3, 2, or 1.5 hours.');
                    }
                },
            ];
        }

        return $rules;
    }

    private function applyResolvedShiftLength(): void
    {
        $this->shiftLengthMinutes = $this->resolvedShiftLengthMinutes()
            ?? throw new \LogicException('Shift length was applied before validation.');
    }

    private function resolvedShiftLengthMinutes(): ?int
    {
        if ($this->coveragePattern !== ContinuousCoveragePlan::PATTERN_AROUND_THE_CLOCK) {
            return 720;
        }

        if (in_array($this->shiftLengthChoice, ['720', '480', '360'], true)) {
            return (int) $this->shiftLengthChoice;
        }

        if ($this->shiftLengthChoice !== 'custom' || ! is_numeric($this->customShiftLengthHours)) {
            return null;
        }

        $minutes = (float) $this->customShiftLengthHours * 60;
        $roundedMinutes = (int) round($minutes);

        if ($roundedMinutes < 60 || $roundedMinutes > 720 || abs($minutes - $roundedMinutes) > 0.0001 || 1440 % $roundedMinutes !== 0) {
            return null;
        }

        return $roundedMinutes;
    }
}
