@extends('emails.layout')

@section('title', $roundName . ' predictions are open')

@section('preheader', "The {$roundName} match-ups are set in {$source}'s {$poolName} — get your picks in" . ($deadlineLabel ? " before {$deadlineLabel} {$deadlineZone}." : '.'))

@section('headerTag', 'New round')

@section('accentBarSolid', $accentSolid)
@section('accentBarGradient', $accentGradient)

@section('content')
    {{-- Hero --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="ffa-pad" align="center" style="padding:36px 32px 4px;text-align:center;">
                <p style="margin:0;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:12px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:{{ $accentInk }};">Pool by {{ $source }} &middot; New predictions</p>
                <h1 class="ffa-h1" style="margin:12px 0 0;font-family:'Fredoka','Trebuchet MS',Verdana,sans-serif;font-size:30px;font-weight:600;line-height:1.1;letter-spacing:-0.02em;color:#0D2E23;">{{ $roundName }} is open</h1>
            </td>
        </tr>
    </table>

    {{-- Deadline strip --}}
    @if ($deadlineLabel)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td class="ffa-pad" style="padding:22px 32px 6px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F1F4F0;border:1px solid #E7ECE8;border-radius:14px;">
                        <tr>
                            <td valign="middle" style="padding:16px 18px;width:54px;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td align="center" valign="middle" width="44" height="44" bgcolor="{{ $accentSolid }}" style="width:44px;height:44px;background-color:{{ $accentSolid }};background-image:{{ $accentGradient }};border-radius:12px;font-family:'Fredoka','Trebuchet MS',Verdana,sans-serif;font-size:20px;font-weight:600;color:#ffffff;text-align:center;">&#9201;</td>
                                    </tr>
                                </table>
                            </td>
                            <td valign="middle" style="padding:16px 6px;">
                                <div style="font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#7A847E;">Predictions close</div>
                                <div style="margin-top:2px;font-family:'Fredoka','Trebuchet MS',Verdana,sans-serif;font-size:16px;font-weight:600;color:#0D2E23;">{{ $deadlineLabel }} {{ $deadlineZone }}</div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    @endif

    {{-- Body copy --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="ffa-pad-narrow" align="center" style="padding:14px 48px 0;text-align:center;">
                <p style="margin:0;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#5E6B64;">The {{ $roundName }} match-ups are locked in. Head in and call every tie before the window closes{{ $deadlineLabel ? '' : ' — kick-off comes round fast' }}.</p>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:24px 32px 6px;text-align:center;">
                @include('emails.partials.button', ['url' => $url, 'label' => 'Make your picks →', 'variant' => 'pitch'])
            </td>
        </tr>
        <tr>
            <td class="ffa-pad-narrow" align="center" style="padding:14px 48px 34px;text-align:center;">
                <p style="margin:0;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:12.5px;line-height:1.55;color:#7A847E;">Miss the deadline and the round locks with no pick — don't leave points on the table.</p>
            </td>
        </tr>
    </table>
@endsection

@section('footerNote')
    You're getting this because you're playing <b style="color:#5E6B64;font-weight:700;">{{ $source }}'s {{ $poolName }}</b> on Brothers Bets. We only email when a new round opens for you to predict.
@endsection
