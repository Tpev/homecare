<?php

namespace App\Livewire\Family;

use App\Models\Lead;
use App\Services\Ops\OpsAlertService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CallbackRequest extends Component
{
    public string $full_name = '';

    public string $phone = '';

    public string $email = '';

    public string $zip = '';

    public string $service_type = 'Companion care';

    public string $callback_time = 'today';

    public string $notes = '';

    public bool $submitted = false;

    /** @var array<int, string> */
    public array $serviceOptions = [
        'Companion care',
        'Meal prep',
        'Errands and rides',
        'Light housekeeping',
        'Not sure yet',
    ];

    /** @var array<string, string> */
    public array $callbackOptions = [
        'today' => 'Today',
        'tomorrow_morning' => 'Tomorrow morning',
        'tomorrow_afternoon' => 'Tomorrow afternoon',
        'this_week' => 'Later this week',
    ];

    public function mount(): void
    {
        $serviceType = (string) request()->query('service_type', $this->service_type);
        $timePreference = (string) request()->query('time_preference', $this->callback_time);

        if (in_array($serviceType, $this->serviceOptions, true)) {
            $this->service_type = $serviceType;
        }

        if (array_key_exists($timePreference, $this->callbackOptions)) {
            $this->callback_time = $timePreference;
        }

        $this->zip = trim((string) request()->query('zip', ''));
    }

    public function submit(): void
    {
        $validated = $this->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'min:7', 'max:30'],
            'email' => ['nullable', 'email', 'max:160'],
            'zip' => ['nullable', 'string', 'max:12'],
            'service_type' => ['required', 'string', Rule::in($this->serviceOptions)],
            'callback_time' => ['required', 'string', Rule::in(array_keys($this->callbackOptions))],
            'notes' => ['nullable', 'string', 'max:1200'],
        ]);

        $lead = Lead::query()->create([
            'lead_type' => 'family',
            'name' => trim($validated['full_name']),
            'email' => filled($validated['email']) ? trim($validated['email']) : null,
            'phone' => trim($validated['phone']),
            'location' => filled($validated['zip']) ? trim($validated['zip']) : null,
            'zip' => filled($validated['zip']) ? trim($validated['zip']) : null,
            'data' => [
                'source' => 'family_callback_page',
                'intent' => 'callback_request',
                'service_type' => $validated['service_type'],
                'callback_time' => $validated['callback_time'],
                'callback_time_label' => $this->callbackOptions[$validated['callback_time']],
                'notes' => filled($validated['notes']) ? trim($validated['notes']) : null,
                'starting_rate' => '$30/hr',
            ],
            'status' => 'new',
            'source_url' => request()->fullUrl(),
            'referrer_url' => request()->headers->get('referer'),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        app(OpsAlertService::class)->notifyCallbackRequestCreated($lead);

        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.family.callback-request');
    }
}
