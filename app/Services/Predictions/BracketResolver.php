<?php

namespace App\Services\Predictions;

use App\Models\Entry;
use App\Models\KnockoutPrediction;
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
    public function __construct(private readonly KnockoutSlotResolver $slotResolver = new KnockoutSlotResolver) {}

    /**
     * Resolve the full bracket for an entry from its currently saved predictions.
     */
    public function resolve(Entry $entry): ResolvedBracket
    {
        // The tournament structure is immutable, so loadMissing is fine; predictions change
        // between calls (and may be stale on a reused model), so always reload them fresh.
        $entry->loadMissing([
            'game.tournament.groups.teams',
            'game.tournament.groups.fixtures',
            'game.tournament.knockoutFixtures.phase',
        ]);
        $entry->load(['groupPredictions', 'knockoutPredictions']);

        $tournament = $entry->game->tournament;
        $groupPredictions = $entry->groupPredictions->keyBy('fixture_id');
        $knockoutPredictions = $entry->knockoutPredictions->keyBy('fixture_id');
        $ordering = ManualTieOrdering::fromEntry($entry);

        $standings = [];
        foreach ($tournament->groups as $group) {
            $predictionsForGroup = [];
            foreach ($group->fixtures as $fixture) {
                if ($groupPredictions->has($fixture->id)) {
                    $predictionsForGroup[$fixture->id] = $groupPredictions->get($fixture->id);
                }
            }

            $standings[$group->name] = new GroupStandings($group, $predictionsForGroup, $ordering->forGroup($group->name));
        }

        // The advancing team of each feeder is the user's own "who advances" pick.
        $resolved = $this->slotResolver->resolve(
            $standings,
            $tournament->knockoutFixtures,
            fn (int $feederId): ?int => $knockoutPredictions->get($feederId)?->advancing_team_id,
            $tournament->groups,
            $ordering->thirds,
        );

        return new ResolvedBracket($standings, $resolved['rankedThirds'], $resolved['resolved']);
    }

    /**
     * Persist the resolved home/away teams onto every knockout prediction row and clear
     * any "who advances" pick (plus its scores) that is no longer one of the two resolved
     * teams. Iterates to a fixed point so an upstream change cascades down the whole tree.
     */
    public function persist(Entry $entry): void
    {
        $entry->loadMissing(['game.tournament.knockoutFixtures', 'groupPredictions']);

        // The tree is at most five levels deep (R32 -> R16 -> QF -> SF -> final), so a
        // handful of passes is always enough to reach a fixed point. The +1 is a guard.
        for ($pass = 0; $pass < 6; $pass++) {
            $entry->load('knockoutPredictions');

            $resolved = $this->resolve($entry);
            $existing = $entry->knockoutPredictions->keyBy('fixture_id');
            $changed = false;

            DB::transaction(function () use ($entry, $resolved, $existing, &$changed): void {
                foreach ($entry->game->tournament->knockoutFixtures as $fixture) {
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
