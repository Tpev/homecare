<x-emails.lolo-layout
    preheader="A new family joined LoLo Care."
    eyebrow="Operations alert"
    title="New family registration"
    intro="A new family account was created on LoLo Care."
    :cta-url="route('admin.users.show', $user)"
    :raw-url="route('admin.users.show', $user)"
    cta-label="Review family account"
    :home-url="route('dashboard')"
    :logo-url="asset(\App\Support\MarketplaceNotificationPresentation::LOGO_PATH)"
    :year="now()->year"
>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border:1px solid #E3D6C5;border-radius:14px;background-color:#FFF7EA;">
        @foreach ([
            ['User ID', '#'.$user->id],
            ['Name', $user->name],
            ['Email', $user->email],
            ['Phone', $user->phone ?: 'Not provided'],
            ['Location', trim(($user->city ?: '').' '.($user->state ?: '')) ?: 'Not provided'],
            ['Created', optional($user->created_at)->format('F j, Y · g:i A T')],
        ] as $detail)
            <tr><td valign="top" style="width:34%;padding:10px 14px;{{ !$loop->last ? 'border-bottom:1px solid #E3D6C5;' : '' }}color:#6E746F;font-size:13px;">{{ $detail[0] }}</td><td valign="top" style="padding:10px 14px;{{ !$loop->last ? 'border-bottom:1px solid #E3D6C5;' : '' }}color:#23483F;font-size:14px;font-weight:bold;">{{ $detail[1] }}</td></tr>
        @endforeach
    </table>
</x-emails.lolo-layout>
