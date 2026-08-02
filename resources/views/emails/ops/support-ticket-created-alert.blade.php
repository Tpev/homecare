<x-emails.lolo-layout
    preheader="A new LoLo Care support request needs operations review."
    eyebrow="Operations alert"
    :title="'New '.strtolower($ticket->priority ?: 'normal').' priority support request'"
    :intro="'A '.($ticket->opener?->role ?: 'user').' opened a support request that is now waiting in the operations queue.'"
    :cta-url="$supportUrl"
    :raw-url="$supportUrl"
    cta-label="Open support request"
    :home-url="$homeUrl"
    :logo-url="$logoUrl"
    :year="$year"
>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border:1px solid #E3D6C5;border-radius:14px;background-color:#FFF7EA;">
        @foreach ([
            ['Support request', '#'.$ticket->id],
            ['Opened by', ($ticket->opener?->name ?: 'Former user').' · '.($ticket->opener?->email ?: 'email unavailable')],
            ['Role', ucfirst((string) ($ticket->opener?->role ?: 'unknown'))],
            ['Category', str($ticket->category)->headline()],
            ['Priority', str($ticket->priority)->headline()],
            ['Subject', $ticket->subject],
            ['Created', optional($ticket->created_at)->format('F j, Y · g:i A T')],
        ] as $detail)
            <tr>
                <td valign="top" style="width:36%;padding:10px 14px;{{ !$loop->last ? 'border-bottom:1px solid #E3D6C5;' : '' }}color:#6E746F;font-size:13px;line-height:1.45;">{{ $detail[0] }}</td>
                <td valign="top" style="padding:10px 14px;{{ !$loop->last ? 'border-bottom:1px solid #E3D6C5;' : '' }}color:#23483F;font-size:14px;font-weight:bold;line-height:1.45;">{{ $detail[1] }}</td>
            </tr>
        @endforeach
    </table>
    <p style="margin:18px 0 0;color:#53645D;font-size:14px;line-height:1.6;"><strong>Initial description:</strong><br>{{ $ticket->description }}</p>
</x-emails.lolo-layout>
