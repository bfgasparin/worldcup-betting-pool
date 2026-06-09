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
    $places = trans_choice('place|places', $delta);
@endphp

@section('title', $isUp ? __('You climbed the table') : __('Your standings update'))

@section('preheader', ($isUp ? __('You climbed to :rank in the pool by :source.', ['rank' => $rankOrdinal, 'source' => $source]) : __('You slipped to :rank in the pool by :source.', ['rank' => $rankOrdinal, 'source' => $source])))

@section('headerTag', __('Standings'))

@section('accentBarSolid', $accentSolid)
@section('accentBarGradient', $accentGradient)

@section('content')
    {{-- Hero --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="ffa-pad" align="center" style="padding:36px 32px 4px;text-align:center;">
                <p style="margin:0;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:12px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:{{ $accentInk }};">{{ __('Pool by :source · :label leaderboard', ['source' => $source, 'label' => $leaderboardLabel]) }}</p>
                <h1 class="ffa-h1" style="margin:12px 0 0;font-family:'Fredoka','Trebuchet MS',Verdana,sans-serif;font-size:30px;font-weight:600;line-height:1.1;letter-spacing:-0.02em;color:#0D2E23;">{{ $isUp ? __('You climbed to :rank!', ['rank' => $rankOrdinal]) : __('You slipped to :rank', ['rank' => $rankOrdinal]) }}</h1>
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
                            <div style="font-family:'Fredoka','Trebuchet MS',Verdana,sans-serif;font-size:15px;font-weight:600;color:#0D2E23;">{{ $isUp ? __('You climbed :delta :places', ['delta' => $delta, 'places' => $places]) : __('You slipped :delta :places', ['delta' => $delta, 'places' => $places]) }}</div>
                            <div style="margin-top:2px;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:12.5px;font-weight:600;color:#7A847E;">{{ __('from :rank', ['rank' => $prevOrdinal]) }} &middot; {{ __(':points pts', ['points' => $points]) }}</div>
                        </td>
                        <td valign="middle" align="right" style="padding:16px 18px;">
                            <div style="font-family:'Fredoka','Trebuchet MS',Verdana,sans-serif;font-size:24px;font-weight:600;color:#0D2E23;">{{ $rankOrdinal }}</div>
                            <div style="font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:11px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#7A847E;">{{ __('of :total', ['total' => $totalEntries]) }}</div>
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
                    <p style="margin:0;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#5E6B64;">{!! ($isUp ? __('Keep it going — you\'re just :points behind :name in :rank.', ['points' => '<b style="color:#0D2E23;font-weight:700;">' . __(':points pts', ['points' => $pointsBehind]) . '</b>', 'name' => e($aheadName), 'rank' => e($aheadOrdinal)]) : __('Still in it — you\'re just :points behind :name in :rank.', ['points' => '<b style="color:#0D2E23;font-weight:700;">' . __(':points pts', ['points' => $pointsBehind]) . '</b>', 'name' => e($aheadName), 'rank' => e($aheadOrdinal)])) !!}</p>
                @else
                    <p style="margin:0;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#5E6B64;">{{ $isUp ? __('Keep it going — there\'s plenty of football still to play.') : __('Plenty of football left — there\'s still time to climb back.') }}</p>
                @endif
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:24px 32px 6px;text-align:center;">
                @include('emails.partials.button', ['url' => $url, 'label' => __('See the full table →'), 'variant' => 'pitch'])
            </td>
        </tr>
        <tr>
            <td class="ffa-pad-narrow" align="center" style="padding:14px 48px 34px;text-align:center;">
                <p style="margin:0;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:12.5px;line-height:1.55;color:#7A847E;">{{ __('There\'s plenty of football still to come — keep an eye on the table.') }}</p>
            </td>
        </tr>
    </table>
@endsection

@section('footerNote')
    {!! __('You\'re getting this because you\'re playing :pool on Brothers Bets. We only email about your standings.', ['pool' => '<b style="color:#5E6B64;font-weight:700;">' . __('the pool by :source', ['source' => e($source)]) . '</b>']) !!}
@endsection
