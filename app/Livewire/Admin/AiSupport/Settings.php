<?php

namespace App\Livewire\Admin\AiSupport;

use App\Models\AiSupportPilotGrant;
use App\Services\AiSupport\AiSupportControlService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Settings extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->canManageAiSupportControls(), 403);
    }

    public function enableForEveryone(AiSupportControlService $controls): void
    {
        $controls->enableForEveryone(auth()->user());
        session()->flash('status', 'AI Support is now available to every supported Family and Caregiver user.');
    }

    public function usePilotOnly(AiSupportControlService $controls): void
    {
        $controls->usePilotOnly(auth()->user());
        session()->flash('status', 'AI Support is now limited to the two active pilot users.');
    }

    public function stopAllAutomation(AiSupportControlService $controls): void
    {
        $controls->stopAllAutomation(auth()->user());
        session()->flash('status', 'AI automation stopped. Human chat remains available.');
    }

    public function resumeAutomation(AiSupportControlService $controls): void
    {
        $controls->resumeAutomation(auth()->user());
        session()->flash('status', 'AI automation resumed in the selected availability mode.');
    }

    public function render(AiSupportControlService $controls): View
    {
        $runtimeAvailable = (bool) config('ai_support.runtime_available', false);
        $providerEnabled = (bool) config('ai_support.provider_enabled', false);
        $masterState = $controls->state('master_enabled');
        $visibleState = $controls->state('user_visible_enabled');
        $generalReleaseState = $controls->state('general_release_enabled');
        $humanOnlyState = $controls->state('human_only');

        $mode = match (true) {
            $humanOnlyState['enabled'] => 'Emergency stop',
            ! $runtimeAvailable || ! $providerEnabled || ! $masterState['enabled'] || ! $visibleState['enabled'] => 'Unavailable',
            $generalReleaseState['enabled'] => 'Live for everyone',
            default => 'Pilot only',
        };

        return view('livewire.admin.ai-support.settings', [
            'runtimeAvailable' => $runtimeAvailable,
            'providerEnabled' => $providerEnabled,
            'generalReleaseState' => $generalReleaseState,
            'humanOnlyState' => $humanOnlyState,
            'mode' => $mode,
            'activeGrants' => AiSupportPilotGrant::query()->effectiveAt()->count(),
        ]);
    }
}
