<?php

namespace App\Services\Predictions;

use App\Models\Fixture;
use App\Models\Group;
use App\Models\GroupPrediction;
use App\Models\Tournament;
use Illuminate\Support\Facades\DB;

/**
 * Projects the OFFICIAL knockout bracket onto the fixtures table from the real, already-played
 * results. It reuses the same {@see KnockoutSlotResolver} topology as the prediction engine,
 * but sources "who advances" from each fixture's recorded {@see Fixture::$winner_team_id}
 * instead of a user's pick, and builds group standings from the official fixture scores rather
 * than predicted ones.
 *
 * Running it writes `home_team_id`/`away_team_id` onto every knockout fixture that can be
 * resolved so far, leaving the rest null. It is idempotent: a corrected upstream result simply
 * re-cascades the affected slots on the next run. Round-of-32 group slots fill as each group
 * finishes; the best-third slots only fill once every group is complete (the allocation needs
 * the whole eight-group combination).
 */
class OfficialBracketProjector
{
    public function __construct(private readonly KnockoutSlotResolver $slotResolver = new KnockoutSlotResolver) {}

    /**
     * Resolve and persist the official knockout participants for the tournament.
     */
    public function project(Tournament $tournament): void
    {
        // The tournament structure is immutable, but the mutable result data (group scores,
        // knockout winners) changes between calls as batches are approved — so reload it fresh
        // rather than trusting a possibly-stale relation cache.
        $tournament->loadMissing(['groups.teams']);
        $tournament->load([
            'groups.fixtures',
            'knockoutFixtures.phase',
        ]);

        $ordering = ManualTieOrdering::fromTournament($tournament);

        $standings = [];
        foreach ($tournament->groups as $group) {
            $standings[$group->name] = new GroupStandings($group, $this->officialResults($group), $ordering->forGroup($group->name));
        }

        $winnerByFixtureId = $tournament->knockoutFixtures->keyBy('id');

        $resolved = $this->slotResolver->resolve(
            $standings,
            $tournament->knockoutFixtures,
            fn (int $feederId): ?int => $winnerByFixtureId->get($feederId)?->winner_team_id,
            $tournament->groups,
            $ordering->thirds,
        )['resolved'];

        DB::transaction(function () use ($tournament, $resolved): void {
            foreach ($tournament->knockoutFixtures as $fixture) {
                $slot = $resolved[$fixture->id] ?? ['home' => null, 'away' => null];

                $fixture->update([
                    'home_team_id' => $slot['home'],
                    'away_team_id' => $slot['away'],
                ]);
            }
        });
    }

    /**
     * The official scores for a group's already-played fixtures, shaped as the lightweight
     * {@see GroupPrediction} records {@see GroupStandings} consumes. Unplayed fixtures are
     * skipped, so a group only ranks (and feeds the bracket) once all six are in.
     *
     * @return array<int, GroupPrediction>
     */
    private function officialResults(Group $group): array
    {
        return $group->fixtures
            ->filter(fn (Fixture $fixture): bool => $fixture->home_goals !== null && $fixture->away_goals !== null)
            ->mapWithKeys(fn (Fixture $fixture): array => [$fixture->id => new GroupPrediction([
                'home_goals' => $fixture->home_goals,
                'away_goals' => $fixture->away_goals,
            ])])
            ->all();
    }
}
