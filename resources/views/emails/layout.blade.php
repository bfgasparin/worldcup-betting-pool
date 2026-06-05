<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>@yield('title', 'Brothers Bets')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #FAFBF8; }
        table { border-collapse: collapse; }
        a { text-decoration: none; }
        img { border: 0; line-height: 100%; outline: none; -ms-interpolation-mode: bicubic; }
        .ffa-anchor { color: #0A6B49; text-decoration: underline; }

        @media only screen and (max-width: 620px) {
            .ffa-container { width: 100% !important; }
            .ffa-pad { padding-left: 22px !important; padding-right: 22px !important; }
            .ffa-pad-narrow { padding-left: 22px !important; padding-right: 22px !important; }
            .ffa-code { font-size: 34px !important; letter-spacing: 6px !important; padding-left: 18px !important; padding-right: 8px !important; }
            .ffa-h1 { font-size: 26px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#FAFBF8;-webkit-font-smoothing:antialiased;">

    {{-- Hidden inbox preview text --}}
    <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#FAFBF8;opacity:0;">@yield('preheader')&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FAFBF8;">
        <tr>
            <td align="center" style="padding:32px 16px;">

                <table role="presentation" class="ffa-container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;background-color:#ffffff;border:1px solid #E7ECE8;border-radius:20px;overflow:hidden;">

                    {{-- ============ HEADER BAND ============ --}}
                    <tr>
                        <td class="ffa-pad" style="background-color:#0B2419;background-image:linear-gradient(160deg,#1B4A38 0%,#0B2419 80%);padding:26px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="left" valign="middle">
                                        @include('emails.partials.wordmark', ['gColor' => '#ffffff', 'amColor' => '#FFC23C', 'size' => '22px'])
                                    </td>
                                    <td align="right" valign="middle">
                                        <span style="display:inline-block;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:11px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:#CDE6DA;background-color:#16382A;border:1px solid #2C5443;padding:6px 12px;border-radius:999px;">@yield('headerTag')</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ============ BODY ============ --}}
                    <tr>
                        <td style="background-color:#ffffff;">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- ============ FOOTER ============ --}}
                    <tr>
                        <td class="ffa-pad" style="background-color:#F1F4F0;padding:24px 32px;text-align:center;border-top:1px solid #E7ECE8;">
                            <div style="margin-bottom:8px;">
                                @include('emails.partials.wordmark', ['gColor' => '#0A6B49', 'amColor' => '#FF9F1C', 'size' => '16px'])
                            </div>
                            <p style="margin:0 auto;max-width:46ch;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:11.5px;line-height:1.6;color:#7A847E;">@yield('footerNote')</p>
                        </td>
                    </tr>

                    {{-- ============ BRAND BAR ============ --}}
                    {{-- A pool email may tint this to its accent via @section('accentBar*'); defaults to pitch. --}}
                    <tr>
                        <td height="6" style="height:6px;line-height:6px;font-size:0;background-color:@yield('accentBarSolid', '#0FA968');background-image:@yield('accentBarGradient', 'linear-gradient(135deg,#16C07A 0%,#0A6B49 100%)');">&nbsp;</td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
