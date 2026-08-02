<x-emails.lolo-layout
    :preheader="'LoLo Care voice call '.($report['call_sid'] ?? 'unknown').' finished with outcome '.($report['outcome'] ?? 'unknown').'.'"
    eyebrow="Voice call report"
    :title="'Call outcome: '.str($report['outcome'] ?? 'unknown')->headline()"
    :intro="'A LoLo Care voice call completed. Review the caller, care need, outcome, and follow-up status below.'"
    :cta-url="route('admin.voice-ai.index')"
    :raw-url="route('admin.voice-ai.index')"
    cta-label="Open Voice AI"
    :home-url="route('dashboard')"
    :logo-url="asset(\App\Support\MarketplaceNotificationPresentation::LOGO_PATH)"
    :year="now()->year"
>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border:1px solid #E3D6C5;border-radius:14px;background-color:#FFF7EA;">
        @foreach ([
            ['Call SID', $report['call_sid'] ?? 'Not available'],
            ['Caller', $report['name'] ?? 'Not provided'],
            ['Phone', $report['phone'] ?? 'Not provided'],
            ['Care recipient', $report['care_recipient'] ?? 'Not provided'],
            ['Care needs', $report['care_needs'] ?? 'Not provided'],
            ['Urgency', str($report['urgency'] ?? 'unknown')->headline()],
            ['Location', collect([$report['city'] ?? null, $report['zip'] ?? null])->filter()->implode(', ') ?: 'Not provided'],
            ['Callback time', $report['callback_time'] ?? 'Not requested'],
            ['Outcome', str($report['outcome'] ?? 'unknown')->headline()],
            ['Call status', str($report['call_status'] ?? 'unknown')->headline()],
            ['Duration', isset($report['duration_seconds']) ? $report['duration_seconds'].' seconds' : 'Not available'],
            ['Callback requested', !empty($report['callback_requested']) ? 'Yes' : 'No'],
            ['Signup link sent', !empty($report['signup_link_sent']) ? 'Yes' : 'No'],
        ] as $detail)
            <tr><td valign="top" style="width:34%;padding:10px 14px;{{ !$loop->last ? 'border-bottom:1px solid #E3D6C5;' : '' }}color:#6E746F;font-size:13px;">{{ $detail[0] }}</td><td valign="top" style="padding:10px 14px;{{ !$loop->last ? 'border-bottom:1px solid #E3D6C5;' : '' }}color:#23483F;font-size:14px;font-weight:bold;">{{ $detail[1] }}</td></tr>
        @endforeach
    </table>

    @if (!empty($report['summary']))
        <div style="margin-top:18px;padding:16px;background-color:#F1E5D2;border-left:4px solid #C96B55;border-radius:10px;color:#53645D;font-size:14px;line-height:1.6;">
            <strong style="color:#23483F;">Call summary</strong><br>{{ $report['summary'] }}
        </div>
    @endif

    @if (!empty($report['transcript']))
        <div style="margin-top:18px;padding:16px;border:1px solid #E3D6C5;border-radius:10px;color:#53645D;font-size:13px;line-height:1.55;">
            <strong style="color:#23483F;">Transcript</strong>
            <p style="margin:8px 0 0;white-space:pre-wrap;">{{ $report['transcript'] }}</p>
        </div>
    @endif
</x-emails.lolo-layout>
