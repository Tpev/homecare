<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>New family registration</title>
</head>
<body style="font-family: Arial, sans-serif; color: #0f172a;">
    <h2 style="margin-bottom: 8px;">New family registration</h2>
    <p style="margin-top: 0;">A new family account was created on HomeCare.</p>

    <table cellpadding="6" cellspacing="0" border="0" style="border-collapse: collapse;">
        <tr><td><strong>User ID</strong></td><td>{{ $user->id }}</td></tr>
        <tr><td><strong>Name</strong></td><td>{{ $user->name }}</td></tr>
        <tr><td><strong>Email</strong></td><td>{{ $user->email }}</td></tr>
        <tr><td><strong>Phone</strong></td><td>{{ $user->phone ?: 'n/a' }}</td></tr>
        <tr><td><strong>Location</strong></td><td>{{ trim(($user->city ?: '').' '.($user->state ?: '')) ?: 'n/a' }}</td></tr>
        <tr><td><strong>Created at</strong></td><td>{{ optional($user->created_at)->toDateTimeString() }}</td></tr>
    </table>
</body>
</html>

