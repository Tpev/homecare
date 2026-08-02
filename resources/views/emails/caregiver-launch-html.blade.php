<x-emails.lolo-layout
    :preheader="$previewText"
    eyebrow="Caregiver community"
    title="Welcome to LoLo Care"
    :intro="($firstName !== '' ? 'Hi '.$firstName.', ' : '').'LoLo Care helps independent caregivers connect directly with families looking for dependable local support.'"
    :cta-url="$websiteUrl"
    :raw-url="$websiteUrl"
    cta-label="Open LoLo Care"
    :home-url="$websiteUrl"
    :logo-url="$logoUrl"
    :year="now()->year"
>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border:1px solid #E3D6C5;border-radius:14px;background-color:#FFF7EA;">
        @foreach ([
            'Keep your profile, availability, and care preferences current.',
            'Review the schedule, location, care tasks, and rate before accepting work.',
            'Use LoLo Care messages and visit tools to keep families informed.',
        ] as $item)
            <tr>
                <td style="padding:{{ $loop->first ? '16px' : '8px' }} 16px {{ $loop->last ? '16px' : '8px' }};color:#53645D;font-size:15px;line-height:1.55;">
                    <span style="display:inline-block;width:20px;height:20px;margin-right:8px;border-radius:50%;background-color:#DDE9DF;color:#23483F;font-size:13px;font-weight:bold;line-height:20px;text-align:center;">&#10003;</span>{{ $item }}
                </td>
            </tr>
        @endforeach
    </table>
    <p style="margin:18px 0 0;color:#53645D;font-size:14px;line-height:1.6;">Questions? Call {{ $phone }} or visit LoLo Care online.</p>
    <p style="margin:12px 0 0;color:#6E746F;font-size:12px;line-height:1.6;">
        Follow LoLo Care: <a href="{{ $facebookUrl }}" style="color:#0F5B52;">Facebook</a> &middot; <a href="{{ $instagramUrl }}" style="color:#0F5B52;">Instagram</a>
    </p>
</x-emails.lolo-layout>
