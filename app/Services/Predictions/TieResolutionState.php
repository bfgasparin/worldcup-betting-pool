<?php

namespace App\Services\Predictions;

use App\Enums\ProposalStatus;
use App\Models\Entry;
use App\Models\Fixture;
use App\Models\Group;
use App\Models\GroupPrediction;
use App\Models\ScoreBatch;
use App\Models\Tournament;
use Illuminate\Support\Collection;

/**
 * Computes which ties are currently unresolved — for a player's predicted bracket, or a
 * tournament's official results (optionally projected forward with a pending score batch). The
 * single source of truth shared by the prediction wizard, the score-review screen, the approval
 * gate, and the ordering endpoints' validation, so they all agree on what needs human ordering.
 */
class TieResolutionState
{
    public function __construct(
        private readonly BracketResolver $bracketResolver = new BracketResolver,
        private readonly KnockoutSlotResolver $slotResolver = new KnockoutSlotResolver,
    ) {}

    /**
     * The unresolved ties in a player's predicted standings (the upfront self-derived bracket).
     */
    public function forEntry(Entry $entry): TieState
    {
        $entry->loadMissing([
            'pool.tournament.groups.teams',
            'pool.tournament.groups.fixtures',
            'pool.tournament.knockoutFixtures.phase',
        ]);

        $resolved = $this->bracketResolver->resolve($entry);
        $ordering = ManualTieOrdering::fromEntry($entry);

        return $this->summarise($resolved->standings, $entry->pool->tournament->groups, $resolved->rankedThirds, $ordering->thirds);
    }

    /**
     * The unresolved ties in a tournament's official results. When $batch is given, the results
     * are projected forward with that batch's (non-rejected) proposals — the post-approval state.
     */
    public function forTournament(Tournament $tournament, ?ScoreBatch $batch = null): TieState
    {
        $tournament->loadMissing([
            'groups.teams',
            'groups.fixtures',
            'knockoutFixtures.phase',
        ]);

        $ordering = ManualTieOrdering::fromTournament($tournament);
        $overrides = $this->proposalScores($batch);

        $standings = [];
        foreach ($tournament->groups as $group) {
            $standings[$group->name] = new GroupStandings($group, $this->projectedResults($group, $overrides), $ordering->forGroup($group->name));
        }

        $rankedThirds = $this->slotResolver->resolve(
            $standings,
            $tournament->knockoutFixtures,
            fn (int $feederId): ?int => null,
            $tournament->groups,
            $ordering->thirds,
        )['rankedThirds'];

        return $this->summarise($standings, $tournament->groups, $rankedThirds, $ordering->thirds);
    }

    /**
     * @param  array<string, GroupStandings>  $standings
     * @param  Collection<int, Group>  $groups
     * @param  list<int>|null  $rankedThirds
     * @param  list<int>|null  $thirdsOrder
     */
    private function summarise(array $standings, Collection $groups, ?array $rankedThirds, ?array $thirdsOrder): TieState
    {
        $groupTies = [];
        $groupsResolved = true;

        foreach ($groups as $group) {
            $groupStandings = $standings[$group->name];

            if (! $groupStandings->isComplete()) {
                continue;
            }

            $tied = $groupStandings->tiedClusters();

            if ($tied !== []) {
                $groupTies[$group->name] = $tied;
            }

            if ($groupStandings->hasUnresolvedTies()) {
                $groupsResolved = false;
            }
        }

        $straddling = $this->slotResolver->straddlingThirds($standings, $groups, $thirdsOrder);
        $thirdsResolved = $straddling === [] || $rankedThirds !== null;

        return new TieState($standings, $groupTies, $straddling, $thirdsResolved, $groupsResolved);
    }

    /**
     * The official scores for a group's fixtures, overlaid with a pending batch's proposals.
     *
     * @param  array<int, array{int, int}>  $overrides  fixture id => [home goals, away goals]
     * @return array<int, GroupPrediction>
     */
    private function projectedResults(Group $group, array $overrides): array
    {
        $results = [];

        foreach ($group->fixtures as $fixture) {
            if (isset($overrides[$fixture->id])) {
                [$home, $away] = $overrides[$fixture->id];
            } elseif ($fixture->home_goals !== null && $fixture->away_goals !== null) {
                $home = $fixture->home_goals;
                $away = $fixture->away_goals;
            } else {
                continue;
            }

            $results[$fixture->id] = new GroupPrediction(['home_goals' => $home, 'away_goals' => $away]);
        }

        return $results;
    }

    /**
     * @return array<int, array{int, int}> fixture id => [home goals, away goals]
     */
    private function proposalScores(?ScoreBatch $batch): array
    {
        if ($batch === null) {
            return [];
        }

        $scores = [];
        foreach ($batch->proposals()->where('status', '!=', ProposalStatus::Rejected)->get() as $proposal) {
            if ($proposal->home_goals !== null && $proposal->away_goals !== null) {
                $scores[$proposal->fixture_id] = [$proposal->home_goals, $proposal->away_goals];
            }
        }

        return $scores;
    }
}
