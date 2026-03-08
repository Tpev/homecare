<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class CaregiverLanding extends Component
{
    public function render(): View
    {
        return view('livewire.caregiver-landing')->layout('layouts.marketing');
    }
}
