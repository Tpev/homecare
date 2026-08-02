@props([
    'preheader' => '',
    'eyebrow' => 'LoLo Care update',
    'title',
    'intro' => null,
    'ctaUrl' => null,
    'rawUrl' => null,
    'ctaLabel' => null,
    'supportUrl' => null,
    'preferencesUrl' => null,
    'homeUrl' => null,
    'logoUrl' => null,
    'year' => null,
    'openTrackingUrl' => null,
])
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background-color:#F5ECDE;font-family:Arial,Helvetica,sans-serif;color:#24302D;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;mso-hide:all;">
        {{ $preheader }}&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background-color:#F5ECDE;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:640px;background-color:#FFFCF7;border:1px solid #E3D6C5;border-radius:20px;overflow:hidden;">
                    <tr>
                        <td style="padding:24px 24px 20px;background-color:#FFF7EA;border-bottom:1px solid #E3D6C5;">
                            @if ($logoUrl)
                                <a href="{{ $homeUrl ?: '#' }}" style="text-decoration:none;">
                                    <img src="{{ $logoUrl }}" width="210" alt="LoLo Care" style="display:block;width:210px;max-width:70%;height:auto;border:0;outline:none;text-decoration:none;">
                                </a>
                            @else
                                <p style="margin:0;color:#23483F;font-family:Georgia,'Times New Roman',serif;font-size:28px;font-weight:bold;line-height:1.2;">LoLo Care</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 24px 10px;">
                            <p style="margin:0 0 10px;color:#A84F3F;font-size:12px;font-weight:bold;letter-spacing:0.12em;line-height:1.4;text-transform:uppercase;">{{ $eyebrow }}</p>
                            <h1 style="margin:0;color:#23483F;font-family:Georgia,'Times New Roman',serif;font-size:30px;font-weight:normal;line-height:1.2;">{{ $title }}</h1>
                            @if ($intro)
                                <p style="margin:16px 0 0;color:#53645D;font-size:16px;line-height:1.65;">{{ $intro }}</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:10px 24px 28px;">
                            {{ $slot }}

                            @if ($ctaUrl && $ctaLabel)
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top:24px;">
                                    <tr>
                                        <td bgcolor="#A84F3F" style="border-radius:12px;">
                                            <a href="{{ $ctaUrl }}" style="display:inline-block;min-width:180px;padding:14px 22px;color:#FFFFFF;font-size:15px;font-weight:bold;line-height:20px;text-align:center;text-decoration:none;">{{ $ctaLabel }}</a>
                                        </td>
                                    </tr>
                                </table>

                                <p style="margin:18px 0 0;color:#6E746F;font-size:12px;line-height:1.6;">
                                    If the button does not open, copy this address into your browser:<br>
                                    <a href="{{ $ctaUrl }}" style="color:#0F5B52;text-decoration:underline;word-break:break-all;">{{ $rawUrl ?: $ctaUrl }}</a>
                                </p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 24px;background-color:#23483F;">
                            @if ($supportUrl)
                                <p style="margin:0 0 8px;color:#FFF7EA;font-size:13px;line-height:1.6;">
                                    Need help? <a href="{{ $supportUrl }}" style="color:#FFF7EA;font-weight:bold;text-decoration:underline;">Contact LoLo Care support</a>.
                                </p>
                            @endif
                            @if ($preferencesUrl)
                                <p style="margin:0 0 8px;color:#E8DED0;font-size:12px;line-height:1.6;">
                                    <a href="{{ $preferencesUrl }}" style="color:#FFF7EA;text-decoration:underline;">Manage notification preferences</a>
                                </p>
                            @endif
                            <p style="margin:0;color:#E8DED0;font-size:12px;line-height:1.6;">
                                LoLo Care Inc
                                @if ($year)
                                    &middot; {{ $year }}
                                @endif
                                @if ($homeUrl)
                                    &middot; <a href="{{ $homeUrl }}" style="color:#FFF7EA;text-decoration:underline;">carelolo.com</a>
                                @endif
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    @if ($openTrackingUrl)
        <img src="{{ $openTrackingUrl }}" alt="" width="1" height="1" style="display:block;width:1px;height:1px;border:0;outline:none;">
    @endif
</body>
</html>
