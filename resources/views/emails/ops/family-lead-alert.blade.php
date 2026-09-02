@php
    $form = (array) data_get($lead->data, 'form_answers', []);
    $isEscalation = $alertType === \App\Mail\Ops\FamilyLeadAlertMail::TYPE_ESCALATION;
    $receivedAt = $lead->submitted_at ?: $lead->created_at;
    $waitMinutes = $receivedAt ? (int) $receivedAt->diffInMinutes(now()) : null;
    $careNeeds = (array) ($form['care_needs'] ?? []);
@endphp
<x-emails.lolo-layout
    :preheader="$isEscalation ? 'A family lead has missed the first-call SLA.' : 'A new family lead is ready to call.'"
    :eyebrow="$isEscalation ? 'First-call escalation' : 'New family lead'"
    :title="$isEscalation ? 'This family is still waiting' : 'Please call '.($lead->name ?: 'this family').' now'"
    :intro="$isEscalation ? 'No first phone call has been recorded within the configured response-time target.' : 'The family has just submitted an enquiry. Their key details are below so the first call can be useful and personal.'"
    :cta-url="$isEscalation ? route('admin.family-acquisition.leads') : route('sdr.family-calling')"
    :raw-url="$isEscalation ? route('admin.family-acquisition.leads') : route('sdr.family-calling')"
    :cta-label="$isEscalation ? 'Open family CRM' : 'Open calling console'"
    :home-url="route('dashboard')"
    :logo-url="asset(\App\Support\MarketplaceNotificationPresentation::LOGO_PATH)"
    :year="now()->year"
>
    @if($isEscalation && $waitMinutes !== null)
        <div style="margin:0 0 18px;padding:14px 16px;border:1px solid #F1B9AD;border-radius:12px;background:#FFF1ED;color:#8F3F32;font-size:14px;font-weight:bold;">
            Waiting {{ $waitMinutes }} minutes without a recorded first call.
        </div>
    @endif

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border:1px solid #E3D6C5;border-radius:14px;background-color:#FFF7EA;">
        @foreach ([
            ['Lead ID', '#'.$lead->id],
            ['Name', $lead->name ?: 'Not provided'],
            ['Phone', $lead->phone ?: 'Not provided'],
            ['Email', $lead->email ?: 'Not provided'],
            ['Location', $lead->location ?: $lead->zip ?: 'Not provided'],
            ['Care for', $form['care_for'] ?? data_get($lead->data, 'recipient_relationship', 'Not provided')],
            ['Urgency', $form['urgency'] ?? data_get($lead->data, 'start_time_label', 'Not provided')],
            ['Best time', $form['preferred_call_time'] ?? data_get($lead->data, 'callback_time_label', 'Not provided')],
            ['Source', $lead->sourceLabel()],
            ['Received', optional($receivedAt)->format('F j, Y · g:i A T') ?: 'Not provided'],
        ] as $detail)
            <tr><td valign="top" style="width:34%;padding:10px 14px;{{ !$loop->last ? 'border-bottom:1px solid #E3D6C5;' : '' }}color:#6E746F;font-size:13px;">{{ $detail[0] }}</td><td valign="top" style="padding:10px 14px;{{ !$loop->last ? 'border-bottom:1px solid #E3D6C5;' : '' }}color:#23483F;font-size:14px;font-weight:bold;">{{ $detail[1] }}</td></tr>
        @endforeach
    </table>

    @if($careNeeds !== [])
        <p style="margin:18px 0 0;color:#23483F;font-size:14px;line-height:1.6;"><strong>Care needs:</strong> {{ implode(', ', $careNeeds) }}</p>
    @endif

    @if(trim((string) ($form['additional_details'] ?? '')) !== '')
        <div style="margin-top:16px;padding:16px;background-color:#F1E5D2;border-left:4px solid #C96B55;border-radius:10px;color:#53645D;font-size:14px;line-height:1.6;">
            <strong style="color:#23483F;">Family’s note</strong><br>{{ $form['additional_details'] }}
        </div>
    @endif
</x-emails.lolo-layout>
