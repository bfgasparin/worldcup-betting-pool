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
 * Round-of-32 placeholder labels ("Winner Group A", "Runner-up Group B", "3rd Place N")
 * and cascades winners/losers through the feeder tree (R16 -> QF -> SF -> third place /
 * final) based on the user's "who advances" picks.
 *
 * Determinism note: FIFA breaks unresolvable ties (and assigns third-placed teams to
 * specific slots) via lookup tables and a drawing of lots. This engine is intentionally
 * deterministic instead — group seed position and group sort order are the final
 * tie-breakers, and "3rd Place N" maps positionally to the N-th best third per the
 * seeded bracket structure. Scoring is out of scope here.
 */
class BracketResolver
{
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

        $resolved = [];
        $ordered = $tournament->knockoutFixtures
            ->sortBy(fn (Fixture $fixture): int => $fixture->phase->sort_order * 1000 + $fixture->match_number)
            ->values();

        foreach ($ordered as $fixture) {
            $resolved[$fixture->id] = [
                'home' => $this->resolveSlot('home', $fixture, $standings, $rankedThirds, $resolved, $knockoutPredictions),
                'away' => $this->resolveSlot('away', $fixture, $standings, $rankedThirds, $resolved, $knockoutPredictions),
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
     * Resolve one side of a knockout fixture to a team id, or null when it cannot be
     * determined yet from the current predictions.
     *
     * @param  array<string, GroupStandings>  $standings
     * @param  list<int>|null  $rankedThirds
     * @param  array<int, array{home: ?int, away: ?int}>  $resolved  already-resolved feeders
     * @param  Collection<int, KnockoutPrediction>  $knockoutPredictions
     */
    private function resolveSlot(string $side, Fixture $fixture, array $standings, ?array $rankedThirds, array $resolved, $knockoutPredictions): ?int
    {
        $feederId = $side === 'home' ? $fixture->home_feeder_fixture_id : $fixture->away_feeder_fixture_id;

        if ($feederId === null) {
            $label = $side === 'home' ? $fixture->home_placeholder_label : $fixture->away_placeholder_label;

            return $this->resolveLabel($label, $standings, $rankedThirds);
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
     * Map a Round-of-32 placeholder label to a resolved team id, or null when unknown.
     *
     * @param  array<string, GroupStandings>  $standings
     * @param  list<int>|null  $rankedThirds
     */
    private function resolveLabel(?string $label, array $standings, ?array $rankedThirds): ?int
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

        if (preg_match('/^3rd Place ([1-8])$/', $label, $matches)) {
            return $rankedThirds[(int) $matches[1] - 1] ?? null;
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
