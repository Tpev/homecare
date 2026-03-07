<?php

namespace App\Livewire;

use App\Models\Lead;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CaregiverLanding extends Component
{
    public string $fullName = '';
    public string $email = '';
    public string $zip = '';
    public string $workMode = 'independent';
    public array $services = [];
    public string $availability = 'part_time';

    public function submit(): void
    {
        $this->validate([
            'fullName' => ['required', 'string', 'min:2', 'max:80'],
            'email' => ['required', 'email', 'max:120'],
            'zip' => ['required', 'string', 'min:5', 'max:10'],
            'workMode' => ['required', Rule::in(['independent', 'agency'])],
            'availability' => ['required', Rule::in(['full_time', 'part_time', 'weekends', 'overnights'])],
            'services' => ['array'],
        ]);

        Lead::create([
            'lead_type' => 'caregiver',
            'name'      => $this->fullName,
            'email'     => strtolower(trim($this->email)),
            'phone'     => null,
            'company'   => null,
            'location'  => null,
            'zip'       => $this->zip,
            'data'      => [
                'fullName' => $this->fullName,
                'email' => $this->email,
                'zip' => $this->zip,
                'workMode' => $this->workMode,
                'availability' => $this->availability,
                'services' => $this->services,
            ],
            'status'     => 'new',
            'source_url' => request()->fullUrl(),
            'referrer_url' => request()->headers->get('referer'),
            'ip'         => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ]);

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'You’re in!',
            'message' => 'We’ll email you when caregiver early access opens in Raleigh.',
        ]);

        $this->reset(['fullName', 'email', 'zip', 'services']);
        $this->workMode = 'independent';
        $this->availability = 'part_time';
    }

    public function render()
    {
        return view('livewire.caregiver-landing')->layout('layouts.marketing');
    }
}