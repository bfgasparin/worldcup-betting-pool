<?php

namespace App\Services\Predictions;

use App\Enums\FeederOutcome;
use App\Models\Fixture;
use App\Models\Group;
use Illuminate\Support\Collection;

/**
 * The shared topology engine that resolves which team fills each knockout slot. It ranks the
 * eight best third-placed teams, resolves the Round-of-32 placeholder labels ("Winner Group A",
 * "Runner-up Group B", "3rd Group A/B/C/D/F") via the official FIFA allocation
 * ({@see ThirdPlaceAllocation}), and cascades winners/losers through the feeder tree
 * (R16 -> QF -> SF -> third place / final).
 *
 * The only thing that differs between resolving a user's predicted bracket and the official
 * one is the source of "who advances" from each feeder fixture: the prediction wizard reads the
 * user's pick ({@see BracketResolver}); the official projection reads the fixture's recorded
 * winner ({@see OfficialBracketProjector}). That source is supplied as the `$advancingFor`
 * closure, so this resolver is the single source of truth for the bracket shape.
 */
class KnockoutSlotResolver
{
    public function __construct(private readonly ThirdPlaceAllocation $allocation = new ThirdPlaceAllocation) {}

    /**
     * Resolve every knockout fixture's home/away team ids from the group standings and a source
     * of "who advanced" per feeder fixture.
     *
     * @param  array<string, GroupStandings>  $standings
     * @param  Collection<int, Fixture>  $knockoutFixtures
     * @param  callable(int): ?int  $advancingFor  feeder fixture id => advancing team id (or null)
     * @param  Collection<int, Group>  $groups
     * @param  list<int>|null  $thirdsOrder  human ordering of a tie that straddles the qualifying cut
     * @return array{rankedThirds: list<int>|null, resolved: array<int, array{home: ?int, away: ?int}>}
     */
    public function resolve(array $standings, Collection $knockoutFixtures, callable $advancingFor, Collection $groups, ?array $thirdsOrder = null): array
    {
        $rankedThirds = $this->rankThirds($standings, $groups, $thirdsOrder);
        $thirdsByMatchNumber = $this->assignThirds($standings, $rankedThirds);

        $resolved = [];
        $ordered = $knockoutFixtures
            ->sortBy(fn (Fixture $fixture): int => $fixture->phase->sort_order * 1000 + $fixture->match_number)
            ->values();

        foreach ($ordered as $fixture) {
            $resolved[$fixture->id] = [
                'home' => $this->resolveSlot('home', $fixture, $standings, $thirdsByMatchNumber, $resolved, $advancingFor),
                'away' => $this->resolveSlot('away', $fixture, $standings, $thirdsByMatchNumber, $resolved, $advancingFor),
            ];
        }

        return ['rankedThirds' => $rankedThirds, 'resolved' => $resolved];
    }

    /**
     * Rank the third-placed teams across all groups, returning the top-8 team ids in
     * order. Only available once every group is complete.
     *
     * Teams are ranked by points -> goal difference -> goals for. Where that leaves the 8th/9th
     * qualifying boundary inside a run of teams level on all three, the set of qualifiers is
     * ambiguous: a human must order the straddling run ($thirdsOrder). Without a matching
     * ordering the whole allocation is deferred (null); ties that fall wholly above or below the
     * cut never change which eight groups qualify, so group sort order stably orders them.
     *
     * @param  array<string, GroupStandings>  $standings
     * @param  Collection<int, Group>  $groups
     * @param  list<int>|null  $thirdsOrder
     * @return list<int>|null
     */
    private function rankThirds(array $standings, Collection $groups, ?array $thirdsOrder = null): ?array
    {
        $thirds = $this->sortedThirds($standings, $groups);

        if ($thirds === null) {
            return null;
        }

        $thirds = $this->resolveThirdsCut($thirds, $thirdsOrder);

        if ($thirds === null) {
            return null;
        }

        return array_map(
            fn (array $third): int => $third['standing']->teamId,
            array_slice($thirds, 0, 8),
        );
    }

