<?php

namespace App\Services\Scoring\Strategies;

use App\Models\Fixture;
use App\Models\GroupPrediction;
use App\Models\KnockoutPrediction;
use App\Services\Scoring\KnockoutProgressionScorer;
use App\Services\Scoring\ScorelineScorer;
use App\Services\Scoring\ScoringConfig;

/**
 * The standard World Cup rules: the group stage is scored on the predicted scoreline (the
 * four-tier ladder), while the knockout stage is scored per team the player correctly placed in
 * each fixture, plus per-team goal-count and champion bonuses.
 */
class WorldCupStandardRules implements ScoringRules
{
    public function __construct(
        private readonly ScorelineScorer $scorelineScorer = new ScorelineScorer,
        private readonly KnockoutProgressionScorer $knockoutScorer = new KnockoutProgressionScorer,
    ) {}

    public function scoreGroup(GroupPrediction $prediction, Fixture $fixture, ScoringConfig $config): int
    {
        if ($prediction->home_goals === null || $prediction->away_goals === null
            || $fixture->home_goals === null || $fixture->away_goals === null) {
            return 0;
        }

        return $this->scorelineScorer->score(
            $prediction->home_goals,
            $prediction->away_goals,
            $fixture->home_goals,
            $fixture->away_goals,
            $config->groupTiers(),
        );
    }

    public function scoreKnockout(KnockoutPrediction $prediction, Fixture $fixture, ScoringConfig $config): int
    {
        return $this->knockoutScorer->score($prediction, $fixture, $config);
    }
}
