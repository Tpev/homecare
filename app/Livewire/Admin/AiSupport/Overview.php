<?php

namespace App\Livewire\Admin\AiSupport;

use App\Models\AiSupportPilotGrant;
use App\Services\AiSupport\AiSupportControlService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Overview extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->canViewAiSupportPilot(), 403);
    }

    public function render(AiSupportControlService $controls): View
    {
        $now = now();

        return view('livewire.admin.ai-support.overview', [
            'runtimeAvailable' => (bool) config('ai_support.runtime_available', false),
            'masterState' => $controls->state('master_enabled'),
            'visibleState' => $controls->state('user_visible_enabled'),
            'humanOnlyState' => $controls->state('human_only'),
            'activeGrants' => AiSupportPilotGrant::query()->effectiveAt()->count(),
            'scheduledGrants' => AiSupportPilotGrant::query()
                ->notRevoked()
                ->where('starts_at', '>', $now)
                ->count(),
            'expiringSoon' => AiSupportPilotGrant::query()
                ->effectiveAt()
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $now->copy()->addDays(7))
                ->count(),
        ]);
    }
}
