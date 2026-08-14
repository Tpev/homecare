<?php

namespace App\Livewire\Admin\AiSupport;

use App\Services\AiSupport\AiSupportControlService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Settings extends Component
{
    private const HIDDEN_OPERATOR_CONTROLS = ['shadow_enabled'];

    public string $controlKey = 'master_enabled';

    public bool $desiredEnabled = false;

    public string $controlReason = '';

    public bool $impactConfirmed = false;

    public string $confirmationText = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->canManageAiSupportControls(), 403);
    }

    public function changeControl(AiSupportControlService $controls): void
    {
        $validated = $this->validate([
            'controlKey' => ['required', Rule::in($this->operatorControlKeys($controls))],
            'desiredEnabled' => ['boolean'],
            'controlReason' => ['required', 'string', 'min:5', 'max:500'],
            'impactConfirmed' => ['accepted'],
            'confirmationText' => ['required', Rule::in(['APPLY'])],
        ]);

        $controls->set(
            auth()->user(),
            $validated['controlKey'],
            (bool) $validated['desiredEnabled'],
            $validated['controlReason'],
        );

        $this->reset(['controlReason', 'impactConfirmed', 'confirmationText']);
        session()->flash('status', 'AI support control version recorded. Every higher-level control and an exact-user grant still apply.');
    }

    public function render(AiSupportControlService $controls): View
    {
        $states = collect($this->operatorControlKeys($controls))->mapWithKeys(
            fn (string $key): array => [$key => $controls->state($key)]
        );

        return view('livewire.admin.ai-support.settings', [
            'states' => $states,
            'runtimeAvailable' => (bool) config('ai_support.runtime_available', false),
            'providerEnabled' => (bool) config('ai_support.provider_enabled', false),
        ]);
    }

    /** @return list<string> */
    private function operatorControlKeys(AiSupportControlService $controls): array
    {
        return array_values(array_diff($controls->keys(), self::HIDDEN_OPERATOR_CONTROLS));
    }
}
