@extends('emails.layout')

@section('title', 'Top of the table')

@section('preheader', "You're now #1 in {$source}'s {$gameName} — see how the table looks.")

@section('headerTag', 'Milestone')

@section('accentBarSolid', $accentSolid)
@section('accentBarGradient', $accentGradient)

@section('content')
    {{-- Hero --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="ffa-pad" align="center" style="padding:36px 32px 4px;text-align:center;">
                <p style="margin:0;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:12px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:{{ $accentInk }};">Game by {{ $source }} &middot; {{ $leaderboardLabel }} leaderboard</p>
                <h1 class="ffa-h1" style="margin:12px 0 0;font-family:'Fredoka','Trebuchet MS',Verdana,sans-serif;font-size:30px;font-weight:600;line-height:1.1;letter-spacing:-0.02em;color:#0D2E23;">You're top of the table, {{ $userName }}!</h1>
            </td>
        </tr>
    </table>

    {{-- Gold milestone stat --}}
    <table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" style="margin:22px auto 6px;">
        <tr>
            <td align="center" bgcolor="#FFC23C" style="background-color:#FFC23C;background-image:linear-gradient(135deg,#FFD15C 0%,#FF9F1C 100%);border-radius:16px;padding:20px 40px;text-align:center;">
                <div style="font-family:'Fredoka','Trebuchet MS',Verdana,sans-serif;font-size:54px;font-weight:600;line-height:1;color:#3a2600;">🏆&nbsp;1st</div>
                <div style="margin-top:6px;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#7a4f00;">{{ $points }} pts &middot; of {{ $totalEntries }} {{ \Illuminate\Support\Str::plural('player', $totalEntries) }}</div>
            </td>
        </tr>
    </table>

    {{-- Body copy --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="ffa-pad-narrow" align="center" style="padding:16px 48px 0;text-align:center;">
                @if ($runnerUpName && $leadOverRunnerUp > 0)
                    <p style="margin:0;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#5E6B64;">You're <b style="color:#0D2E23;font-weight:700;">{{ $leadOverRunnerUp }} pts</b> clear of {{ $runnerUpName }}. Enjoy the view — there's plenty of football still to play.</p>
                @else
                    <p style="margin:0;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#5E6B64;">You've taken the lead. Enjoy the view — there's plenty of football still to play.</p>
                @endif
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:24px 32px 6px;text-align:center;">
                @include('emails.partials.button', ['url' => $url, 'label' => 'See the table →', 'variant' => 'gold'])
            </td>
        </tr>
        <tr>
            <td class="ffa-pad-narrow" align="center" style="padding:14px 48px 34px;text-align:center;">
                <p style="margin:0;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:12.5px;line-height:1.55;color:#7A847E;">The next round of results could shake things up — there's a long way to the final.</p>
            </td>
        </tr>
    </table>
@endsection

@section('footerNote')
    You're getting this because you're playing <b style="color:#5E6B64;font-weight:700;">{{ $source }}'s {{ $gameName }}</b> on Brothers Betting Pool. We only email about your standings.
@endsection
