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
        if ($predictedHome === $actualHome && $predictedAway === $actualAway) {
            return $tiers->exactScore;
        }

        $outcomeCorrect = ($predictedHome <=> $predictedAway) === ($actualHome <=> $actualAway);
        $oneTeamExact = $predictedHome === $actualHome || $predictedAway === $actualAway;

        if ($outcomeCorrect && $oneTeamExact) {
            return $tiers->correctOutcomeAndOneTeamExact;
        }

        if ($outcomeCorrect) {
            return $tiers->correctOutcome;
        }

        if ($oneTeamExact) {
            return $tiers->oneTeamExact;
        }

        return 0;
    }
}
