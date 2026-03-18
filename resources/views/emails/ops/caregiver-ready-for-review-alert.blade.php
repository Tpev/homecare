<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Caregiver ready for review</title>
</head>
<body style="font-family: Arial, sans-serif; color: #0f172a;">
    <h2 style="margin-bottom: 8px;">Caregiver ready for review</h2>
    <p style="margin-top: 0;">A caregiver submitted onboarding and is now under review.</p>

    <table cellpadding="6" cellspacing="0" border="0" style="border-collapse: collapse;">
        <tr><td><strong>Profile ID</strong></td><td>{{ $profile->id }}</td></tr>
        <tr><td><strong>User ID</strong></td><td>{{ $user->id }}</td></tr>
        <tr><td><strong>Name</strong></td><td>{{ $user->name }}</td></tr>
        <tr><td><strong>Email</strong></td><td>{{ $user->email }}</td></tr>
        <tr><td><strong>Status</strong></td><td>{{ $profile->status }}</td></tr>
        <tr><td><strong>City / State</strong></td><td>{{ trim(($user->city ?: '').' '.($user->state ?: '')) ?: 'n/a' }}</td></tr>
        <tr><td><strong>Experience</strong></td><td>{{ is_null($profile->years_experience) ? 'n/a' : $profile->years_experience.' years' }}</td></tr>
        <tr><td><strong>Submitted at</strong></td><td>{{ optional($profile->review_submitted_at)->toDateTimeString() ?: optional($profile->updated_at)->toDateTimeString() }}</td></tr>
    </table>
</body>
</html>

