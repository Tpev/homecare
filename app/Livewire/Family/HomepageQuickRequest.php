<?php

namespace App\Livewire\Family;

use Livewire\Component;

class HomepageQuickRequest extends Component
{
    public string $service_type = 'Companion care';

    public string $zip = '';

    public string $time_preference = 'today_afternoon';

    /** @var array<int, string> */
    public array $serviceOptions = [
        'Companion care',
        'Meal prep',
        'Errands and rides',
        'Light housekeeping',
    ];

    /** @var array<string, string> */
    public array $timeOptions = [
        'now' => 'As soon as possible',
        'today_afternoon' => 'Today, 4 PM',
        'tomorrow_morning' => 'Tomorrow morning',
        'this_week' => 'Later this week',
    ];

    public function mount(): void
    {
        $this->zip = '27601';
    }

    public function continueToCallback()
    {
        $validated = $this->validate([
            'service_type' => ['required', 'string', 'max:100'],
            'zip' => ['required', 'string', 'min:3', 'max:12'],
            'time_preference' => ['required', 'string'],
        ]);

        return $this->redirect(route('landing.get-care', [
            'service_type' => $validated['service_type'],
            'zip' => trim($validated['zip']),
            'time_preference' => $validated['time_preference'],
        ], absolute: false), navigate: true);
    }

    public function render()
    {
        return view('livewire.family.homepage-quick-request');
    }
}
