<?php

namespace App\Services\Scoring;

/**
 * Scores a single predicted scoreline against the official one using the four descending tiers
 * (exact -> correct outcome + one exact goal count -> correct outcome -> one exact goal count ->
 * nothing). The tiers are mutually exclusive by construction and evaluated as an ordered ladder
 * so the mapping is unambiguous. A correct draw counts as a correct outcome.
 */
class ScorelineScorer
{
    public function score(int $predictedHome, int $predictedAway, int $actualHome, int $actualAway, ScorelineTiers $tiers): int
    {
        return $this->evaluate($predictedHome, $predictedAway, $actualHome, $actualAway, $tiers)->points;
    }

    /**
     * Compare a predicted scoreline against the official one, returning both the tier points and
     * the per-category signals (exact score, correct outcome, individual team goals hit 0–2) the
     * leaderboards roll up.
     */
    public function evaluate(int $predictedHome, int $predictedAway, int $actualHome, int $actualAway, ScorelineTiers $tiers): PredictionBreakdown
    {
        $homeExact = $predictedHome === $actualHome;
        $awayExact = $predictedAway === $actualAway;
        $isExactScore = $homeExact && $awayExact;
        $oneTeamExact = $homeExact || $awayExact;
        $outcomeCorrect = ($predictedHome <=> $predictedAway) === ($actualHome <=> $actualAway);

        $points = match (true) {
            $isExactScore => $tiers->exactScore,
            $outcomeCorrect && $oneTeamExact => $tiers->correctOutcomeAndOneTeamExact,
            $outcomeCorrect => $tiers->correctOutcome,
            $oneTeamExact => $tiers->oneTeamExact,
            default => 0,
        };

        return new PredictionBreakdown(
            points: $points,
            isCorrectOutcome: $outcomeCorrect,
            teamGoalsHit: (int) $homeExact + (int) $awayExact,
        );
    }
}
