<?php

namespace App\Livewire\Partner;

use App\Models\PartnerProfile;
use App\Support\StaffAccess;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function boot(): void
    {
        $user = auth()->user()?->fresh();
        abort_unless($user?->role === 'partner' && StaffAccess::allowsUser($user), 403);
    }

    public function render()
    {
        return view('livewire.partner.dashboard', [
            'profile' => PartnerProfile::query()->where('user_id', auth()->id())->firstOrFail(),
        ]);
    }
}
