<?php

namespace App\Services\Scoring;

use App\Enums\LeaderboardCategory;
use App\Models\Entry;
use App\Models\Fixture;
use App\Models\Game;
use App\Models\LeaderboardStanding;
use App\Services\Scoring\Strategies\ScoringRules;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes points for an entire game from the official results currently on its tournament's
 * fixtures. It resolves the game's scoring strategy, scores every prediction (writing
 * `points_awarded` per group/knockout prediction) and rolls them up into `Entry.total_points`.
 *
 * The same pass aggregates each entry's per-category metrics ({@see LeaderboardMetrics}) and upserts
 * one {@see LeaderboardStanding} per {@see LeaderboardCategory}, so every leaderboard is
 * recomputed together.
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
        /** @var list<PredictionBreakdown> $breakdowns */
        $breakdowns = [];

        foreach ($entry->groupPredictions as $prediction) {
            $fixture = $fixturesById[$prediction->fixture_id] ?? null;

            if ($fixture === null || ! $this->hasOfficialResult($fixture)) {
                $prediction->update(['points_awarded' => null]);

                continue;
            }

            $breakdown = $rules->evaluateGroup($prediction, $fixture, $config);
            $prediction->update(['points_awarded' => $breakdown->points]);
            $breakdowns[] = $breakdown;
        }

        foreach ($entry->knockoutPredictions as $prediction) {
            $fixture = $fixturesById[$prediction->fixture_id] ?? null;

            if ($fixture === null || ! $this->hasOfficialResult($fixture)) {
                $prediction->update(['points_awarded' => null]);

                continue;
            }

            $breakdown = $rules->evaluateKnockout($prediction, $fixture, $config);
            $prediction->update(['points_awarded' => $breakdown->points]);
            $breakdowns[] = $breakdown;
        }

        // Group and knockout fold into the same tournament-wide metrics; an entry with no scored
        // prediction keeps a null total (so the boards hold their "warming up" state).
        $metrics = $this->aggregate($breakdowns);
        $entry->update(['total_points' => $breakdowns === [] ? null : $metrics->points]);

        $this->upsertStandings($entry, $metrics);
    }

    /**
     * Roll every scored prediction's breakdown into one entry's leaderboard metrics.
     *
     * @param  list<PredictionBreakdown>  $breakdowns
     */
    private function aggregate(array $breakdowns): LeaderboardMetrics
    {
        $points = 0;
        $correctOutcomes = 0;
        $teamGoalsHit = 0;

        foreach ($breakdowns as $breakdown) {
            $points += $breakdown->points;
            $correctOutcomes += $breakdown->isCorrectOutcome ? 1 : 0;
            $teamGoalsHit += $breakdown->teamGoalsHit;
        }

        return new LeaderboardMetrics(
            points: $points,
            correctOutcomes: $correctOutcomes,
            teamGoalsHit: $teamGoalsHit,
        );
    }

    /**
     * Writes one standing row per leaderboard for the entry. Rows are upserted even for unscored
     * entries (value 0), so every board always has a full set of rows and the "points unlock as
     * results land" empty state holds. Ranks are left to {@see RankSnapshotter}.
     */
    private function upsertStandings(Entry $entry, LeaderboardMetrics $metrics): void
    {
        foreach (LeaderboardCategory::cases() as $category) {
            $entry->standings()->updateOrCreate(
                ['category' => $category],
                [
                    'value' => $category->valueFor($metrics),
                    'tiebreaker' => $category->tiebreakerFor($metrics),
                ],
            );
        }
    }

    private function hasOfficialResult(Fixture $fixture): bool
    {
        return $fixture->home_goals !== null && $fixture->away_goals !== null;
    }
}
