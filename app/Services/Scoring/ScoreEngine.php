<?php

namespace App\Services\Scoring;

use App\Models\Entry;
use App\Models\Fixture;
use App\Models\Game;
use App\Services\Scoring\Strategies\ScoringRules;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes points for an entire game from the official results currently on its tournament's
 * fixtures. It resolves the game's scoring strategy, scores every prediction (writing
 * `points_awarded` per group/knockout prediction) and rolls them up into `Entry.total_points`.
 *
 * It fully overwrites prior values rather than incrementing, so it is safe to run after every
 * approved batch and after corrections — a prediction whose fixture has no official result yet
 * is reset to null (unscored), and `total_points` stays null until the entry has at least one
 * scored prediction (so the leaderboard keeps its "points unlock as results land" state).
 */
class ScoreEngine
{
    public function __construct(private readonly ScoringRulesFactory $rulesFactory = new ScoringRulesFactory) {}

    public function recompute(Game $game): void
    {
        $config = ScoringConfig::fromGame($game);
        $rules = $this->rulesFactory->make($game->scoring_strategy);

        // Structure and official results live on the shared tournament; entries on the game.
        $tournament = $game->tournament;
        $tournament->load([
            'groups.fixtures',
            'knockoutFixtures.phase',
        ]);
        $game->load([
            'entries.groupPredictions',
            'entries.knockoutPredictions',
        ]);

        $fixturesById = [];
        foreach ($tournament->groups as $group) {
            foreach ($group->fixtures as $fixture) {
                $fixturesById[$fixture->id] = $fixture;
            }
        }
        foreach ($tournament->knockoutFixtures as $fixture) {
            $fixturesById[$fixture->id] = $fixture;
        }

        DB::transaction(function () use ($game, $fixturesById, $rules, $config): void {
            foreach ($game->entries as $entry) {
                $this->scoreEntry($entry, $fixturesById, $rules, $config);
            }
        });
    }

    /**
     * @param  array<int, Fixture>  $fixturesById
     */
    private function scoreEntry(Entry $entry, array $fixturesById, ScoringRules $rules, ScoringConfig $config): void
    {
        $total = 0;
        $scoredAny = false;

        foreach ($entry->groupPredictions as $prediction) {
            $fixture = $fixturesById[$prediction->fixture_id] ?? null;
            $points = $fixture !== null && $this->hasOfficialResult($fixture)
                ? $rules->scoreGroup($prediction, $fixture, $config)
                : null;

            $prediction->update(['points_awarded' => $points]);

            if ($points !== null) {
                $total += $points;
                $scoredAny = true;
            }
        }

        foreach ($entry->knockoutPredictions as $prediction) {
            $fixture = $fixturesById[$prediction->fixture_id] ?? null;
            $points = $fixture !== null && $this->hasOfficialResult($fixture)
                ? $rules->scoreKnockout($prediction, $fixture, $config)
                : null;

            $prediction->update(['points_awarded' => $points]);

            if ($points !== null) {
                $total += $points;
                $scoredAny = true;
            }
        }

        $entry->update(['total_points' => $scoredAny ? $total : null]);
    }

    private function hasOfficialResult(Fixture $fixture): bool
    {
        return $fixture->home_goals !== null && $fixture->away_goals !== null;
    }
}
