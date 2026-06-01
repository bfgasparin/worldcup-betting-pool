@php
    $rankOrdinal = \Illuminate\Support\Number::ordinal($rank);
    $prevOrdinal = \Illuminate\Support\Number::ordinal($previousRank);
    $aheadOrdinal = \Illuminate\Support\Number::ordinal(max($rank - 1, 1));
    $isUp = $direction === 'up';
    $places = \Illuminate\Support\Str::plural('place', $delta);
@endphp
Brothers Betting Pool — Leaderboard update

{{ $isUp ? "You climbed to {$rankOrdinal}!" : "You slipped to {$rankOrdinal}" }}

You {{ $isUp ? 'climbed' : 'slipped' }} {{ $delta }} {{ $places }} (from {{ $prevOrdinal }}) — now {{ $rankOrdinal }} of {{ $totalEntries }} on {{ $points }} pts.
@if ($aheadName && $pointsBehind !== null && $pointsBehind > 0)

You're just {{ $pointsBehind }} pts behind {{ $aheadName }} in {{ $aheadOrdinal }}.
@endif

See the full table: {{ $url }}

Predict the next matchday to keep moving.

— Brothers Betting Pool
