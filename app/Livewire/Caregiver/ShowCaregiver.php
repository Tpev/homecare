<?php

namespace App\Livewire\Caregiver;

use App\Models\CaregiverProfile;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ShowCaregiver extends Component
{
    public CaregiverProfile $caregiver;

    public function mount(string $slug): void
    {
        $this->caregiver = CaregiverProfile::query()
            ->with(['user','skills','languages','availabilities'])
            ->where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();
    }

    public function render()
    {
        return view('livewire.caregiver.show-caregiver');
    }
}
