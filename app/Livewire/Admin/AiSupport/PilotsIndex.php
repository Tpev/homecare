<?php

namespace App\Livewire\Admin\AiSupport;

use App\Models\AiSupportPilotGrant;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class PilotsIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'current';

    public function mount(): void
    {
        abort_unless(auth()->user()?->canViewAiSupportPilot(), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $search = trim($this->search);
        $grants = AiSupportPilotGrant::query()
            ->with(['user:id,name,email,role', 'grantedBy:id,name', 'revokedBy:id,name'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $match) use ($search): void {
                    $match->where('id', $search)
                        ->orWhere('user_id', ctype_digit($search) ? (int) $search : -1)
                        ->orWhereHas('user', fn (Builder $user): Builder => $user
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%'));
                });
            })
            ->when($this->status === 'current', fn (Builder $query): Builder => $query
                ->notRevoked()
                ->where(fn (Builder $window): Builder => $window
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now())))
            ->when($this->status === 'active', fn (Builder $query): Builder => $query->effectiveAt())
            ->when($this->status === 'scheduled', fn (Builder $query): Builder => $query
                ->notRevoked()
                ->where('starts_at', '>', now()))
            ->when($this->status === 'ended', fn (Builder $query): Builder => $query
                ->where(fn (Builder $ended): Builder => $ended
                    ->whereNotNull('revoked_at')
                    ->orWhere('expires_at', '<=', now())))
            ->latest('created_at')
            ->paginate(20);

        return view('livewire.admin.ai-support.pilots-index', ['grants' => $grants]);
    }
}
