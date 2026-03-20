<?php

namespace App\Livewire\Caregiver;

use App\Models\CaregiverModerationLog;
use App\Models\CaregiverProfile;
use App\Models\CaregiverProfileVersion;
use App\Models\Language;
use App\Models\Skill;
use App\Services\Ops\OpsAlertService;
use App\Support\CaregiverOnboardingState;
use App\Support\FunnelTracker;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

#[Layout('layouts.app')]
class OnboardingWizard extends Component
{
    use WithFileUploads;

    public int $step = 1;
    public int $totalSteps = 4;

    public ?string $profile_photo_path = null;
    public $profile_photo = null;

    public string $bio = '';
    public ?int $years_experience = null;
    public string $date_of_birth = '';
    public string $city = '';
    public string $state = '';
    public string $service_area_zip = '';
    public ?int $service_radius_miles = 10;
    public bool $is_accepting_new_clients = true;

    public array $selectedSkills = [];
    public array $selectedLanguages = [];
    public array $skillOptions = [];
    public array $languageOptions = [];

    // availability[day][] = ['start' => '09:00', 'end' => '17:00']
    public array $availability = [];

    public CaregiverProfile $profile;

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
        abort_unless($user && $user->role === 'caregiver', 403);

        $this->profile = CaregiverProfile::firstOrCreate(
            ['user_id' => $user->id],
            ['status' => 'draft']
        );

        $this->skillOptions = Skill::query()->orderBy('name')->get(['id', 'name'])->toArray();
        $this->languageOptions = Language::query()->orderBy('name')->get(['id', 'name'])->toArray();

        $this->bio = (string) $this->profile->bio;
        $this->years_experience = $this->profile->years_experience;
        $this->date_of_birth = $user->date_of_birth?->format('Y-m-d') ?? '';
        $this->city = (string) $user->city;
        $this->state = (string) $user->state;
        $this->service_area_zip = (string) $this->profile->service_area_zip;
        $this->service_radius_miles = $this->profile->service_radius_miles ?: 10;
        $this->is_accepting_new_clients = (bool) $this->profile->is_accepting_new_clients;
        $this->profile_photo_path = $this->profile->profile_photo_path;

        $this->selectedSkills = $this->profile->skills()->pluck('skills.id')->all();
        $this->selectedLanguages = $this->profile->languages()->pluck('languages.id')->all();

        $this->availability = collect(range(0, 6))->mapWithKeys(
            fn (int $day) => [$day => []]
        )->toArray();

        foreach ($this->profile->availabilities()->orderBy('day_of_week')->orderBy('start_time')->get() as $slot) {
            $this->availability[$slot->day_of_week][] = [
                'start' => substr($slot->start_time, 0, 5),
                'end' => substr($slot->end_time, 0, 5),
            ];
        }

        $requestedStep = request()->integer('step');
        if ($requestedStep >= 1 && $requestedStep <= $this->totalSteps) {
            $this->step = $requestedStep;
        }

