<?php

namespace Tests\Concerns;

use App\Models\Entry;
use App\Models\Fixture;
use App\Models\GroupPrediction;
use App\Models\Tournament;
use App\Services\Predictions\BracketResolver;

/**
 * Test helpers for building predicted scores against the seeded World Cup structure.
 */
trait InteractsWithPredictions
{
    /**
     * A position-based result rule that resolves a group to seed order: the better-seeded team
     * (lower group position) wins every match 1–0, so position 1 wins the group, 2 is
     * runner-up, 3 third, 4 last — independent of the actual fixture pairings/order.
     *
     * @return callable(int, int): array{int, int}
     */
    protected function seedOrderScores(): callable
    {
        return fn (int $homePosition, int $awayPosition): array => $homePosition < $awayPosition
            ? [1, 0]
            : [0, 1];
    }

    /**
     * Predict the six fixtures of one group by applying a result rule to each fixture's two
     * group positions (1–4): rule($homePosition, $awayPosition) => [homeGoals, awayGoals].
     *
     * @param  callable(int, int): array{int, int}  $rule
     */
    protected function predictGroup(Entry $entry, Tournament $tournament, string $groupName, callable $rule): void
    {
        $group = $tournament->groups()->where('name', $groupName)->firstOrFail();
        $positions = $group->teams()->get()->mapWithKeys(
            fn ($team) => [$team->id => $team->pivot->position],
        );

        foreach ($group->fixtures()->orderBy('match_number')->get() as $fixture) {
            [$home, $away] = $rule(
                $positions[$fixture->home_team_id],
                $positions[$fixture->away_team_id],
            );

            GroupPrediction::updateOrCreate(
                ['entry_id' => $entry->id, 'fixture_id' => $fixture->id],
                ['home_goals' => $home, 'away_goals' => $away],
            );
        }
    }

    /**
     * Apply the same result rule to every group of the tournament.
     *
     * @param  callable(int, int): array{int, int}  $rule
     */
    protected function predictAllGroups(Entry $entry, Tournament $tournament, callable $rule): void
    {
        foreach ($tournament->groups()->orderBy('sort_order')->get() as $group) {
            $this->predictGroup($entry, $tournament, $group->name, $rule);
        }
    }

    protected function knockoutFixture(Tournament $tournament, string $bracketSlot): Fixture
    {
        return $tournament->fixtures()->where('bracket_slot', $bracketSlot)->firstOrFail();
    }

    /**
     * Pick the home team to advance in every resolved knockout fixture, persisting after
     * each round so the bracket fills in level by level all the way to the final.
     */
    protected function advanceAllHome(Entry $entry, BracketResolver $resolver): void
    {
        $resolver->persist($entry);

        for ($round = 0; $round < 6; $round++) {
            $entry->load('knockoutPredictions');
            $progressed = false;

            foreach ($entry->knockoutPredictions as $prediction) {
                if ($prediction->predicted_home_team_id !== null && $prediction->advancing_team_id === null) {
                    $prediction->update([
                        'advancing_team_id' => $prediction->predicted_home_team_id,
                        'home_goals' => 1,
                        'away_goals' => 0,
                    ]);
                    $progressed = true;
                }
            }

            $resolver->persist($entry);

            if (! $progressed) {
                break;
            }
        }
    }
}