    /**
     * The third-placed team of every group, sorted by points -> goal difference -> goals for ->
     * group sort order, or null when any group is incomplete or its third is itself an unresolved
     * tie (so the third's identity is unknown).
     *
     * @param  array<string, GroupStandings>  $standings
     * @param  Collection<int, Group>  $groups
     * @return list<array{standing: TeamStanding, sortOrder: int}>|null
     */
    private function sortedThirds(array $standings, Collection $groups): ?array
    {
        foreach ($standings as $groupStandings) {
            if (! $groupStandings->isComplete()) {
                return null;
            }
        }

        $thirds = [];
        foreach ($groups as $group) {
            $third = $standings[$group->name]->thirdStanding();

            if ($third === null) {
                return null;
            }

            $thirds[] = ['standing' => $third, 'sortOrder' => $group->sort_order];
        }

        usort($thirds, function (array $a, array $b): int {
            /** @var TeamStanding $standingA */
            $standingA = $a['standing'];
            /** @var TeamStanding $standingB */
            $standingB = $b['standing'];

            return ($standingB->points() <=> $standingA->points())
                ?: ($standingB->goalDifference() <=> $standingA->goalDifference())
                ?: ($standingB->goalsFor <=> $standingA->goalsFor)
                ?: ($a['sortOrder'] <=> $b['sortOrder']);
        });

        return $thirds;
    }

    /**
     * The third-placed team ids whose tie straddles the 8th/9th qualifying cut and therefore need
     * a manual ordering, in their default (group sort order) arrangement; an empty list when the
     * cut is clean or the thirds cannot be ranked yet. Reports the tie itself, regardless of
     * whether an ordering has been supplied — used by the tie inspector and the default-order helper.
     *
     * @param  array<string, GroupStandings>  $standings
     * @param  Collection<int, Group>  $groups
     * @param  list<int>|null  $thirdsOrder  apply this ordering to the run when it matches the set
     * @return list<int>
     */
    public function straddlingThirds(array $standings, Collection $groups, ?array $thirdsOrder = null): array
    {
        $thirds = $this->sortedThirds($standings, $groups);

        if ($thirds === null || count($thirds) <= 8 || ! $this->thirdsEqual($thirds[7], $thirds[8])) {
            return [];
        }

        $start = 7;
        while ($start > 0 && $this->thirdsEqual($thirds[$start - 1], $thirds[7])) {
            $start--;
        }

        $end = 8;
        while ($end + 1 < count($thirds) && $this->thirdsEqual($thirds[$end + 1], $thirds[8])) {
            $end++;
        }

        $run = array_slice($thirds, $start, $end - $start + 1);
        usort($run, fn (array $a, array $b): int => $a['sortOrder'] <=> $b['sortOrder']);
        $runIds = array_map(fn (array $third): int => $third['standing']->teamId, $run);

        if ($thirdsOrder !== null) {
            $chosen = array_values(array_filter($thirdsOrder, fn (int $id): bool => in_array($id, $runIds, true)));

            if (count($chosen) === count($runIds) && count(array_unique($chosen)) === count($runIds)) {
                return $chosen;
            }
        }

        return $runIds;
    }

    /**
     * Decide the order of any tie that straddles the 8th/9th qualifying boundary. Returns the
     * thirds unchanged when the cut is clean, the run re-ordered when a matching manual ordering
     * is supplied, or null when the straddling run has no matching ordering (defer the cut).
     *
     * @param  list<array{standing: TeamStanding, sortOrder: int}>  $thirds  sorted by the criteria
     * @param  list<int>|null  $thirdsOrder
     * @return list<array{standing: TeamStanding, sortOrder: int}>|null
     */
    private function resolveThirdsCut(array $thirds, ?array $thirdsOrder): ?array
    {
        if (count($thirds) <= 8) {
            return $thirds;
        }

        // The boundary is between index 7 (8th) and index 8 (9th).
        if (! $this->thirdsEqual($thirds[7], $thirds[8])) {
            return $thirds;
        }

        $start = 7;
        while ($start > 0 && $this->thirdsEqual($thirds[$start - 1], $thirds[7])) {
            $start--;
        }

        $end = 8;
        while ($end + 1 < count($thirds) && $this->thirdsEqual($thirds[$end + 1], $thirds[8])) {
            $end++;
        }

        $run = array_slice($thirds, $start, $end - $start + 1);
        $runIds = array_map(fn (array $third): int => $third['standing']->teamId, $run);

        $chosen = $thirdsOrder === null
            ? []
            : array_values(array_filter($thirdsOrder, fn (int $id): bool => in_array($id, $runIds, true)));

        if (count($chosen) !== count($runIds) || count(array_unique($chosen)) !== count($runIds)) {
            return null;
        }

        $byId = [];
        foreach ($run as $third) {
            $byId[$third['standing']->teamId] = $third;
        }

        $orderedRun = array_map(fn (int $id): array => $byId[$id], $chosen);

        return array_merge(
            array_slice($thirds, 0, $start),
            $orderedRun,
            array_slice($thirds, $end + 1),
        );
    }