        app(CaregiverOnboardingState::class)->trackStepViewed($user, CaregiverOnboardingState::STEP_PROFILE_BASICS);
    }

    public function addRange(int $day): void
    {
        $this->availability[$day][] = ['start' => '09:00', 'end' => '17:00'];
    }

    public function removeRange(int $day, int $index): void
    {
        unset($this->availability[$day][$index]);
        $this->availability[$day] = array_values($this->availability[$day]);
    }

    public function nextStep(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'caregiver', 403);

        try {
            $this->validateStep();
        } catch (ValidationException $exception) {
            app(CaregiverOnboardingState::class)->trackStepError(
                $user,
                CaregiverOnboardingState::STEP_PROFILE_BASICS,
                $exception->errors()
            );

            throw $exception;
        }

        $this->saveDraft(false);
        $this->step = min($this->step + 1, $this->totalSteps);

        FunnelTracker::track('caregiver_onboarding_step_completed', $user, $this->profile, [
            'step_number' => $this->step - 1,
            'flow' => 'profile_basics',
        ]);
    }

    public function previousStep(): void
    {
        $this->step = max($this->step - 1, 1);
    }

    public function submitForReview(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'caregiver', 403);

        $this->step = $this->totalSteps;
        try {
            $this->validateForSubmission();
        } catch (ValidationException $exception) {
            app(CaregiverOnboardingState::class)->trackStepError(
                $user,
                CaregiverOnboardingState::STEP_PROFILE_BASICS,
                $exception->errors()
            );

            throw $exception;
        }

        if (! $this->identityIsApproved()) {
            $this->addError('identity_verification', 'Please complete identity verification before submitting for review.');
            app(CaregiverOnboardingState::class)->trackStepError($user, CaregiverOnboardingState::STEP_PROFILE_BASICS, [
                'identity_verification' => ['identity_verification'],
            ]);

            return;
        }

        if (! $this->taskPreferencesAreComplete()) {
            $this->addError('task_preferences', 'Select the tasks you are comfortable with before submitting.');
            app(CaregiverOnboardingState::class)->trackStepError($user, CaregiverOnboardingState::STEP_PROFILE_BASICS, [
                'task_preferences' => ['task_preferences'],
            ]);

            return;
        }

        $this->saveDraft(true);
        app(OpsAlertService::class)->notifyCaregiverReadyForReview($user, $this->profile->fresh());
        FunnelTracker::track('caregiver_onboarding_submitted_for_review', $user, $this->profile->fresh());

        session()->flash('status', 'Profile submitted. Review usually takes up to 1 business day.');
        $this->redirect(route('caregiver.setup.index', absolute: false), navigate: true);
    }

    private function validateStep(): void
    {
        $rules = match ($this->step) {
            1 => [
                'bio' => ['required', 'string', 'min:40', 'max:2000'],
                'years_experience' => ['required', 'integer', 'min:0', 'max:60'],
                'date_of_birth' => ['required', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
                'city' => ['required', 'string', 'max:120'],
                'state' => ['required', Rule::in(array_keys($this->usStates))],
                'profile_photo' => ['nullable', 'image', 'max:2048'],
                'selectedLanguages' => ['required', 'array', 'min:1'],
                'selectedLanguages.*' => ['integer', Rule::exists('languages', 'id')],
            ],
            2 => [
                'service_area_zip' => ['required', 'string', 'max:15'],
                'service_radius_miles' => ['required', 'integer', 'min:1', 'max:60'],
            ],
            3 => [
                'availability' => ['required', 'array'],
            ],
            default => [
                'is_accepting_new_clients' => ['required', 'boolean'],
            ],
        };

        $this->validate($rules);

        if ($this->step === 3) {
            $this->validateAvailabilityRanges();
        }
    }

    private function isQuarterHour(string $hhmm): bool
    {
        if (! preg_match('/^\d{2}:\d{2}$/', $hhmm)) {
            return false;
        }

        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h >= 0 && $h <= 23 && in_array($m, [0, 15, 30, 45], true);
    }

    private function toMinutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }

    private function validateAvailabilityRanges(): void
    {
        $enabledDays = 0;

        foreach ($this->availability as $day => $dayRanges) {
            if (count($dayRanges) === 0) {
                continue;
            }

            $enabledDays++;
            $windows = [];

            foreach ($dayRanges as $idx => $range) {
                $start = (string) Arr::get($range, 'start', '');
                $end = (string) Arr::get($range, 'end', '');

                if (! $this->isQuarterHour($start) || ! $this->isQuarterHour($end)) {
                    $this->addError("availability.$day.$idx", 'Times must be in 15-minute increments.');
                    return;
                }

                $startMin = $this->toMinutes($start);
                $endMin = $this->toMinutes($end);

                if ($startMin >= $endMin) {
                    $this->addError("availability.$day.$idx", 'End time must be after start time.');
                    return;
                }

                $windows[] = [$startMin, $endMin];
            }

            usort($windows, fn ($a, $b) => $a[0] <=> $b[0]);

            for ($i = 1; $i < count($windows); $i++) {
                if ($windows[$i][0] < $windows[$i - 1][1]) {
                    $this->addError("availability.$day", 'Overlapping ranges are not allowed.');
                    return;
                }
            }
        }

        if ($enabledDays === 0) {
            $this->addError('availability', 'Add at least one availability range.');
        }
    }

    private function saveDraft(bool $submitForReview): void
    {
        DB::transaction(function () use ($submitForReview) {
            $user = auth()->user();

            if ($this->profile_photo) {
                $this->profile_photo_path = $this->profile_photo->store('caregiver-photos', 'public');
            }

            $user->forceFill([
                'city' => trim($this->city),
                'state' => strtoupper($this->state),
                'date_of_birth' => $this->date_of_birth,
            ])->save();

            $this->profile->user()->associate($user);

            if (! $this->profile->slug) {
                $this->profile->slug = Str::slug($user->name . '-' . $user->id);
            }

            $this->profile->fill([
                'profile_photo_path' => $this->profile_photo_path,
                'bio' => trim($this->bio),
                'years_experience' => $this->years_experience,
                'service_area_zip' => trim($this->service_area_zip),
                'service_radius_miles' => $this->service_radius_miles,
                'is_accepting_new_clients' => $this->is_accepting_new_clients,
            ]);

            if ($submitForReview) {
                $this->profile->status = 'under_review';
                $this->profile->review_submitted_at = now();
                $this->profile->rejection_reason = null;

                $user->forceFill([
                    'onboarding_completed_at' => now(),
                ])->save();
            } elseif (! in_array($this->profile->status, ['active', 'under_review', 'suspended'], true)) {
                $this->profile->status = 'draft';
            }

            $this->profile->save();

            $this->profile->skills()->sync($this->selectedSkills);
            $this->profile->languages()->sync($this->selectedLanguages);

            $this->profile->availabilities()->delete();
            foreach ($this->availability as $day => $dayRanges) {
                foreach ($dayRanges as $range) {
                    $this->profile->availabilities()->create([
                        'day_of_week' => (int) $day,
                        'start_time' => $range['start'],
                        'end_time' => $range['end'],
                    ]);
                }
            }

            CaregiverProfileVersion::create([
                'caregiver_profile_id' => $this->profile->id,
                'user_id' => $user->id,
                'reason' => $submitForReview ? 'submitted_for_review' : 'draft_save',
                'snapshot' => [
                    'profile' => $this->profile->fresh()->toArray(),
					'skills' => $this->profile->skills()->pluck('skills.id')->all(),
					'languages' => $this->profile->languages()->pluck('languages.id')->all(),
                    'availability' => $this->availability,
                ],
            ]);

            if ($submitForReview) {
                CaregiverModerationLog::create([
                    'caregiver_profile_id' => $this->profile->id,
                    'actor_user_id' => $user->id,
                    'action' => 'submitted',
                    'note' => 'Profile submitted for review',
                    'meta' => ['status' => 'under_review'],
                ]);
            }
        });
    }

    private function identityIsApproved(): bool
    {
        $this->profile->refresh();

        return (bool) $this->profile->identity_verified_at
            || $this->profile->identity_verification_status === 'approved';
    }

    private function taskPreferencesAreComplete(): bool
    {
        $this->profile->refresh();

        return $this->profile->skills()->exists();
    }

    private function insuranceIsComplete(): bool
    {
        $this->profile->refresh();

        return $this->profile->insuranceIsComplete();
    }

    private function validateForSubmission(): void
    {
        $this->validate([
            'bio' => ['required', 'string', 'min:40', 'max:2000'],
            'years_experience' => ['required', 'integer', 'min:0', 'max:60'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', Rule::in(array_keys($this->usStates))],
            'selectedLanguages' => ['required', 'array', 'min:1'],
            'selectedLanguages.*' => ['integer', Rule::exists('languages', 'id')],
            'service_area_zip' => ['required', 'string', 'max:15'],
            'service_radius_miles' => ['required', 'integer', 'min:1', 'max:60'],
            'availability' => ['required', 'array'],
            'is_accepting_new_clients' => ['required', 'boolean'],
        ]);

        $this->validateAvailabilityRanges();
    }

    public function render()
    {
        $onboarding = app(CaregiverOnboardingState::class)->build(auth()->user());

        return view('livewire.caregiver.onboarding-wizard', [
            'days' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            'taskPreferencesComplete' => $this->taskPreferencesAreComplete(),
            'insuranceComplete' => $this->insuranceIsComplete(),
            'onboarding' => $onboarding,
        ]);
    }
}
