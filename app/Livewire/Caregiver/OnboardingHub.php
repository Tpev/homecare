<?php

namespace App\Livewire\Caregiver;

use App\Support\CaregiverOnboardingState;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class OnboardingHub extends Component
{
    public array $state = [];

    public function mount(CaregiverOnboardingState $onboardingState): void
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'caregiver', 403);

        $this->state = $onboardingState->build($user);
        $onboardingState->trackHubViewed($user);

        if (($this->state['onboarding_mode'] ?? false) !== true) {
            $this->redirect(route('dashboard', absolute: false), navigate: true);
        }
    }

    public function continueSetup(CaregiverOnboardingState $onboardingState): void
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'caregiver', 403);

        $this->redirect($onboardingState->nextRequiredRoute($user), navigate: true);
    }

    public function render()
    {
        return view('livewire.caregiver.onboarding-hub');
    }
}
