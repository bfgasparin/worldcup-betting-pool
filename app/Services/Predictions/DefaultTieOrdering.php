<?php

namespace App\Services\Predictions;

use App\Enums\OrderingScope;
use App\Models\Entry;
use App\Models\Fixture;
use App\Models\Group;
use App\Models\GroupPrediction;
use App\Models\Tournament;
use Illuminate\Support\Collection;

/**
 * Records the pre-feature deterministic default orderings — within-group ties by seed position,
 * the thirds cut by points/GD/GF/group sort order — for every tie the engine could not resolve.
 *
 * It exists for non-interactive flows that need a complete bracket without a human in the loop:
 * the tournament simulator, test fixtures, and as the "suggested" starting order an admin can
 * accept. Real predictions/results still surface ties for a person to order by hand; this only
 * reproduces the old automatic behaviour where no person is available to decide. Idempotent.
 */
class DefaultTieOrdering
{
    public function __construct(private readonly KnockoutSlotResolver $slotResolver = new KnockoutSlotResolver) {}

    /**
     * Resolve the ties in the tournament's OFFICIAL results, writing tournament-wide orderings.
     */
    public function applyToTournament(Tournament $tournament): void
    {
        $tournament->loadMissing(['groups.teams', 'groups.fixtures']);

        $standings = [];
        foreach ($tournament->groups as $group) {
            $standings[$group->name] = new GroupStandings($group, $this->officialResults($group));
        }

        $this->apply(
            $standings,
            $tournament->groups,
            function (?int $groupId, OrderingScope $scope, array $tied, array $ordered) use ($tournament): void {
                $tournament->groupOrderings()->updateOrCreate(
                    ['group_id' => $groupId, 'scope' => $scope],
                    ['tied_team_ids' => $tied, 'ordered_team_ids' => $ordered],
                );
            },
        );
    }

    /**
     * Resolve the ties in a player's predicted standings, writing per-entry orderings.
     */
    public function applyToEntry(Entry $entry): void
    {
        $entry->loadMissing(['game.tournament.groups.teams', 'game.tournament.groups.fixtures']);
        $predictions = $entry->groupPredictions()->get()->keyBy('fixture_id');
        $tournament = $entry->game->tournament;

        $standings = [];
        foreach ($tournament->groups as $group) {
            $forGroup = [];
            foreach ($group->fixtures as $fixture) {
                if ($predictions->has($fixture->id)) {
                    $forGroup[$fixture->id] = $predictions->get($fixture->id);
                }
            }

            $standings[$group->name] = new GroupStandings($group, $forGroup);
        }

        $this->apply(
            $standings,
            $tournament->groups,
            function (?int $groupId, OrderingScope $scope, array $tied, array $ordered) use ($entry): void {
                $entry->groupOrderings()->updateOrCreate(
                    ['group_id' => $groupId, 'scope' => $scope],
                    ['tied_team_ids' => $tied, 'ordered_team_ids' => $ordered],
                );
            },
        );
    }

    /**
     * Compute and write a default ordering for every unresolved tie.
     *
     * @param  array<string, GroupStandings>  $standings
     * @param  Collection<int, Group>  $groups
     * @param  callable(?int, OrderingScope, list<int>, list<int>): void  $writer
     */
    private function apply(array $standings, Collection $groups, callable $writer): void
    {
        foreach ($groups as $group) {
            $groupStandings = $standings[$group->name];

            if (! $groupStandings->isComplete()) {
                continue;
            }

            $ties = $groupStandings->unresolvedTies();

            if ($ties === []) {
                continue;
            }

            // Each cluster is already in seed order; flatten them into one per-group ordering.
            $ordered = array_merge(...$ties);
            $writer($group->id, OrderingScope::WithinGroup, $this->sorted($ordered), $ordered);
        }

        $straddling = $this->slotResolver->straddlingThirds($standings, $groups);

        if ($straddling !== []) {
            $writer(null, OrderingScope::Thirds, $this->sorted($straddling), $straddling);
        }
    }

    /**
     * The official scores for a group's already-played fixtures, shaped as the {@see GroupPrediction}
     * records {@see GroupStandings} consumes.
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

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function sorted(array $ids): array
    {
        sort($ids);

        return $ids;
    }
}
