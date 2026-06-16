<?php

namespace App\Livewire\Family;

use App\Models\Lead;
use App\Services\Ops\OpsAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

    public bool $consent_to_contact = false;

    public bool $submitted = false;

    public string $landing_url = '';

    public ?string $landing_referrer_url = null;

    /** @var array<string, string> */
    public array $tracking = [];

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

        $this->landing_url = request()->fullUrl();
        $this->landing_referrer_url = request()->headers->get('referer');
        $this->tracking = $this->trackingFromRequest(request());

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
            'consent_to_contact' => ['accepted'],
        ]);

        $source = $this->leadSource();
        $sourceDetail = $this->leadSourceDetail();

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
                'consent_to_contact' => true,
                'tracking' => $this->tracking,
                'meta_pixel_event' => 'Lead',
            ],
            'status' => 'new',
            'source' => $source,
            'source_detail' => $sourceDetail,
            'external_source' => $source === 'meta_ads' ? 'meta_ads' : null,
            'source_url' => $this->landing_url ?: request()->fullUrl(),
            'referrer_url' => $this->landing_referrer_url ?: request()->headers->get('referer'),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        app(OpsAlertService::class)->notifyCallbackRequestCreated($lead);

        $this->submitted = true;

        $this->dispatch(
            'lolo-callback-submitted',
            lead_id: $lead->id,
            event_name: 'Lead',
            content_name: 'Family callback request',
            content_category: 'home_care_callback',
            value: 45,
            currency: 'USD',
        );
    }

    public function render()
    {
        return view('livewire.family.callback-request');
    }

    /**
     * @return array<string, string>
     */
    private function trackingFromRequest(Request $request): array
    {
        $keys = [
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
            'fbclid',
            'gclid',
            'msclkid',
            'campaign_id',
            'adset_id',
            'ad_id',
            'placement',
            'site_source_name',
        ];

        $tracking = [];

        foreach ($keys as $key) {
            $value = $request->query($key);

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $tracking[$key] = Str::limit(trim($value), 255, '');
        }

        return $tracking;
    }

    private function leadSource(): string
    {
        $utmSource = strtolower((string) ($this->tracking['utm_source'] ?? ''));

        if (
            str_contains($utmSource, 'meta')
            || str_contains($utmSource, 'facebook')
            || str_contains($utmSource, 'instagram')
            || array_key_exists('fbclid', $this->tracking)
        ) {
            return 'meta_ads';
        }

        if (str_contains($utmSource, 'google') || array_key_exists('gclid', $this->tracking)) {
            return 'google_ads';
        }

        if ($utmSource !== '') {
            return Str::limit($utmSource, 40, '');
        }

        return 'callback_page';
    }

    private function leadSourceDetail(): ?string
    {
        $parts = array_filter([
            $this->tracking['utm_campaign'] ?? null,
            $this->tracking['utm_term'] ?? null,
            $this->tracking['utm_content'] ?? null,
        ], fn (?string $value) => filled($value));

        if ($parts === []) {
            return null;
        }

        return Str::limit(implode(' / ', $parts), 255, '');
    }
}
