<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f6fb;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <span style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;">
        {{ $title }} - {{ $body }}
    </span>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f6fb;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #d7e2ee;">
                    <tr>
                        <td style="padding:24px;background:linear-gradient(120deg,#0c4a6e,#0ea5e9 55%,#10b981);color:#ffffff;">
                            <p style="margin:0 0 8px 0;font-size:13px;letter-spacing:0.12em;text-transform:uppercase;opacity:0.9;">{{ $eventLabel }}</p>
                            <h1 style="margin:0;font-size:28px;line-height:1.2;">{{ $appName }}</h1>
                            <p style="margin:10px 0 0 0;font-size:15px;line-height:1.5;max-width:520px;">
                                Real-time care marketplace updates for faster decisions.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 24px 18px 24px;">
                            <h2 style="margin:0 0 10px 0;font-size:24px;line-height:1.25;color:#0f172a;">{{ $title }}</h2>
                            <p style="margin:0 0 20px 0;font-size:15px;line-height:1.65;color:#334155;">{{ $body }}</p>

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
                                <a href="{{ $url }}" style="color:#0284c7;word-break:break-all;">{{ $url }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 24px 24px 24px;background:#f8fbff;border-top:1px solid #dbe7f3;">
                            <p style="margin:0 0 8px 0;font-size:13px;color:#334155;">
                                Need help now? Open support:
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
</body>
</html>
