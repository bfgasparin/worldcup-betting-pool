<?php

namespace App\Services\Predictions;

use App\Enums\FeederOutcome;
use App\Models\Entry;
use App\Models\Fixture;
use App\Models\Group;
use App\Models\KnockoutPrediction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The authoritative engine that turns a user's predicted group scores into resolved
 * knockout teams.
 *
 * It computes group standings, ranks the eight best third-placed teams, resolves the
 * Round-of-32 placeholder labels ("Winner Group A", "Runner-up Group B", "3rd Group A/B/C/D/F")
 * and cascades winners/losers through the feeder tree (R16 -> QF -> SF -> third place /
 * final) based on the user's "who advances" picks.
 *
 * Third-placed teams are slotted via the official FIFA allocation ({@see ThirdPlaceAllocation}):
 * the eight qualifying thirds map to fixed Round-of-32 slots so a third never meets the winner
 * of its own group. Determinism note: where FIFA would break unresolvable ties by a drawing of
 * lots, this engine instead uses group seed position and group sort order as the final
 * tie-breakers. Scoring is out of scope here.
 */
class BracketResolver
{
    public function __construct(private readonly ThirdPlaceAllocation $allocation = new ThirdPlaceAllocation) {}

    /**
     * Resolve the full bracket for an entry from its currently saved predictions.
     */
    public function resolve(Entry $entry): ResolvedBracket
    {
        // The tournament structure is immutable, so loadMissing is fine; predictions change
        // between calls (and may be stale on a reused model), so always reload them fresh.
        $entry->loadMissing([
            'tournament.groups.teams',
            'tournament.groups.fixtures',
            'tournament.knockoutFixtures.phase',
        ]);
        $entry->load(['groupPredictions', 'knockoutPredictions']);

        $tournament = $entry->tournament;
        $groupPredictions = $entry->groupPredictions->keyBy('fixture_id');
        $knockoutPredictions = $entry->knockoutPredictions->keyBy('fixture_id');

        $standings = [];
        foreach ($tournament->groups as $group) {
            $predictionsForGroup = [];
            foreach ($group->fixtures as $fixture) {
                if ($groupPredictions->has($fixture->id)) {
                    $predictionsForGroup[$fixture->id] = $groupPredictions->get($fixture->id);
                }
            }

            $standings[$group->name] = new GroupStandings($group, $predictionsForGroup);
        }

        $rankedThirds = $this->rankThirds($standings, $tournament->groups);
        $thirdsByMatchNumber = $this->assignThirds($standings, $rankedThirds);

        $resolved = [];
        $ordered = $tournament->knockoutFixtures
            ->sortBy(fn (Fixture $fixture): int => $fixture->phase->sort_order * 1000 + $fixture->match_number)
            ->values();

        foreach ($ordered as $fixture) {
            $resolved[$fixture->id] = [
                'home' => $this->resolveSlot('home', $fixture, $standings, $thirdsByMatchNumber, $resolved, $knockoutPredictions),
                'away' => $this->resolveSlot('away', $fixture, $standings, $thirdsByMatchNumber, $resolved, $knockoutPredictions),
            ];
        }

        return new ResolvedBracket($standings, $rankedThirds, $resolved);
    }

    /**
     * Persist the resolved home/away teams onto every knockout prediction row and clear
     * any "who advances" pick (plus its scores) that is no longer one of the two resolved
     * teams. Iterates to a fixed point so an upstream change cascades down the whole tree.
     */
    public function persist(Entry $entry): void
    {
        $entry->loadMissing(['tournament.knockoutFixtures', 'groupPredictions']);

        // The tree is at most five levels deep (R32 -> R16 -> QF -> SF -> final), so a
        // handful of passes is always enough to reach a fixed point. The +1 is a guard.
        for ($pass = 0; $pass < 6; $pass++) {
            $entry->load('knockoutPredictions');

            $resolved = $this->resolve($entry);
            $existing = $entry->knockoutPredictions->keyBy('fixture_id');
            $changed = false;

            DB::transaction(function () use ($entry, $resolved, $existing, &$changed): void {
                foreach ($entry->tournament->knockoutFixtures as $fixture) {
                    $slot = $resolved->fixture($fixture->id);
                    $prediction = $existing->get($fixture->id);

                    $homeId = $slot['home'];
                    $awayId = $slot['away'];
                    $advancing = $prediction?->advancing_team_id;
                    $homeGoals = $prediction?->home_goals;
                    $awayGoals = $prediction?->away_goals;

                    $bothResolved = $homeId !== null && $awayId !== null;
                    $stale = $advancing !== null
                        && (! $bothResolved || ((int) $advancing !== $homeId && (int) $advancing !== $awayId));

                    if ($stale) {
                        $advancing = null;
                        $homeGoals = null;
                        $awayGoals = null;
                    }

                    $attributes = [
                        'predicted_home_team_id' => $homeId,
                        'predicted_away_team_id' => $awayId,
                        'advancing_team_id' => $advancing,
                        'home_goals' => $homeGoals,
                        'away_goals' => $awayGoals,
                    ];

                    if ($prediction === null || $this->differs($prediction, $attributes)) {
                        $changed = true;
                    }

                    KnockoutPrediction::updateOrCreate(
                        ['entry_id' => $entry->id, 'fixture_id' => $fixture->id],
                        $attributes,
                    );
                }
            });

            if (! $changed) {
                break;
            }
        }
    }

    /**
     * Rank the third-placed teams across all groups, returning the top-8 team ids in
     * order. Only available once every group is fully predicted.
     *
     * @param  array<string, GroupStandings>  $standings
     * @param  Collection<int, Group>  $groups
     * @return list<int>|null
     */
    private function rankThirds(array $standings, $groups): ?array
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

        return array_map(
            fn (array $third): int => $third['standing']->teamId,
            array_slice($thirds, 0, 8),
        );
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
     * determined yet from the current predictions.
     *
     * @param  array<string, GroupStandings>  $standings
     * @param  array<int, int>|null  $thirdsByMatchNumber  match number => third-placed team id
     * @param  array<int, array{home: ?int, away: ?int}>  $resolved  already-resolved feeders
     * @param  Collection<int, KnockoutPrediction>  $knockoutPredictions
     */
    private function resolveSlot(string $side, Fixture $fixture, array $standings, ?array $thirdsByMatchNumber, array $resolved, $knockoutPredictions): ?int
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

        $advancing = $knockoutPredictions->get($feederId)?->advancing_team_id;
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

    /**
     * @param  array<string, ?int>  $attributes
     */
    private function differs(KnockoutPrediction $prediction, array $attributes): bool
    {
        foreach ($attributes as $key => $value) {
            $current = $prediction->{$key};
            $current = $current === null ? null : (int) $current;
            $value = $value === null ? null : (int) $value;

            if ($current !== $value) {
                return true;
            }
        }

        return false;
    }
}
