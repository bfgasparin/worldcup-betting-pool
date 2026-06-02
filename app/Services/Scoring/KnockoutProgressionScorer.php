<?php

namespace App\Services\Scoring;

use App\Enums\PhaseKey;
use App\Models\Fixture;
use App\Models\KnockoutPrediction;

/**
 * Scores a knockout prediction team by team. For each of the two teams the player named for a
 * fixture:
 *
 *  - correct_team: award when that team is actually one of the two teams contesting this match
 *    (the player correctly "classified" it into this exact match — a wrong opponent never
 *    borrows points from the team that was right).
 *  - team_goal_count_bonus: additionally award when that correctly-placed team's predicted goal
 *    count matches the official one (compared by team, regardless of side).
 *
 * On the final only, the champion bonus is added when the player's advancing pick is the actual
 * champion. So a fixture where the player nailed both teams and both scores is worth
 * (10 + 5) × 2 = 30, plus the champion bonus on the final.
 */
class KnockoutProgressionScorer
{
    public function score(KnockoutPrediction $prediction, Fixture $fixture, ScoringConfig $config): int
    {
        return $this->evaluate($prediction, $fixture, $config)->points;
    }

    /**
     * Score a knockout prediction, returning the points plus the per-category signals the
     * leaderboards roll up (team-based, since the player predicts the matchup): how many of the two
     * named teams were correctly placed *and* on the right score ({@see PredictionBreakdown::teamGoalsHit},
     * 0–2), and whether the player sent the team that actually advanced through
     * ({@see PredictionBreakdown::isCorrectOutcome}).
     */
    public function evaluate(KnockoutPrediction $prediction, Fixture $fixture, ScoringConfig $config): PredictionBreakdown
    {
        $officialHome = $this->asInt($fixture->home_team_id);
        $officialAway = $this->asInt($fixture->away_team_id);

        // The match has no official line-up yet (not projected/played) — nothing to score.
        if ($officialHome === null || $officialAway === null) {
            return new PredictionBreakdown(points: 0, isCorrectOutcome: false, teamGoalsHit: 0);
        }

        $official = [$officialHome, $officialAway];
        $points = 0;
        $teamGoalsHit = 0;

        $sides = [
            [$this->asInt($prediction->predicted_home_team_id), $prediction->home_goals],
            [$this->asInt($prediction->predicted_away_team_id), $prediction->away_goals],
        ];

        foreach ($sides as [$teamId, $predictedGoals]) {
            // Correct classification: the predicted team really is in this match.
            if ($teamId === null || ! in_array($teamId, $official, true)) {
                continue;
            }

            $points += $config->knockoutCorrectTeam();

            // Plus a bonus when that team's predicted goal count matches the official one.
            $officialGoals = $teamId === $officialHome ? $fixture->home_goals : $fixture->away_goals;

            if ($predictedGoals !== null && (int) $predictedGoals === $officialGoals) {
                $points += $config->knockoutGoalCountBonus();
                $teamGoalsHit++;
            }
        }

        // The "match winner": the player sent the team that actually advanced through.
        $correctWinner = $prediction->advancing_team_id !== null
            && $this->asInt($prediction->advancing_team_id) === $this->asInt($fixture->winner_team_id);

        // Champion bonus on the final.
        if ($fixture->phase->key === PhaseKey::Final && $correctWinner) {
            $points += $config->champion();
        }

        return new PredictionBreakdown(
            points: $points,
            isCorrectOutcome: $correctWinner,
            teamGoalsHit: $teamGoalsHit,
        );
    }

    private function asInt(int|string|null $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
