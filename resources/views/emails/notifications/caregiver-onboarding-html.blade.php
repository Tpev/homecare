<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#eef5f8;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <span style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;">
        {{ $preheader ?? ($title.' - '.$body) }}
    </span>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eef5f8;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #d7e2ee;">
                    <tr>
                        <td style="padding:26px 24px;background:linear-gradient(130deg,#0a4a6e 0%,#0f8fb3 55%,#17b879 100%);color:#ffffff;">
                            <p style="margin:0 0 10px 0;font-size:12px;letter-spacing:0.14em;text-transform:uppercase;opacity:0.92;">{{ $eventLabel }}</p>
                            <h1 style="margin:0;font-size:30px;line-height:1.15;">{{ $appName }}</h1>
                            <p style="margin:12px 0 0 0;font-size:15px;line-height:1.6;max-width:520px;">
                                {{ $firstName !== '' ? 'Hi '.$firstName.',' : 'Hi,' }} {{ $body }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px 24px 18px;">
                            <h2 style="margin:0 0 12px 0;font-size:24px;line-height:1.25;color:#0f172a;">{{ $title }}</h2>

                            @if(!empty($checklist))
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 20px 0;">
                                    @foreach($checklist as $item)
                                        <tr>
                                            <td style="padding:7px 0;font-size:15px;line-height:1.5;color:#334155;">
                                                <span style="display:inline-block;width:18px;height:18px;line-height:18px;text-align:center;border-radius:999px;background:#dcfce7;color:#047857;font-weight:bold;margin-right:8px;">✓</span>
                                                {{ $item }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif

                            <table role="presentation" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="border-radius:10px;background:#0ea5e9;">
                                        <a href="{{ $url }}" style="display:inline-block;padding:12px 18px;color:#ffffff;text-decoration:none;font-weight:bold;font-size:14px;">
                                            {{ $ctaLabel }}
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:18px 0 0 0;font-size:12px;line-height:1.6;color:#64748b;">
                                Button not working? Copy and paste this link:
                                <br>
                                <a href="{{ $url }}" style="color:#0284c7;word-break:break-all;">{{ $rawUrl ?? $url }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 24px 24px;background:#f8fbff;border-top:1px solid #dbe7f3;">
                            <p style="margin:0 0 8px 0;font-size:13px;color:#334155;">
                                Need help? We’re happy to guide you:
                                <a href="{{ $supportUrl }}" style="color:#0369a1;text-decoration:underline;">{{ $supportUrl }}</a>
                            </p>
                            <p style="margin:0;font-size:12px;color:#64748b;">
                                {{ $appName }} · <a href="{{ $homeUrl }}" style="color:#0369a1;">{{ $homeUrl }}</a> · {{ $year }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    @if (!empty($openTrackingUrl))
        <img src="{{ $openTrackingUrl }}" alt="" width="1" height="1" style="display:block;border:0;outline:none;text-decoration:none;">
    @endif
</body>
</html>

