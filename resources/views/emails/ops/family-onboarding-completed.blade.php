<x-emails.lolo-layout
    preheader="A family has completed onboarding."
    eyebrow="Operations alert"
    :title="$heading"
    :intro="$summary"
    :cta-url="$actionUrl"
    :raw-url="$actionUrl"
    cta-label="Review onboarding"
    :home-url="route('dashboard')"
    :logo-url="asset(\App\Support\MarketplaceNotificationPresentation::LOGO_PATH)"
    :year="now()->year"
>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #E3D6C5;background:#FFF7EA;">
        @foreach($details as $detail)
            <tr>
                <td valign="top" style="width:34%;padding:10px 14px;color:#6E746F;font-size:13px;">{{ $detail['label'] }}</td>
                <td valign="top" style="padding:10px 14px;color:#23483F;font-size:14px;white-space:pre-wrap;overflow-wrap:anywhere;">{{ $detail['value'] }}</td>
            </tr>
        @endforeach
    </table>
</x-emails.lolo-layout>
