<?php

namespace App\Livewire;

use App\Models\Lead;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

class Landing extends Component
{
    use Interactions;

    public string $location = 'Raleigh, NC';
    public ?string $careType = 'companion';
    public ?string $when = 'asap';
    public string $email = '';

    protected array $rules = [
        'location' => ['required', 'string', 'max:80'],
        'careType'  => ['required', 'string', 'max:50'],
        'when'      => ['required', 'string', 'max:20'],
        'email'     => ['required', 'email', 'max:255'],
    ];

    protected array $messages = [
        'email.required' => 'Please enter an email to get notified.',
        'email.email'    => 'Please enter a valid email address.',
    ];

    public function submit(): void
    {
        $this->validate();

        Lead::create([
            'lead_type' => 'general',
            'name'      => null,
            'email'     => strtolower(trim($this->email)),
            'phone'     => null,
            'company'   => null,
            'location'  => $this->location,
            'zip'       => null,
            'data'      => [
                'location' => $this->location,
                'careType' => $this->careType,
                'when'     => $this->when,
                'email'    => $this->email,
            ],
            'status'       => 'new',
            'source_url'   => request()->fullUrl(),
            'referrer_url' => request()->headers->get('referer'),
            'ip'           => request()->ip(),
            'user_agent'   => (string) request()->userAgent(),
        ]);

        $this->toast()
            ->success('You’re on the list!', 'We’ll email you when Raleigh opens.')
            ->send();

        $this->reset('email');
    }

    public function render(): View
    {
        return view('livewire.landing')
            ->layout('layouts.marketing');
    }
}