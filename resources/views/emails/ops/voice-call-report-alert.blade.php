@php
    $report = $report ?? [];
    $metadata = $report['metadata'] ?? [];
@endphp

<h1>Voice Call Report</h1>

<p><strong>Call SID:</strong> {{ $report['call_sid'] ?? 'n/a' }}</p>
<p><strong>Caller phone:</strong> {{ $report['phone'] ?? 'n/a' }}</p>
<p><strong>Lead type:</strong> {{ $report['lead_type'] ?? 'n/a' }}</p>
<p><strong>Intent:</strong> {{ $report['intent'] ?? 'n/a' }}</p>
<p><strong>Outcome:</strong> {{ $report['outcome'] ?? 'n/a' }}</p>
<p><strong>Status:</strong> {{ $report['call_status'] ?? 'n/a' }}</p>
<p><strong>Duration:</strong> {{ $report['duration_seconds'] ?? 'n/a' }} seconds</p>
<p><strong>Started at:</strong> {{ $report['started_at'] ?? 'n/a' }}</p>
<p><strong>Ended at:</strong> {{ $report['ended_at'] ?? 'n/a' }}</p>
<p><strong>Callback requested:</strong> {{ !empty($report['callback_requested']) ? 'yes' : 'no' }}</p>
<p><strong>Signup link sent:</strong> {{ !empty($report['signup_link_sent']) ? 'yes' : 'no' }}</p>

@if (!empty($report['summary']))
    <h2>Summary</h2>
    <p>{{ $report['summary'] }}</p>
@endif

@if ($metadata !== [])
    <h2>Metadata</h2>
    <pre>{{ json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
@endif

@if (!empty($report['transcript']))
    <h2>Transcript</h2>
    <pre>{{ $report['transcript'] }}</pre>
@endif
