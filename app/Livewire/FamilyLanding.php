<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class FamilyLanding extends Component
{
    public function render(): View
    {
        return view('livewire.family-landing')
            ->layout('layouts.marketing');
    }
}
