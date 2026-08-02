LoLo Care
{{ $eventLabel }}

@if (($firstName ?? '') !== '')
Hi {{ $firstName }},

@endif
{{ $title }}

{{ $body }}

@if (!empty($emailDetails))
DETAILS
@foreach ($emailDetails as $detail)
{{ $detail['label'] }}: {{ $detail['value'] }}
@endforeach

@endif
@if (!empty($nextSteps))
WHAT TO DO NEXT
@foreach ($nextSteps as $step)
{{ $loop->iteration }}. {{ $step }}
@endforeach

@endif
{{ $ctaLabel }}: {{ $rawUrl ?? $url }}

Need help? Contact LoLo Care support: {{ $supportUrl }}
@if (!empty($preferencesUrl))
Manage notification preferences: {{ $preferencesUrl }}
@endif
LoLo Care: {{ $homeUrl }}
