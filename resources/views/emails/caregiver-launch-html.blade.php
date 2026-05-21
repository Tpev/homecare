<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HomeCare Hub is now LoLo Care</title>
</head>
<body style="margin:0;padding:0;background:#f6f1e8;font-family:Arial,Helvetica,sans-serif;color:#17231f;">
    <span style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;">
        {{ $previewText }}
        @for ($i = 0; $i < 90; $i++)
            &#8204;&nbsp;
        @endfor
    </span>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f1e8;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:660px;background:#fffaf2;border:1px solid #dfd3c1;border-radius:18px;overflow:hidden;">
                    <tr>
                        <td style="padding:28px 26px;background:#16433b;color:#fffaf2;">
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 24px 0;">
                                <tr>
                                    <td style="background:#fffaf2;border:1px solid #eadac4;border-radius:12px;padding:12px 14px;">
                                        <img src="{{ $logoUrl }}" width="178" alt="LoLo Care" style="display:block;width:178px;max-width:100%;height:auto;border:0;outline:none;text-decoration:none;">
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 9px 0;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#d8c7aa;">Caregiver launch update</p>
                            <h1 style="margin:0;font-size:30px;line-height:1.18;">HomeCare Hub has a new name</h1>
                            <p style="margin:12px 0 0 0;font-size:15px;line-height:1.6;color:#f7ead5;max-width:540px;">
                                We're launching on {{ $launchDate }}, and we'll share more details early next week.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 26px 12px 26px;">
                            <p style="margin:0 0 16px 0;font-size:16px;line-height:1.65;">
                                Hi {{ $firstName !== '' ? $firstName : 'there' }},
                            </p>

                            <p style="margin:0 0 16px 0;font-size:16px;line-height:1.65;">
                                We're excited to share an important update with you: <strong>HomeCare Hub is now LoLo Care</strong>.
                            </p>

                            <p style="margin:0 0 16px 0;font-size:16px;line-height:1.65;">
                                This is our new brand for helping families find trusted in-home companionship and everyday support from vetted caregivers. The name is changing, but the mission stays the same: make care easier to find, easier to trust, and easier to coordinate.
                            </p>

                            <p style="margin:0 0 16px 0;font-size:16px;line-height:1.65;">
                                We'll officially launch LoLo Care to families on <strong>{{ $launchDate }}</strong>.
                            </p>

                            <p style="margin:0 0 16px 0;font-size:16px;line-height:1.65;">
                                There is nothing you need to do today. We just wanted you to hear about the brand change directly from us first.
                            </p>

                            <p style="margin:0 0 16px 0;font-size:16px;line-height:1.65;">
                                Early next week, we'll get in touch again with more information about the launch, what it means for caregiver accounts, and any next steps before families begin using the platform.
                            </p>

                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 22px 0;">
                                <tr>
                                    <td style="border-radius:10px;background:#16433b;">
                                        <a href="{{ $websiteUrl }}" style="display:inline-block;padding:13px 18px;color:#fffaf2;text-decoration:none;font-weight:bold;font-size:14px;">
                                            Visit LoLo Care
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 16px 0;font-size:16px;line-height:1.65;">
                                Thank you for being part of this next chapter. We're excited to bring LoLo Care to families with caregivers like you at the center of the experience.
                            </p>

                            <p style="margin:0;font-size:16px;line-height:1.65;">
                                Warmly,<br>
                                The LoLo Care Team
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 26px 24px 26px;background:#f1eadf;border-top:1px solid #dfd3c1;">
                            <p style="margin:0 0 8px 0;font-size:13px;line-height:1.6;color:#4c635b;">
                                LoLo Care | <a href="{{ $websiteUrl }}" style="color:#0f6b5f;">{{ $websiteUrl }}</a> | {{ $phone }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
