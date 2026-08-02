<x-emails.lolo-layout
    :preheader="$preheader"
    :eyebrow="$eventLabel"
    :title="$title"
    :intro="$body"
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
    @if (($firstName ?? '') !== '')
        <p style="margin:0 0 18px;color:#24302D;font-size:15px;line-height:1.6;">Hi {{ $firstName }},</p>
    @endif

    @if (!empty($emailDetails))
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin:0;border:1px solid #E3D6C5;border-radius:14px;background-color:#FFF7EA;">
            @foreach ($emailDetails as $detail)
                <tr>
                    <td valign="top" style="width:38%;padding:11px 14px;{{ !$loop->last ? 'border-bottom:1px solid #E3D6C5;' : '' }}color:#6E746F;font-size:13px;line-height:1.45;">{{ $detail['label'] }}</td>
                    <td valign="top" style="padding:11px 14px;{{ !$loop->last ? 'border-bottom:1px solid #E3D6C5;' : '' }}color:#23483F;font-size:14px;font-weight:bold;line-height:1.45;">{{ $detail['value'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (!empty($nextSteps))
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin-top:20px;background-color:#F1E5D2;border-left:4px solid #C96B55;border-radius:10px;">
            <tr>
                <td style="padding:16px;">
                    <p style="margin:0 0 8px;color:#23483F;font-size:14px;font-weight:bold;line-height:1.4;">What to do next</p>
                    @foreach ($nextSteps as $step)
                        <p style="margin:{{ $loop->first ? '0' : '8px' }} 0 0;color:#53645D;font-size:14px;line-height:1.55;">{{ $loop->iteration }}. {{ $step }}</p>
                    @endforeach
                </td>
            </tr>
        </table>
    @endif
</x-emails.lolo-layout>
