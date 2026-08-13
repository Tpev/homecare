<?php

namespace App\Livewire\Admin\AiSupport;

use App\Models\AiSupportAdminAuditEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ActivityIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $family = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->canViewAiSupportPilot(), 403);
    }

    public function render(): View
    {
        $events = AiSupportAdminAuditEvent::query()
            ->with(['actor:id,name,email', 'targetUser:id,name,email'])
            ->when($this->family !== '', fn (Builder $query): Builder => $query->where('event_family', $this->family))
            ->latest('occurred_at')
            ->paginate(30);

        return view('livewire.admin.ai-support.activity-index', ['events' => $events]);
    }
}
