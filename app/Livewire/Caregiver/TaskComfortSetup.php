<?php

namespace App\Livewire\Caregiver;

use App\Models\CaregiverProfile;
use App\Models\Skill;
use App\Support\CaregiverOnboardingState;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TaskComfortSetup extends Component
{
    public CaregiverProfile $profile;
    public array $selectedSkills = [];
    public array $skillOptions = [];

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'caregiver', 403);

        $this->profile = CaregiverProfile::firstOrCreate(['user_id' => $user->id], ['status' => 'draft']);
        $this->skillOptions = Skill::query()->orderBy('name')->get(['id', 'name'])->toArray();
        $this->selectedSkills = $this->profile->skills()->pluck('skills.id')->all();

        app(CaregiverOnboardingState::class)->trackStepViewed($user, CaregiverOnboardingState::STEP_TASKS);
    }

    public function save(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'caregiver', 403);

        try {
            $this->validate([
                'selectedSkills' => ['required', 'array', 'min:1'],
                'selectedSkills.*' => ['integer', Rule::exists('skills', 'id')],
            ]);
        } catch (ValidationException $exception) {
            app(CaregiverOnboardingState::class)->trackStepError(
                $user,
                CaregiverOnboardingState::STEP_TASKS,
                $exception->errors()
            );

            throw $exception;
        }

        $this->profile->skills()->sync($this->selectedSkills);

        $onboardingState = app(CaregiverOnboardingState::class);
        $onboardingState->trackStepCompleted($user, CaregiverOnboardingState::STEP_TASKS);

        session()->flash('status', 'Task comfort preferences saved. Moving to your next required step.');
        $this->redirect($onboardingState->nextRequiredRoute($user), navigate: true);
    }

    public function render()
    {
        $onboarding = app(CaregiverOnboardingState::class)->build(auth()->user());

        return view('livewire.caregiver.task-comfort-setup', compact('onboarding'));
    }
}
