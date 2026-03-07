<?php

namespace App\Livewire;

use App\Models\Lead;
use Illuminate\Validation\Rule;
use Livewire\Component;

class AgencyLanding extends Component
{
    public string $agencyName = '';
    public string $contactName = '';
    public string $email = '';
    public string $phone = '';
    public string $serviceArea = 'Raleigh, NC';
    public string $primaryGoal = 'get_clients';
    public array $coverageNeeds = [];
    public string $staffingFrequency = 'sometimes';

    public function submit(): void
    {
        $this->validate([
            'agencyName' => ['required', 'string', 'min:2', 'max:120'],
            'contactName' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'serviceArea' => ['required', 'string', 'min:2', 'max:120'],
            'primaryGoal' => ['required', Rule::in(['get_clients', 'fill_shifts', 'both'])],
            'coverageNeeds' => ['array'],
            'staffingFrequency' => ['required', Rule::in(['daily', 'weekly', 'sometimes'])],
        ]);

        Lead::create([
            'lead_type' => 'agency',
            'name'      => $this->contactName,
            'email'     => strtolower(trim($this->email)),
            'phone'     => $this->phone ?: null,
            'company'   => $this->agencyName,
            'location'  => $this->serviceArea,
            'zip'       => null,
            'data'      => [
                'agencyName' => $this->agencyName,
                'contactName' => $this->contactName,
                'email' => $this->email,
                'phone' => $this->phone,
                'serviceArea' => $this->serviceArea,
                'primaryGoal' => $this->primaryGoal,
                'coverageNeeds' => $this->coverageNeeds,
                'staffingFrequency' => $this->staffingFrequency,
            ],
            'status'     => 'new',
            'source_url' => request()->fullUrl(),
            'referrer_url' => request()->headers->get('referer'),
            'ip'         => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ]);

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Request received',
            'message' => 'We’ll reach out with agency early access details for Raleigh.',
        ]);

        $this->reset(['agencyName', 'contactName', 'email', 'phone', 'coverageNeeds']);
        $this->serviceArea = 'Raleigh, NC';
        $this->primaryGoal = 'get_clients';
        $this->staffingFrequency = 'sometimes';
    }

    public function render()
    {
        return view('livewire.agency-landing')->layout('layouts.marketing');
    }
}