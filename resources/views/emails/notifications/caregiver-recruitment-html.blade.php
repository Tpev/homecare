<x-emails.lolo-layout
    :preheader="$preheader"
    eyebrow="An invitation from LoLo"
    :title="$title"
    :cta-url="$url"
    :raw-url="$rawUrl ?? $url"
    :cta-label="$ctaLabel"
    :support-url="$supportUrl"
    :preferences-url="$preferencesUrl ?? null"
    :home-url="$homeUrl"
    :logo-url="$logoUrl"
    :year="$year"
    :open-tracking-url="$openTrackingUrl"
>
    <p style="margin:0 0 12px;color:#24302D;font-size:16px;line-height:1.6;">{{ ($firstName ?? '') !== '' ? 'Hi '.$firstName.',' : 'Hi,' }}</p>
    <p style="margin:0 0 20px;color:#53645D;font-size:16px;line-height:1.65;">{{ $body }}</p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin:0;border:1px solid #E3D6C5;border-radius:14px;background-color:#FFF7EA;">
        @foreach ($emailDetails as $detail)
            <tr>
                <td valign="top" style="width:34%;padding:11px 14px;{{ !$loop->last ? 'border-bottom:1px solid #E3D6C5;' : '' }}color:#6E746F;font-size:13px;line-height:1.45;">{{ $detail['label'] }}</td>
                <td valign="top" style="padding:11px 14px;{{ !$loop->last ? 'border-bottom:1px solid #E3D6C5;' : '' }}color:#23483F;font-size:14px;font-weight:bold;line-height:1.45;">{{ $detail['value'] }}</td>
            </tr>
        @endforeach
    </table>

    <p style="margin:20px 0 0;color:#53645D;font-size:15px;line-height:1.65;">{{ $opportunityMessage }}</p>
    @foreach ($nextSteps as $step)
        <p style="margin:16px 0 0;color:#23483F;font-size:15px;line-height:1.6;">{{ $step }}</p>
    @endforeach
    <p style="margin:18px 0 0;color:#53645D;font-size:15px;line-height:1.6;">The LoLo team</p>
</x-emails.lolo-layout>
