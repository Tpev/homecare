<?php

namespace App\Livewire\Partner;

use App\Livewire\Forms\PartnerProfileForm;
use App\Models\PartnerProfile;
use App\Support\PartnerOptions;
use App\Support\StaffAccess;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProfileEditor extends Component
{
    public PartnerProfileForm $form;

    public function boot(): void
    {
        $user = auth()->user()?->fresh();
        abort_unless($user?->role === 'partner' && StaffAccess::allowsUser($user), 403);
    }

    public function mount(): void
    {
        $this->form->setProfile($this->profile());
    }

    public function save(): void
    {
        // The account is always resolved server-side; partner status and internal notes
        // never enter the editable form payload.
        $this->profile()->update($this->form->normalizedData());
        session()->flash('partner-profile-saved', true);
        $this->redirect(route('partner.dashboard', absolute: false), navigate: true);
    }

    private function profile(): PartnerProfile
    {
        return PartnerProfile::query()->where('user_id', auth()->id())->firstOrFail();
    }

    public function render()
    {
        return view('livewire.partner.profile-editor', [
            'categories' => PartnerOptions::categories(),
            'states' => PartnerOptions::states(),
        ]);
    }
}
