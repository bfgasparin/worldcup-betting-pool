<?php

namespace App\Services\Scoring;

use App\Enums\LeaderboardCategory;

/**
 * Per-entry aggregates rolled up from every prediction's breakdown during a recompute, spanning the
 * whole tournament (group + knockout). Each {@see LeaderboardCategory} reads the field it needs via
 * {@see LeaderboardCategory::valueFor()} and {@see LeaderboardCategory::tiebreakerFor()}, so adding a
 * board never touches this struct.
 */
final class LeaderboardMetrics
{
    public function __construct(
        public readonly int $points = 0,
        public readonly int $correctOutcomes = 0,
        public readonly int $teamGoalsHit = 0,
    ) {}

    /**
     * Roll a set of per-prediction breakdowns into one entry's metrics. Shared by the live
     * recompute ({@see ScoreEngine}) and the per-matchday reconstruction
     * ({@see MatchdayLeaderboard}), so both fold breakdowns the same way.
     *
     * @param  iterable<PredictionBreakdown>  $breakdowns
     */
    public static function fromBreakdowns(iterable $breakdowns): self
    {
        $points = 0;
        $correctOutcomes = 0;
        $teamGoalsHit = 0;

        foreach ($breakdowns as $breakdown) {
            $points += $breakdown->points;
            $correctOutcomes += $breakdown->isCorrectOutcome ? 1 : 0;
            $teamGoalsHit += $breakdown->teamGoalsHit;
        }

        return new self(
            points: $points,
            correctOutcomes: $correctOutcomes,
            teamGoalsHit: $teamGoalsHit,
        );
    }
}
