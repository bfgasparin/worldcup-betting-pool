<?php

namespace App\Services\Live;

use App\Console\Commands\FetchScores;
use App\Contracts\ScoreProvider;
use App\Enums\FixtureStatus;
use App\Enums\LiveStatus;
use App\Models\Fixture;
use App\Models\Tournament;

/**
 * Advances a tournament's live scoreboard off the clock from a {@see ScoreProvider} feed — the
 * automated replacement for the admin's manual Live Center clicking. Each pass: takes due fixtures
 * live, ticks every live scoreboard to the provider's current scoreline, and closes any that have
 * reached full time. It never writes an official result — the complete final is proposed for review
 * by the score feed ({@see FetchScores}) and only an admin approves it.
 */
class LiveFeed
{
    public function __construct(
        private readonly ScoreProvider $provider,
        private readonly GoLive $goLive = new GoLive,
        private readonly UpdateLiveScore $updateLiveScore = new UpdateLiveScore,
        private readonly CloseLiveScoreboard $closeLiveScoreboard = new CloseLiveScoreboard,
    ) {}

    public function advance(Tournament $tournament): void
    {
        // 1) Go live: every due, scheduled fixture whose participants are known (a knockout with an
        // unprojected slot is skipped, so we never open an empty scoreboard).
        $tournament->fixtures()
            ->where('status', FixtureStatus::Scheduled)
            ->whereNotNull('kicks_off_at')
            ->where('kicks_off_at', '<=', now())
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->get()
            ->each(fn (Fixture $fixture) => $this->goLive->force($fixture));

        // 2) Tick: bring every live scoreboard to the provider's current scoreline (a fixture taken
        // live in step 1 is now Live, so it gets its first tick in the same pass).
        $scores = collect($this->provider->live($tournament))->keyBy('matchNumber');

        $live = $tournament->fixtures()
            ->where('status', FixtureStatus::Live)
            ->whereHas('liveState', fn ($query) => $query->where('status', LiveStatus::Live))
            ->with('liveState')
            ->get();

        foreach ($live as $fixture) {
            $score = $scores->get($fixture->match_number);

            if ($score !== null) {
                $this->updateLiveScore->update($fixture, $score->homeGoals, $score->awayGoals);
            }
        }

        // 3) Close: any live fixture past full time — already ticked to its final above.
        foreach ($live as $fixture) {
            if ($fixture->hasEnded()) {
                $this->closeLiveScoreboard->close($fixture);
            }
        }
    }
}
