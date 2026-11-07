@props(['title', 'preheader' => null])
{{-- Shared branded email shell. Table-based + inline styles for email-client compatibility. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f1f1;font-family:Arial,Helvetica,sans-serif;color:#1d1515;">
    @if ($preheader)
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $preheader }}</div>
    @endif
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f1f1;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="background:#0b0808;padding:22px 32px;">
                            <span style="font-size:20px;font-weight:bold;letter-spacing:4px;color:#ffffff;">ZAIRON Systems</span>
                            <span style="font-size:12px;color:#f2b8b8;padding-left:10px;">Ideas, To Reality.</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="height:4px;background:#800000;background-image:linear-gradient(90deg,#b3142c,#5c0000);"></td>
                    </tr>
                    <tr>
                        <td style="padding:32px;font-size:15px;line-height:1.6;">
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px;background:#faf7f7;font-size:12px;line-height:1.5;color:#8f8282;">
                            © {{ date('Y') }} ZAIRON Systems · {{ \App\Support\Content::get('site')['email'] }}<br>
                            You are receiving this email because of an action on {{ parse_url(config('app.url'), PHP_URL_HOST) }}.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