    /**
     * @param  array{standing: TeamStanding, sortOrder: int}  $a
     * @param  array{standing: TeamStanding, sortOrder: int}  $b
     */
    private function thirdsEqual(array $a, array $b): bool
    {
        /** @var TeamStanding $standingA */
        $standingA = $a['standing'];
        /** @var TeamStanding $standingB */
        $standingB = $b['standing'];

        return $standingA->points() === $standingB->points()
            && $standingA->goalDifference() === $standingB->goalDifference()
            && $standingA->goalsFor === $standingB->goalsFor;
    }

    /**
     * Resolve which third-placed team fills each third-place Round-of-32 slot, keyed by the
     * slot's match number, via the official allocation table. Null until every group is
     * complete (the whole eight-group combination must be known to look the slotting up).
     *
     * @param  array<string, GroupStandings>  $standings
     * @param  list<int>|null  $rankedThirds  the eight qualifying third team ids
     * @return array<int, int>|null match number => team id
     */
    private function assignThirds(array $standings, ?array $rankedThirds): ?array
    {
        if ($rankedThirds === null) {
            return null;
        }

        $qualifying = array_flip($rankedThirds);
        $thirdTeamByGroup = [];
        $qualifyingGroups = [];

        foreach ($standings as $name => $groupStandings) {
            $third = $groupStandings->thirdStanding();

            if ($third === null) {
                return null;
            }

            $thirdTeamByGroup[$name] = $third->teamId;

            if (isset($qualifying[$third->teamId])) {
                $qualifyingGroups[] = $name;
            }
        }

        $assignment = $this->allocation->assign($qualifyingGroups);

        if ($assignment === null) {
            return null;
        }

        $thirdsByMatchNumber = [];

        foreach ($assignment as $matchNumber => $groupLetter) {
            $thirdsByMatchNumber[$matchNumber] = $thirdTeamByGroup[$groupLetter];
        }

        return $thirdsByMatchNumber;
    }

    /**
     * Resolve one side of a knockout fixture to a team id, or null when it cannot be
     * determined yet.
     *
     * @param  array<string, GroupStandings>  $standings
     * @param  array<int, int>|null  $thirdsByMatchNumber  match number => third-placed team id
     * @param  array<int, array{home: ?int, away: ?int}>  $resolved  already-resolved feeders
     * @param  callable(int): ?int  $advancingFor
     */
    private function resolveSlot(string $side, Fixture $fixture, array $standings, ?array $thirdsByMatchNumber, array $resolved, callable $advancingFor): ?int
    {
        $feederId = $side === 'home' ? $fixture->home_feeder_fixture_id : $fixture->away_feeder_fixture_id;

        if ($feederId === null) {
            $label = $side === 'home' ? $fixture->home_placeholder_label : $fixture->away_placeholder_label;

            return $this->resolveLabel($label, $standings, $thirdsByMatchNumber, $fixture->match_number);
        }

        $feeder = $resolved[$feederId] ?? null;

        if ($feeder === null || $feeder['home'] === null || $feeder['away'] === null) {
            return null;
        }

        $advancing = $advancingFor($feederId);
        $advancing = $advancing === null ? null : (int) $advancing;

        if ($advancing === null || ($advancing !== $feeder['home'] && $advancing !== $feeder['away'])) {
            return null;
        }

        $outcome = $side === 'home' ? $fixture->home_feeder_outcome : $fixture->away_feeder_outcome;

        if ($outcome === FeederOutcome::Winner) {
            return $advancing;
        }

        return $advancing === $feeder['home'] ? $feeder['away'] : $feeder['home'];
    }

    /**
     * Map a Round-of-32 placeholder label to a resolved team id, or null when unknown. The
     * third-place slot ("3rd Group …") is resolved from the fixture's match number via the
     * official allocation; the label itself only documents the eligible groups.
     *
     * @param  array<string, GroupStandings>  $standings
     * @param  array<int, int>|null  $thirdsByMatchNumber  match number => third-placed team id
     */
    private function resolveLabel(?string $label, array $standings, ?array $thirdsByMatchNumber, int $matchNumber): ?int
    {
        if ($label === null) {
            return null;
        }

        if (preg_match('/^Winner Group ([A-L])$/', $label, $matches)) {
            return ($standings[$matches[1]] ?? null)?->winner();
        }

        if (preg_match('/^Runner-up Group ([A-L])$/', $label, $matches)) {
            return ($standings[$matches[1]] ?? null)?->runnerUp();
        }

        if (str_starts_with($label, '3rd Group ')) {
            return $thirdsByMatchNumber[$matchNumber] ?? null;
        }

        return null;
    }
}
