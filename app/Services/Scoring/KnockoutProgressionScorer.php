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
        $officialHome = $this->asInt($fixture->home_team_id);
        $officialAway = $this->asInt($fixture->away_team_id);

        // The match has no official line-up yet (not projected/played) — nothing to score.
        if ($officialHome === null || $officialAway === null) {
            return 0;
        }

        $official = [$officialHome, $officialAway];
        $points = 0;

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
            }
        }

        // Champion bonus on the final.
        if ($fixture->phase->key === PhaseKey::Final
            && $prediction->advancing_team_id !== null
            && $this->asInt($prediction->advancing_team_id) === $this->asInt($fixture->winner_team_id)) {
            $points += $config->champion();
        }

        return $points;
    }

    private function asInt(int|string|null $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
