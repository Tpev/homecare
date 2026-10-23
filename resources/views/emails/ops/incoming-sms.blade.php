<x-emails.lolo-layout
    preheader="A new text message needs your attention."
    eyebrow="Incoming SMS"
    :title="'Text from '.($lead?->name ?: $sms->from_phone)"
    intro="A text message has arrived on the LoLo Care number."
    :cta-url="$url"
    :raw-url="$url"
    :cta-label="$lead ? 'Open lead in CRM' : 'Open CRM'"
    :home-url="route('dashboard')"
    :logo-url="asset(\App\Support\MarketplaceNotificationPresentation::LOGO_PATH)"
    :year="now()->year"
>
    <div style="white-space:pre-wrap;padding:16px;background:#FFF7EA;border-radius:12px;color:#23483F;">{{ $sms->body ?: '(Media message)' }}</div>
    <table role="presentation" width="100%" style="margin-top:16px;font-size:14px;">
        @foreach ([
            'Lead' => $lead ? '#'.$lead->id.' — '.$lead->name : 'No unique lead match; message is available in the admin SMS inbox.',
            'From' => $sms->from_phone,
            'To' => $sms->to_phone,
            'Received' => $sms->received_at?->format('M j, Y g:i:s A T'),
            'Status' => $sms->status,
            'Lead email' => $lead?->email,
            'Owner' => $lead?->assignedAdmin?->name,
            'Stage' => $lead?->stageLabel(),
            'Source' => $lead?->sourceLabel(),
            'Location' => $lead?->location ?: $lead?->zip,
            'Message ID' => $sms->twilio_sid,
        ] as $label => $value)
            @if(filled($value))<tr><td style="padding:6px;vertical-align:top;">{{ $label }}</td><td style="padding:6px;">{{ $value }}</td></tr>@endif
        @endforeach
    </table>
    @foreach($sms->media ?? [] as $media)
        @if(preg_match('/^https:\/\//i', $media['url'] ?? ''))
            <p><a href="{{ $media['url'] }}">Attachment {{ $loop->iteration }} ({{ $media['content_type'] ?? 'media' }})</a></p>
        @endif
    @endforeach
    @if($history->isNotEmpty())
        <h3 style="color:#23483F;">Recent conversation</h3>
        @foreach($history as $item)
            <p style="font-size:12px;color:#53645D;">{{ ucfirst($item->direction) }} · {{ ($item->received_at ?: $item->sent_at ?: $item->created_at)->format('M j, Y g:i A T') }} · {{ $item->status }}</p>
            <p style="white-space:pre-wrap;color:#23483F;">{{ $item->body }}</p>
        @endforeach
    @endif
</x-emails.lolo-layout>
