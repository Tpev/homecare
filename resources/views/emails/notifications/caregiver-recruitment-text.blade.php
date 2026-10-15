LoLo Care
An invitation from LoLo

{{ ($firstName ?? '') !== '' ? 'Hi '.$firstName.',' : 'Hi,' }}

{{ $title }}

{{ $body }}

@foreach ($emailDetails as $detail)
{{ $detail['label'] }}: {{ $detail['value'] }}
@endforeach

{{ $opportunityMessage }}

@foreach ($nextSteps as $step)
{{ $step }}
@endforeach

{{ $ctaLabel }}: {{ $url }}
Direct job link: {{ $rawUrl ?? $url }}

The LoLo team

Need help? Contact LoLo Care support: {{ $supportUrl }}
@if (!empty($preferencesUrl))
Manage notification preferences: {{ $preferencesUrl }}
@endif
LoLo Care: {{ $homeUrl }}
