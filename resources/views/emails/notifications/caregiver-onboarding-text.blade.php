LoLo Care
{{ $eventLabel }}

{{ ($firstName ?? '') !== '' ? 'Hi '.$firstName.',' : 'Hi,' }}

{{ $title }}

{{ $body }}

@if (!empty($checklist))
YOUR CAREGIVER SETUP
@foreach ($checklist as $item)
- {{ $item }}
@endforeach

@endif
Completing these details helps families understand your experience, availability, and the care you are comfortable providing.

{{ $ctaLabel }}: {{ $rawUrl ?? $url }}

Need help? Contact LoLo Care support: {{ $supportUrl }}
@if (!empty($preferencesUrl))
Manage notification preferences: {{ $preferencesUrl }}
@endif
LoLo Care: {{ $homeUrl }}
