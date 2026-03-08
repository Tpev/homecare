<?php

namespace App\Livewire\Admin;

use App\Models\FunnelEvent;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class FunnelAnalytics extends Component
{
    public int $days = 14;

    public function render()
    {
        $start = Carbon::now()->subDays($this->days);

        $events = FunnelEvent::query()
            ->where('occurred_at', '>=', $start)
            ->selectRaw('event, COUNT(*) as total')
            ->groupBy('event')
            ->orderByDesc('total')
            ->get();

        return view('livewire.admin.funnel-analytics', compact('events', 'start'));
    }
}
