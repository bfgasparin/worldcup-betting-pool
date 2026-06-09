{{ __('Brothers Bets — Milestone') }}

{{ __('You\'re top of the table, :name!', ['name' => $userName]) }}

{!! trans_choice('1st in :pool — :points pts, out of :total player.|1st in :pool — :points pts, out of :total players.', $totalEntries, ['pool' => $poolName, 'points' => $points, 'total' => $totalEntries]) !!}
@if ($runnerUpName && $leadOverRunnerUp > 0)

{{ __('You\'re :points pts clear of :name. Enjoy the view — there\'s plenty of football still to play.', ['points' => $leadOverRunnerUp, 'name' => $runnerUpName]) }}
@else

{{ __('You\'ve taken the lead. Enjoy the view — there\'s plenty of football still to play.') }}
@endif

{{ __('See the table') }}: {{ $url }}

{{ __('The next round of results could shake things up — there\'s a long way to the final.') }}

— Brothers Bets
