<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $emailSubject }}</title></head>
<body style="margin:0;background:#F7F3EC;font-family:Arial,Helvetica,sans-serif;color:#173F35;">
    <div style="display:none;max-height:0;overflow:hidden;">Create your free account and post a care request in about 2 minutes.</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 16px;"><tr><td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:white;border:1px solid #E5DED3;border-radius:20px;overflow:hidden;">
            <tr><td style="padding:28px 32px;background:#23483F;color:#fff;font-size:25px;font-weight:bold;">LoLo <span style="font-weight:normal;font-size:18px;">Care</span></td></tr>
            <tr><td style="padding:32px;">
                <p style="margin:0 0 20px;font-size:20px;font-weight:bold;">Hi {{ $firstName ?: 'there' }},</p>
                @foreach(preg_split('/\R\s*\R/', $emailBody) as $paragraph)
                    <p style="margin:0 0 20px;font-size:16px;line-height:1.7;color:#45594F;white-space:pre-line;">{{ $paragraph }}</p>
                @endforeach
                <table role="presentation" cellspacing="0" cellpadding="0" style="margin:28px 0;"><tr><td style="background:#C96B55;border-radius:10px;"><a href="{{ $startUrl }}" style="display:inline-block;padding:17px 25px;color:#fff;text-decoration:none;font-size:16px;font-weight:bold;">Post my care request &rarr;</a></td></tr></table>
                <p style="margin:0;font-size:14px;line-height:1.7;color:#68756F;">Prefer a little guidance? Our team will also follow up to help you get started.</p>
            </td></tr>
            <tr><td style="padding:20px 32px;border-top:1px solid #EEE8DE;color:#77817C;font-size:12px;line-height:1.7;">You received this email because you asked LoLo Care about help at home.<br><a href="{{ $unsubscribeUrl }}" style="color:#68756F;">Unsubscribe from these emails</a></td></tr>
        </table>
    </td></tr></table>
</body></html>
