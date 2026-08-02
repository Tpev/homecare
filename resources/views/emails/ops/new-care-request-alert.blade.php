<x-emails.lolo-layout
    :preheader="'New LoLo Care request #'.$careRequest->id.' was created.'"
    eyebrow="Care operations"
    :title="'New care request #'.$careRequest->id"
    intro="A family created a care request. Review its status, schedule, and coverage needs."
    :cta-url="route('admin.requests.show', $careRequest)"
    :raw-url="route('admin.requests.show', $careRequest)"
    cta-label="Review care request"
    :home-url="route('dashboard')"
    :logo-url="asset(\App\Support\MarketplaceNotificationPresentation::LOGO_PATH)"
    :year="now()->year"
>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border:1px solid #E3D6C5;border-radius:14px;background-color:#FFF7EA;">
        @foreach ([
            ['Request ID', '#'.$careRequest->id],
            ['Family user', '#'.$careRequest->family_user_id],
            ['Title', $careRequest->title],
            ['Type', str($careRequest->request_type ?: 'one_time')->headline()],
            ['Status', str($careRequest->status)->headline()],
            ['Location', collect([$careRequest->city, $careRequest->state, $careRequest->zip])->filter()->implode(', ') ?: 'Not provided'],
            ['Start', optional($careRequest->requested_start_at)->format('F j, Y · g:i A T') ?: 'Not scheduled'],
            ['End', optional($careRequest->requested_end_at)->format('F j, Y · g:i A T') ?: 'Not scheduled'],
            ['Created', optional($careRequest->created_at)->format('F j, Y · g:i A T')],
        ] as $detail)
            <tr><td valign="top" style="width:34%;padding:10px 14px;{{ !$loop->last ? 'border-bottom:1px solid #E3D6C5;' : '' }}color:#6E746F;font-size:13px;">{{ $detail[0] }}</td><td valign="top" style="padding:10px 14px;{{ !$loop->last ? 'border-bottom:1px solid #E3D6C5;' : '' }}color:#23483F;font-size:14px;font-weight:bold;">{{ $detail[1] }}</td></tr>
        @endforeach
    </table>
</x-emails.lolo-layout>
