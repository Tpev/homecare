@php
    $data = (array) $lead->data;
    $callbackTime = $data['callback_time_label'] ?? $data['callback_time'] ?? 'Not provided';
    $requestedContact = $data['requested_contact'] ?? 'LoLo Care team';
    $reason = $data['callback_reason'] ?? $data['reason'] ?? $data['notes'] ?? '';
@endphp
<x-emails.lolo-layout
    :preheader="($lead->name ?: 'A caller').' requested a callback from '.$requestedContact.'.'"
    eyebrow="Callback requested"
    :title="($lead->name ?: 'A caller').' asked for a callback'"
    intro="Use the contact details and requested time below to follow up."
    :cta-url="route('admin.crm.index')"
    :raw-url="route('admin.crm.index')"
    cta-label="Open CRM"
    :home-url="route('dashboard')"
    :logo-url="asset(\App\Support\MarketplaceNotificationPresentation::LOGO_PATH)"
    :year="now()->year"
>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border:1px solid #E3D6C5;border-radius:14px;background-color:#FFF7EA;">
        @foreach ([
            ['Lead ID', '#'.$lead->id],
            ['Name', $lead->name ?: 'Not provided'],
            ['Phone', $lead->phone ?: 'Not provided'],
            ['Email', $lead->email ?: 'Not provided'],
            ['Location', $lead->zip ?: $lead->location ?: 'Not provided'],
            ['Care need', $data['service_type'] ?? 'Not provided'],
            ['Best time to call', $callbackTime],
            ['Requested contact', $requestedContact],
            ['Starting rate shown', $data['starting_rate'] ?? 'Not provided'],
            ['Status', str($lead->status)->headline()],
            ['Submitted', optional($lead->created_at)->format('F j, Y · g:i A T')],
        ] as $detail)
            <tr><td valign="top" style="width:34%;padding:10px 14px;{{ !$loop->last ? 'border-bottom:1px solid #E3D6C5;' : '' }}color:#6E746F;font-size:13px;">{{ $detail[0] }}</td><td valign="top" style="padding:10px 14px;{{ !$loop->last ? 'border-bottom:1px solid #E3D6C5;' : '' }}color:#23483F;font-size:14px;font-weight:bold;">{{ $detail[1] }}</td></tr>
        @endforeach
    </table>

    @if (trim((string) $reason) !== '')
        <div style="margin-top:18px;padding:16px;background-color:#F1E5D2;border-left:4px solid #C96B55;border-radius:10px;color:#53645D;font-size:14px;line-height:1.6;">
            <strong style="color:#23483F;">Family’s note</strong><br>{{ $reason }}
        </div>
    @endif

    <p style="margin:18px 0 0;color:#6E746F;font-size:12px;line-height:1.55;">
        Source: {{ $data['source'] ?? 'Not provided' }}
        @if ($lead->source_url)
            &middot; {{ $lead->source_url }}
        @endif
    </p>
</x-emails.lolo-layout>
