<x-emails.lolo-layout
    :preheader="$preheader"
    :eyebrow="$eventLabel"
    :title="$title"
    :intro="(($firstName ?? '') !== '' ? 'Hi '.$firstName.', ' : '').$body"
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
    @if (!empty($checklist))
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin:0;border:1px solid #E3D6C5;border-radius:14px;background-color:#FFF7EA;">
            <tr>
                <td style="padding:16px 16px 8px;">
                    <p style="margin:0;color:#23483F;font-size:14px;font-weight:bold;line-height:1.4;">Your caregiver setup</p>
                </td>
            </tr>
            @foreach ($checklist as $item)
                <tr>
                    <td style="padding:{{ $loop->first ? '6px' : '8px' }} 16px {{ $loop->last ? '16px' : '8px' }};color:#53645D;font-size:15px;line-height:1.5;">
                        <span style="display:inline-block;width:20px;height:20px;margin-right:8px;border-radius:50%;background-color:#DDE9DF;color:#23483F;font-size:13px;font-weight:bold;line-height:20px;text-align:center;">&#10003;</span>
                        {{ $item }}
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    <p style="margin:18px 0 0;color:#53645D;font-size:14px;line-height:1.6;">
        Completing these details helps families understand your experience, availability, and the care you are comfortable providing.
    </p>
</x-emails.lolo-layout>
