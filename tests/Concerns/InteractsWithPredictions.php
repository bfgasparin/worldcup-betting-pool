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
     * A score map (by fixture order within a group) that resolves to the seed order:
     * position 1 wins the group, position 2 is runner-up, 3 third, 4 last.
     *
     * @return list<array{int, int}>
     */
    protected function seedOrderScores(): array
    {
        return [[1, 0], [1, 0], [1, 0], [0, 1], [0, 1], [1, 0]];
    }

    /**
     * Predict the six fixtures of one group (ordered by match number).
     *
     * @param  list<array{int, int}>  $scores
     */
    protected function predictGroup(Entry $entry, Tournament $tournament, string $groupName, array $scores): void
    {
        $group = $tournament->groups()->where('name', $groupName)->firstOrFail();
        $fixtures = $group->fixtures()->orderBy('match_number')->get();

        foreach ($scores as $index => [$home, $away]) {
            GroupPrediction::updateOrCreate(
                ['entry_id' => $entry->id, 'fixture_id' => $fixtures[$index]->id],
                ['home_goals' => $home, 'away_goals' => $away],
            );
        }
    }

    /**
     * Apply the same score map to every group of the tournament.
     *
     * @param  list<array{int, int}>  $scores
     */
    protected function predictAllGroups(Entry $entry, Tournament $tournament, array $scores): void
    {
        foreach ($tournament->groups()->orderBy('sort_order')->get() as $group) {
            $this->predictGroup($entry, $tournament, $group->name, $scores);
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
