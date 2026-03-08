<?php

namespace App\Livewire\Admin;

use App\Models\CaregiverModerationLog;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class CaregiverModerationLogs extends Component
{
    use WithPagination;

    public function render()
    {
        $logs = CaregiverModerationLog::query()
            ->with(['caregiverProfile.user', 'actor'])
            ->latest()
            ->paginate(30);

        return view('livewire.admin.caregiver-moderation-logs', compact('logs'));
    }
}
