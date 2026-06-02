Brothers Betting Pool — Milestone

You're top of the table, {{ $userName }}!

1st on the {{ $leaderboardLabel }} leaderboard in {{ $tournamentName }} — {{ $points }} pts, out of {{ $totalEntries }} {{ \Illuminate\Support\Str::plural('player', $totalEntries) }}.
@if ($runnerUpName && $leadOverRunnerUp > 0)

You're {{ $leadOverRunnerUp }} pts clear of {{ $runnerUpName }}. Enjoy the view — keep predicting to stay there.
@else

You've taken the lead. Enjoy the view — keep predicting to stay there.
@endif

See the table: {{ $url }}

The next round of results could shake things up — there's a long way to the final.

— Brothers Betting Pool
