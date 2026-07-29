@php
    $data = $lead->data ?? [];
    $callbackTime = $data['callback_time_label'] ?? $data['callback_time'] ?? 'n/a';
    $requestedContact = $data['requested_contact'] ?? 'LoLo Care team';
    $reason = $data['reason'] ?? null;
    $notes = $data['notes'] ?? $reason;
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>LoLo Care callback request</title>
</head>
<body style="font-family: Arial, sans-serif; color: #24302d; background: #fff7ea; margin: 0; padding: 24px;">
    <div style="max-width: 680px; margin: 0 auto; background: #fffaf0; border: 1px solid #eadfce; border-radius: 18px; padding: 24px;">
        <p style="margin: 0 0 8px; color: #c96b55; font-size: 12px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase;">New callback request</p>
        <h1 style="margin: 0 0 12px; color: #23483f; font-size: 28px; line-height: 1.15;">A caller asked {{ $requestedContact }} to call them back.</h1>
        <p style="margin: 0 0 20px; color: #53645d;">This request came from a LoLo Care callback flow. Use the phone number and context below to follow up as soon as possible.</p>

        <table cellpadding="7" cellspacing="0" border="0" style="border-collapse: collapse; width: 100%; background: #fff7ea; border-radius: 12px;">
            <tr><td style="width: 180px;"><strong>Lead ID</strong></td><td>{{ $lead->id }}</td></tr>
            <tr><td><strong>Name</strong></td><td>{{ $lead->name ?: 'n/a' }}</td></tr>
            <tr><td><strong>Phone</strong></td><td><a href="tel:{{ preg_replace('/\D+/', '', (string) $lead->phone) }}" style="color: #23483f;">{{ $lead->phone ?: 'n/a' }}</a></td></tr>
            <tr><td><strong>Email</strong></td><td>{{ $lead->email ?: 'n/a' }}</td></tr>
            <tr><td><strong>ZIP</strong></td><td>{{ $lead->zip ?: $lead->location ?: 'n/a' }}</td></tr>
            <tr><td><strong>Care need</strong></td><td>{{ $data['service_type'] ?? 'n/a' }}</td></tr>
            <tr><td><strong>Best time to call</strong></td><td>{{ $callbackTime }}</td></tr>
            <tr><td><strong>Requested contact</strong></td><td>{{ $requestedContact }}</td></tr>
            <tr><td><strong>Reason</strong></td><td>{{ $reason ?: 'n/a' }}</td></tr>
            <tr><td><strong>Starting rate shown</strong></td><td>{{ $data['starting_rate'] ?? 'n/a' }}</td></tr>
            <tr><td><strong>Status</strong></td><td>{{ $lead->status }}</td></tr>
            <tr><td><strong>Submitted at</strong></td><td>{{ optional($lead->created_at)->toDateTimeString() ?: 'n/a' }}</td></tr>
        </table>

        @if (filled($notes))
            <h2 style="margin: 24px 0 8px; color: #23483f; font-size: 18px;">Notes from family</h2>
            <p style="margin: 0; white-space: pre-line; color: #24302d;">{{ $notes }}</p>
        @endif

        <h2 style="margin: 24px 0 8px; color: #23483f; font-size: 18px;">Source details</h2>
        <table cellpadding="7" cellspacing="0" border="0" style="border-collapse: collapse; width: 100%;">
            <tr><td style="width: 180px;"><strong>Source</strong></td><td>{{ $data['source'] ?? 'n/a' }}</td></tr>
            <tr><td><strong>Intent</strong></td><td>{{ $data['intent'] ?? 'n/a' }}</td></tr>
            <tr><td><strong>Source URL</strong></td><td>{{ $lead->source_url ?: 'n/a' }}</td></tr>
            <tr><td><strong>Referrer URL</strong></td><td>{{ $lead->referrer_url ?: 'n/a' }}</td></tr>
            <tr><td><strong>IP</strong></td><td>{{ $lead->ip ?: 'n/a' }}</td></tr>
            <tr><td><strong>User agent</strong></td><td>{{ $lead->user_agent ?: 'n/a' }}</td></tr>
        </table>

        <h2 style="margin: 24px 0 8px; color: #23483f; font-size: 18px;">Raw lead data</h2>
        <pre style="white-space: pre-wrap; background: #24302d; color: #fff7ea; border-radius: 12px; padding: 14px; font-size: 12px;">{{ json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>
</body>
</html>
