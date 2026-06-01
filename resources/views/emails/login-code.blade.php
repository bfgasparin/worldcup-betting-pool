@extends('emails.layout')

@section('title', 'Your Brothers Betting Pool sign-in code')

@section('preheader', 'Use this code to finish signing in — it expires in ' . $expiresInMinutes . ' minutes.')

@section('headerTag', 'Sign in')

@section('content')
    {{-- Hero --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="ffa-pad" align="center" style="padding:36px 32px 4px;text-align:center;">
                <p style="margin:0;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:12px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#0A6B49;">Secure sign-in</p>
                <h1 class="ffa-h1" style="margin:12px 0 0;font-family:'Fredoka','Trebuchet MS',Verdana,sans-serif;font-size:30px;font-weight:600;line-height:1.1;letter-spacing:-0.02em;color:#0D2E23;">Your login code</h1>
            </td>
        </tr>
    </table>

    {{-- Code panel --}}
    <table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" style="margin:22px auto 6px;">
        <tr>
            <td class="ffa-code" align="center" style="background-color:#EFF8F3;border:1px solid #C9F4DD;border-radius:14px;padding:22px 30px;font-family:'Fredoka','Trebuchet MS',Verdana,sans-serif;font-size:42px;font-weight:600;line-height:1;letter-spacing:10px;text-indent:10px;color:#0A6B49;text-align:center;white-space:nowrap;mso-line-height-rule:exactly;">{{ $code }}</td>
        </tr>
    </table>

    {{-- Body copy --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="ffa-pad-narrow" align="center" style="padding:14px 48px 0;text-align:center;">
                <p style="margin:0;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#5E6B64;">Use the code above to finish signing in to <b style="color:#0D2E23;font-weight:700;">Brothers Betting Pool</b>. It expires in <b style="color:#0D2E23;font-weight:700;">{{ $expiresInMinutes }} minutes</b>.</p>
            </td>
        </tr>
        <tr>
            <td class="ffa-pad-narrow" align="center" style="padding:18px 48px 34px;text-align:center;">
                <p style="margin:0;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:13px;line-height:1.55;color:#7A847E;">If you didn't request this code, you can safely ignore this email — your account stays secure.</p>
            </td>
        </tr>
    </table>
@endsection

@section('footerNote')
    You're receiving this because a sign-in to Brothers Betting Pool was requested for <b style="color:#5E6B64;font-weight:700;">{{ $email }}</b>. We'll never ask you for this code — keep it to yourself.
@endsection
