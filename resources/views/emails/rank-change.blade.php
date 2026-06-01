@extends('emails.layout')

@php
    $rankOrdinal = \Illuminate\Support\Number::ordinal($rank);
    $prevOrdinal = \Illuminate\Support\Number::ordinal($previousRank);
    $aheadOrdinal = \Illuminate\Support\Number::ordinal(max($rank - 1, 1));
    $isUp = $direction === 'up';
    $badgeBg = $isUp ? '#0A6B49' : '#FF9F1C';
    $badgeImg = $isUp
        ? 'linear-gradient(135deg,#16C07A 0%,#0A6B49 100%)'
        : 'linear-gradient(135deg,#FFD15C 0%,#FF9F1C 100%)';
    $badgeColor = $isUp ? '#ffffff' : '#3a2600';
    $arrow = $isUp ? '▲' : '▼';
    $places = \Illuminate\Support\Str::plural('place', $delta);
@endphp

@section('title', $isUp ? 'You climbed the table' : 'Your standings update')

@section('preheader', ($isUp ? "You climbed to {$rankOrdinal}" : "You slipped to {$rankOrdinal}") . " in {$tournamentName}.")

@section('headerTag', 'Standings')

@section('content')
    {{-- Hero --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="ffa-pad" align="center" style="padding:36px 32px 4px;text-align:center;">
                <p style="margin:0;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:12px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#0A6B49;">{{ $tournamentName }} &middot; Leaderboard update</p>
                <h1 class="ffa-h1" style="margin:12px 0 0;font-family:'Fredoka','Trebuchet MS',Verdana,sans-serif;font-size:30px;font-weight:600;line-height:1.1;letter-spacing:-0.02em;color:#0D2E23;">{{ $isUp ? "You climbed to {$rankOrdinal}!" : "You slipped to {$rankOrdinal}" }}</h1>
            </td>
        </tr>
    </table>

    {{-- Move strip --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="ffa-pad" style="padding:22px 32px 6px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F1F4F0;border:1px solid #E7ECE8;border-radius:14px;">
                    <tr>
                        <td valign="middle" style="padding:16px 18px;width:54px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" valign="middle" width="44" height="44" bgcolor="{{ $badgeBg }}" style="width:44px;height:44px;background-color:{{ $badgeBg }};background-image:{{ $badgeImg }};border-radius:12px;font-family:'Fredoka','Trebuchet MS',Verdana,sans-serif;font-size:16px;font-weight:600;color:{{ $badgeColor }};text-align:center;">{{ $arrow }}&nbsp;{{ $delta }}</td>
                                </tr>
                            </table>
                        </td>
                        <td valign="middle" style="padding:16px 6px;">
                            <div style="font-family:'Fredoka','Trebuchet MS',Verdana,sans-serif;font-size:15px;font-weight:600;color:#0D2E23;">You {{ $isUp ? 'climbed' : 'slipped' }} {{ $delta }} {{ $places }}</div>
                            <div style="margin-top:2px;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:12.5px;font-weight:600;color:#7A847E;">from {{ $prevOrdinal }} &middot; {{ $points }} pts</div>
                        </td>
                        <td valign="middle" align="right" style="padding:16px 18px;">
                            <div style="font-family:'Fredoka','Trebuchet MS',Verdana,sans-serif;font-size:24px;font-weight:600;color:#0D2E23;">{{ $rankOrdinal }}</div>
                            <div style="font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:11px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#7A847E;">of {{ $totalEntries }}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Body copy --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="ffa-pad-narrow" align="center" style="padding:14px 48px 0;text-align:center;">
                @if ($aheadName && $pointsBehind !== null && $pointsBehind > 0)
                    <p style="margin:0;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#5E6B64;">{{ $isUp ? 'Keep it going — ' : 'Still in it — ' }}you're just <b style="color:#0D2E23;font-weight:700;">{{ $pointsBehind }} pts</b> behind {{ $aheadName }} in {{ $aheadOrdinal }}.</p>
                @else
                    <p style="margin:0;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#5E6B64;">{{ $isUp ? 'Keep it going — the next matchday could lift you higher.' : 'Plenty of football left — the next matchday is a chance to climb back.' }}</p>
                @endif
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:24px 32px 6px;text-align:center;">
                @include('emails.partials.button', ['url' => $url, 'label' => 'See the full table →', 'variant' => 'pitch'])
            </td>
        </tr>
        <tr>
            <td class="ffa-pad-narrow" align="center" style="padding:14px 48px 34px;text-align:center;">
                <p style="margin:0;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:12.5px;line-height:1.55;color:#7A847E;">Predict the next matchday to keep moving.</p>
            </td>
        </tr>
    </table>
@endsection

@section('footerNote')
    You're getting this because you're playing <b style="color:#5E6B64;font-weight:700;">{{ $tournamentName }}</b> on Brothers Betting Pool. We only email about your standings.
@endsection
