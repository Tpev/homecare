<?php

namespace App\Livewire\Caregiver;

use App\Services\Earnings\CaregiverEarningsService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class EarningsDashboard extends Component
{
    #[Url(as: 'tab')]
    public string $activeTab = 'overview';

    #[Url(as: 'range')]
    public string $range = 'week';

    public int $weeklyGoal = 600;

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'caregiver', 403);

        if (! in_array($this->activeTab, ['overview', 'shifts', 'payouts'], true)) {
            $this->activeTab = 'overview';
        }

        if (! in_array($this->range, ['today', 'week', 'month', 'all'], true)) {
            $this->range = 'week';
        }
    }

    public function setActiveTab(string $tab): void
    {
        if (in_array($tab, ['overview', 'shifts', 'payouts'], true)) {
            $this->activeTab = $tab;
        }
    }

    public function setRange(string $range): void
    {
        if (in_array($range, ['today', 'week', 'month', 'all'], true)) {
            $this->range = $range;
        }
    }

    public function render(CaregiverEarningsService $earningsService)
    {
        $data = $earningsService->forCaregiver(
            auth()->user(),
            $this->range,
            $this->weeklyGoal
        );

        return view('livewire.caregiver.earnings-dashboard', $data);
    }
}

