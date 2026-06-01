<?php

namespace Tests\Concerns;

use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Tournament;
use App\Services\Predictions\OfficialBracketProjector;

/**
 * Test helpers for recording official (already-played) results against the seeded World Cup
 * structure — the real-results mirror of {@see InteractsWithPredictions}.
 */
trait InteractsWithOfficialResults
{
    /**
     * Put a fixture into an "ended" state (live, past full time) so it accepts an official score.
     */
    protected function markEnded(Fixture $fixture): Fixture
    {
        $fixture->update([
            'status' => FixtureStatus::Live,
            'kicks_off_at' => now()->subMinutes((int) config('scoring.match_duration_minutes') + 1),
        ]);

        return $fixture->refresh();
    }

    /**
     * Record official group-stage scores by applying a position-based rule to every group
     * fixture: rule($homePosition, $awayPosition) => [homeGoals, awayGoals]. The winner is
     * derived from the score (null on a draw).
     *
     * @param  callable(int, int): array{int, int}  $rule
     * @param  list<string>|null  $onlyGroups  restrict to these group names (all when null)
     */
    protected function recordOfficialGroupResults(Tournament $tournament, callable $rule, ?array $onlyGroups = null): void
    {
        $groups = $tournament->groups()->with('teams')->orderBy('sort_order')->get();

        foreach ($groups as $group) {
            if ($onlyGroups !== null && ! in_array($group->name, $onlyGroups, true)) {
                continue;
            }

            $positions = $group->teams->mapWithKeys(
                fn ($team) => [$team->id => $team->pivot->position],
            );

            foreach ($group->fixtures()->get() as $fixture) {
                [$home, $away] = $rule(
                    $positions[$fixture->home_team_id],
                    $positions[$fixture->away_team_id],
                );

                $fixture->update([
                    'home_goals' => $home,
                    'away_goals' => $away,
                    'winner_team_id' => $home === $away
                        ? null
                        : ($home > $away ? $fixture->home_team_id : $fixture->away_team_id),
                    'status' => FixtureStatus::Finished,
                ]);
            }
        }
    }

    /**
     * Drive the official knockout bracket to the final by letting the home team win every
     * resolved fixture 1–0, re-projecting after each round so the next level fills in.
     */
    protected function advanceOfficialHome(Tournament $tournament, OfficialBracketProjector $projector): void
    {
        $projector->project($tournament);

        for ($round = 0; $round < 6; $round++) {
            $progressed = false;

            foreach ($tournament->knockoutFixtures()->get() as $fixture) {
                if ($fixture->home_team_id !== null && $fixture->away_team_id !== null && $fixture->winner_team_id === null) {
                    $fixture->update([
                        'home_goals' => 1,
                        'away_goals' => 0,
                        'winner_team_id' => $fixture->home_team_id,
                        'status' => FixtureStatus::Finished,
                    ]);
                    $progressed = true;
                }
            }

            $projector->project($tournament);

            if (! $progressed) {
                break;
            }
        }
    }
}
