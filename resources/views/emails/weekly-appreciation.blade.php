<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $siteName }} - Thank you</title>
</head>
<body style="margin:0;padding:0;background:#f4f7f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f4f7f6;margin:0;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:620px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(15,23,42,.08);">
                <tr>
                    <td style="height:6px;background:{{ $primaryColor }};font-size:0;line-height:0;">&nbsp;</td>
                </tr>

                <tr>
                    <td align="center" style="padding:32px 32px 22px;">
                        @if(!empty($logoUrl))
                            <img src="{{ $logoUrl }}" alt="{{ $siteName }}" style="display:block;max-width:120px;max-height:64px;margin:0 auto 14px;border:0;">
                        @endif
                        <div style="font-size:28px;line-height:1.25;font-weight:700;color:#111827;">{{ $siteName }}</div>
                        <div style="width:52px;height:4px;border-radius:999px;background:{{ $primaryColor }};margin:14px auto 0;"></div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:8px 40px 36px;">
                        <div style="font-size:22px;line-height:1.35;font-weight:700;margin:0 0 18px;">
                            Hi {{ $recipientName }},
                        </div>

                        @foreach($paragraphs as $paragraph)
                            <p style="font-size:17px;line-height:1.7;color:#4b5563;margin:0 0 16px;">{{ $paragraph }}</p>
                        @endforeach

                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin:26px auto 28px;">
                            <tr>
                                <td align="center" bgcolor="{{ $primaryColor }}" style="border-radius:8px;background:{{ $primaryColor }};">
                                    <a href="{{ $dashboardUrl }}" style="display:inline-block;padding:14px 28px;font-size:16px;line-height:1.2;font-weight:700;color:#ffffff;text-decoration:none;border-radius:8px;background:{{ $primaryColor }};">
                                        Open My Digital Diary
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <div style="font-size:15px;line-height:1.7;color:#6b7280;border-top:1px solid #e5e7eb;padding-top:22px;">
                            Best wishes,<br>
                            <strong style="color:#1f2937;">The {{ $siteName }} team</strong>
                        </div>
                    </td>
                </tr>
            </table>

            <div style="max-width:620px;font-size:12px;line-height:1.6;color:#9ca3af;text-align:center;padding:18px 20px 0;">
                You're getting this weekly note because you have a {{ $siteName }} account.<br>
                <a href="{{ $unsubscribeUrl }}" style="color:#6b7280;text-decoration:underline;">Unsubscribe from weekly appreciation emails</a>
            </div>
        </td>
    </tr>
</table>
</body>
</html>
