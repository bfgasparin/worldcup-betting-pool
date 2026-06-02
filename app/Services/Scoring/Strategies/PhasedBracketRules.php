<?php

namespace App\Services\Scoring\Strategies;

use App\Models\Fixture;
use App\Models\GroupPrediction;
use App\Models\KnockoutPrediction;
use App\Services\Scoring\PredictionBreakdown;
use App\Services\Scoring\ScorelineScorer;
use App\Services\Scoring\ScoringConfig;

/**
 * The phased-bracket rules: players predict the group stage upfront, then predict each knockout
 * round against the *official* match-ups as they become known. Both stages are scored on the
 * scoreline (the four-tier ladder), but each knockout round's tiers are scaled by a rising
 * multiplier so the back half of the tournament stays decisive. A flat bonus is added on top of
 * the scoreline whenever the player correctly called who advances. There is no champion bonus —
 * the Final's round multiplier already makes it the biggest single prize.
 */
class PhasedBracketRules implements ScoringRules
{
    public function __construct(
        private readonly ScorelineScorer $scorelineScorer = new ScorelineScorer,
    ) {}

    public function scoreGroup(GroupPrediction $prediction, Fixture $fixture, ScoringConfig $config): int
    {
        return $this->evaluateGroup($prediction, $fixture, $config)->points;
    }

    public function scoreKnockout(KnockoutPrediction $prediction, Fixture $fixture, ScoringConfig $config): int
    {
        return $this->evaluateKnockout($prediction, $fixture, $config)->points;
    }

    public function evaluateGroup(GroupPrediction $prediction, Fixture $fixture, ScoringConfig $config): PredictionBreakdown
    {
        if ($prediction->home_goals === null || $prediction->away_goals === null
            || $fixture->home_goals === null || $fixture->away_goals === null) {
            return new PredictionBreakdown(points: 0, isCorrectOutcome: false, teamGoalsHit: 0);
        }

        return $this->scorelineScorer->evaluate(
            $prediction->home_goals,
            $prediction->away_goals,
            $fixture->home_goals,
            $fixture->away_goals,
            $config->groupTiers(),
        );
    }

    public function evaluateKnockout(KnockoutPrediction $prediction, Fixture $fixture, ScoringConfig $config): PredictionBreakdown
    {
        // The advancing pick is scored independently of the scoreline: a flat bonus for sending
        // the team that actually went through (the only thing that rewards a penalty-shootout call).
        $advancingCorrect = $prediction->advancing_team_id !== null
            && $fixture->winner_team_id !== null
            && (int) $prediction->advancing_team_id === (int) $fixture->winner_team_id;

        $advancingBonus = $advancingCorrect ? $config->knockoutAdvancingBonus() : 0;

        if ($prediction->home_goals === null || $prediction->away_goals === null
            || $fixture->home_goals === null || $fixture->away_goals === null) {
            return new PredictionBreakdown(
                points: $advancingBonus,
                isCorrectOutcome: $advancingCorrect,
                teamGoalsHit: 0,
            );
        }

        $scoreline = $this->scorelineScorer->evaluate(
            $prediction->home_goals,
            $prediction->away_goals,
            $fixture->home_goals,
            $fixture->away_goals,
            $config->knockoutTiers($fixture->phase->key),
        );

        return new PredictionBreakdown(
            points: $scoreline->points + $advancingBonus,
            isCorrectOutcome: $advancingCorrect,
            teamGoalsHit: $scoreline->teamGoalsHit,
        );
    }
}
