{{ $appName }}
{{ $eventLabel }}

{{ $firstName !== '' ? 'Hi '.$firstName.',' : 'Hi,' }}
{{ $title }}

{{ $body }}

@if(!empty($checklist))
Checklist:
@foreach($checklist as $item)
- {{ $item }}
@endforeach

@endif
{{ $ctaLabel }}: {{ $url }}

Need help? {{ $supportUrl }}
Home: {{ $homeUrl }}

