<x-emails.lolo-layout
    preheader="A caregiver completed setup and is ready for LoLo Care review."
    eyebrow="Caregiver review"
    title="Caregiver ready for review"
    intro="A caregiver completed onboarding and is waiting for the operations team to review the profile."
    :cta-url="route('admin.users.show', $user)"
    :raw-url="route('admin.users.show', $user)"
    cta-label="Review caregiver"
    :home-url="route('dashboard')"
    :logo-url="asset(\App\Support\MarketplaceNotificationPresentation::LOGO_PATH)"
    :year="now()->year"
>
    @php
        $profile->loadMissing(['careExperiences', 'certifications.type']);
        $careExperienceSummary = $profile->careExperiences->pluck('label')->implode(', ')
            ?: ($profile->care_experience_answered_at ? 'No specialized care experience selected' : 'Not answered (legacy profile)');
        $credentialSummary = $profile->certifications
            ->map(fn ($credential) => $credential->displayName().' ('.str($credential->verification_status)->headline().')')
            ->implode(', ')
            ?: ($profile->certifications_answered_at ? 'No current certifications selected' : 'Not answered (legacy profile)');
    @endphp
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border:1px solid #E3D6C5;border-radius:14px;background-color:#FFF7EA;">
        @foreach ([
            ['Profile ID', '#'.$profile->id],
            ['User ID', '#'.$user->id],
            ['Caregiver', $user->name],
            ['Email', $user->email],
            ['Status', str($profile->status)->headline()],
            ['Location', trim(($user->city ?: '').' '.($user->state ?: '')) ?: 'Not provided'],
            ['Experience', is_null($profile->years_experience) ? 'Not provided' : $profile->years_experience.' years'],
            ['Care experience', $careExperienceSummary],
            ['Credentials', $credentialSummary],
            ['Submitted', optional($profile->review_submitted_at ?: $profile->updated_at)->format('F j, Y · g:i A T')],
        ] as $detail)
            <tr><td valign="top" style="width:34%;padding:10px 14px;{{ !$loop->last ? 'border-bottom:1px solid #E3D6C5;' : '' }}color:#6E746F;font-size:13px;">{{ $detail[0] }}</td><td valign="top" style="padding:10px 14px;{{ !$loop->last ? 'border-bottom:1px solid #E3D6C5;' : '' }}color:#23483F;font-size:14px;font-weight:bold;">{{ $detail[1] }}</td></tr>
        @endforeach
    </table>
</x-emails.lolo-layout>
