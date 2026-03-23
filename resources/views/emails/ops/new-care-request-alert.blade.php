<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>New care request</title>
</head>
<body style="font-family: Arial, sans-serif; color: #0f172a;">
    <h2 style="margin-bottom: 8px;">New care request published</h2>
    <p style="margin-top: 0;">A family just published a new care request.</p>

    <table cellpadding="6" cellspacing="0" border="0" style="border-collapse: collapse;">
        <tr><td><strong>Request ID</strong></td><td>{{ $careRequest->id }}</td></tr>
        <tr><td><strong>Family user ID</strong></td><td>{{ $careRequest->family_user_id }}</td></tr>
        <tr><td><strong>Title</strong></td><td>{{ $careRequest->title }}</td></tr>
        <tr><td><strong>Type</strong></td><td>{{ $careRequest->request_type ?: 'one_time' }}</td></tr>
        <tr><td><strong>Status</strong></td><td>{{ $careRequest->status }}</td></tr>
        <tr><td><strong>Location</strong></td><td>{{ trim(($careRequest->city ?: '').', '.($careRequest->state ?: '')) ?: 'n/a' }}</td></tr>
        <tr><td><strong>ZIP</strong></td><td>{{ $careRequest->zip ?: 'n/a' }}</td></tr>
        <tr><td><strong>Start</strong></td><td>{{ optional($careRequest->requested_start_at)->toDateTimeString() ?: 'n/a' }}</td></tr>
        <tr><td><strong>End</strong></td><td>{{ optional($careRequest->requested_end_at)->toDateTimeString() ?: 'n/a' }}</td></tr>
        <tr><td><strong>Created at</strong></td><td>{{ optional($careRequest->created_at)->toDateTimeString() }}</td></tr>
    </table>
</body>
</html>

