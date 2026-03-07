<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;


class CaregiverMock extends Component
{
    public string $availability = 'available';
    public string $radius = '10';
    public array $serviceTypes = ['companion', 'personal'];
    public bool $acceptingNewClients = true;

    public function toggleAvailability(): void
    {
        $this->availability = $this->availability === 'available' ? 'paused' : 'available';

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Availability updated',
            'message' => $this->availability === 'available'
                ? 'You’re visible to families in Raleigh.'
                : 'You’re paused (not discoverable).',
        ]);
    }

    public function render()
    {
        return view('livewire.caregiver-mock')->layout('layouts.marketing');
    }
}