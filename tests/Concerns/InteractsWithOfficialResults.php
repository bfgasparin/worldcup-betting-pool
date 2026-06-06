<?php

namespace Tests\Concerns;

use App\Enums\FixtureStatus;
use App\Enums\OrderingScope;
use App\Enums\ProposalStatus;
use App\Models\Fixture;
use App\Models\ScoreBatch;
use App\Models\ScoreProposal;
use App\Models\Tournament;
use App\Services\Predictions\DefaultTieOrdering;
use App\Services\Predictions\OfficialBracketProjector;
use App\Services\Predictions\TieResolutionState;
use App\Services\Scoring\MatchdayCatalog;

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
     * @param  bool  $resolveTies  record a default admin ordering for a straddling thirds tie
     */
    protected function recordOfficialGroupResults(Tournament $tournament, callable $rule, ?array $onlyGroups = null, bool $resolveTies = true): void
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

        if ($resolveTies) {
            // No human to break ties in a fixture: fall back to the deterministic default order.
            (new DefaultTieOrdering)->applyToTournament($tournament);
        }
    }

    /**
     * Record official results for just one matchday's fixtures (a group-stage round) by applying a
     * position-based rule, so a test can settle the tournament round by round.
     *
     * @param  callable(int, int): array{int, int}  $rule
     */
    protected function recordMatchdayResults(Tournament $tournament, string $matchdayKey, callable $rule): void
    {
        $positions = [];
        foreach ($tournament->groups()->with('teams')->get() as $group) {
            foreach ($group->teams as $team) {
                $positions[$team->id] = $team->pivot->position;
            }
        }

        $fixtureIds = collect((new MatchdayCatalog)->forTournament($tournament))
            ->firstWhere('key', $matchdayKey)
            ->fixtureIds;

        foreach (Fixture::whereIn('id', $fixtureIds)->get() as $fixture) {
            [$home, $away] = $rule($positions[$fixture->home_team_id], $positions[$fixture->away_team_id]);

            $fixture->update([
                'home_goals' => $home,
                'away_goals' => $away,
                'winner_team_id' => $home === $away ? null : ($home > $away ? $fixture->home_team_id : $fixture->away_team_id),
                'status' => FixtureStatus::Finished,
            ]);
        }
    }

    /**
     * Propose every group fixture's score into a batch by applying a position-based rule, as if an
     * admin entered them for review: rule($homePosition, $awayPosition) => [homeGoals, awayGoals].
     *
     * @param  callable(int, int): array{int, int}  $rule
     * @param  list<string>|null  $onlyGroups  restrict to these group names (all when null)
     */
    protected function proposeGroupResults(ScoreBatch $batch, callable $rule, ?array $onlyGroups = null): void
    {
        foreach ($batch->tournament->groups()->with('teams')->orderBy('sort_order')->get() as $group) {
            if ($onlyGroups !== null && ! in_array($group->name, $onlyGroups, true)) {
                continue;
            }

            $positions = $group->teams->mapWithKeys(fn ($team) => [$team->id => $team->pivot->position]);

            foreach ($group->fixtures()->get() as $fixture) {
                [$home, $away] = $rule($positions[$fixture->home_team_id], $positions[$fixture->away_team_id]);

                ScoreProposal::updateOrCreate(
                    ['score_batch_id' => $batch->id, 'fixture_id' => $fixture->id],
                    [
                        'home_goals' => $home,
                        'away_goals' => $away,
                        'winner_team_id' => $home === $away ? null : ($home > $away ? $fixture->home_team_id : $fixture->away_team_id),
                        'status' => ProposalStatus::Pending,
                    ],
                );
            }
        }
    }

    /**
     * Record the default admin orderings for whatever ties the projected official results (with the
     * given pending batch) leave unresolved — the test analogue of an admin dragging the tied teams
     * into order on the review screen before approving.
     */
    protected function resolveProjectedTies(Tournament $tournament, ?ScoreBatch $batch = null): void
    {
        $state = (new TieResolutionState)->forTournament($tournament, $batch);

        foreach ($state->groupTies as $groupName => $clusters) {
            $ordered = array_merge(...$clusters);
            $tied = $ordered;
            sort($tied);

            $tournament->groupOrderings()->updateOrCreate(
                ['group_id' => $tournament->groups()->where('name', $groupName)->value('id'), 'scope' => OrderingScope::WithinGroup],
                ['tied_team_ids' => $tied, 'ordered_team_ids' => $ordered],
            );
        }

        if ($state->thirds !== [] && ! $state->thirdsResolved) {
            $ordered = $state->thirds;
            $tied = $ordered;
            sort($tied);

            $tournament->groupOrderings()->updateOrCreate(
                ['group_id' => null, 'scope' => OrderingScope::Thirds],
                ['tied_team_ids' => $tied, 'ordered_team_ids' => $ordered],
            );
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
