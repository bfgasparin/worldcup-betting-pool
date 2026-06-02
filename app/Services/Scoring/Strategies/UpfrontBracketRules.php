<?php

namespace App\Services\Scoring\Strategies;

use App\Models\Fixture;
use App\Models\GroupPrediction;
use App\Models\KnockoutPrediction;
use App\Services\Scoring\KnockoutProgressionScorer;
use App\Services\Scoring\PredictionBreakdown;
use App\Services\Scoring\ScorelineScorer;
use App\Services\Scoring\ScoringConfig;

/**
 * The upfront-bracket rules: players predict the whole tournament before kickoff, so the group
 * stage is scored on the predicted scoreline (the four-tier ladder), while the knockout stage is
 * scored per team the player correctly placed in each fixture of their projected bracket, plus
 * per-team goal-count and champion bonuses.
 */
class UpfrontBracketRules implements ScoringRules
{
    public function __construct(
        private readonly ScorelineScorer $scorelineScorer = new ScorelineScorer,
        private readonly KnockoutProgressionScorer $knockoutScorer = new KnockoutProgressionScorer,
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
            return new PredictionBreakdown(points: 0, isExactScore: false, isCorrectOutcome: false, teamGoalsHit: 0);
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
        return $this->knockoutScorer->evaluate($prediction, $fixture, $config);
    }
}
