{{ $appName }}
{{ $eventLabel }}

{{ $title }}
{{ $body }}

@if (!empty($emailDetails))
@foreach ($emailDetails as $detail)
{{ $detail['label'] }}: {{ $detail['value'] }}
@endforeach
@endif

{{ $ctaLabel }}: {{ $url }}

Need help? Support: {{ $supportUrl }}
Home: {{ $homeUrl }}
