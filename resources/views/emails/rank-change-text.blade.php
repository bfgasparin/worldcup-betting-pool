@php
    $rankOrdinal = \Illuminate\Support\Number::ordinal($rank);
    $prevOrdinal = \Illuminate\Support\Number::ordinal($previousRank);
    $aheadOrdinal = \Illuminate\Support\Number::ordinal(max($rank - 1, 1));
    $isUp = $direction === 'up';
    $places = trans_choice('place|places', $delta);
@endphp
{!! __(':pool — standings update', ['pool' => $poolName]) !!}

{{ $isUp ? __('You climbed to :rank!', ['rank' => $rankOrdinal]) : __('You slipped to :rank', ['rank' => $rankOrdinal]) }}

{{ $isUp ? __('You climbed :delta :places (from :prev) — now :rank of :total on :points pts.', ['delta' => $delta, 'places' => $places, 'prev' => $prevOrdinal, 'rank' => $rankOrdinal, 'total' => $totalEntries, 'points' => $points]) : __('You slipped :delta :places (from :prev) — now :rank of :total on :points pts.', ['delta' => $delta, 'places' => $places, 'prev' => $prevOrdinal, 'rank' => $rankOrdinal, 'total' => $totalEntries, 'points' => $points]) }}
@if ($aheadName && $pointsBehind !== null && $pointsBehind > 0)

{{ __('You\'re just :points pts behind :name in :rank.', ['points' => $pointsBehind, 'name' => $aheadName, 'rank' => $aheadOrdinal]) }}
@endif

{{ __('See the full table') }}: {{ $url }}

{{ __('There\'s plenty of football still to come — keep an eye on the table.') }}

— Brothers Bets
