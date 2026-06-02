<?php

namespace App\Services\Scoring\Strategies;

use App\Enums\ScoringStrategy;
use App\Models\Fixture;
use App\Models\GroupPrediction;
use App\Models\KnockoutPrediction;
use App\Services\Scoring\PredictionBreakdown;
use App\Services\Scoring\ScoringConfig;
use App\Services\Scoring\ScoringRulesFactory;

/**
 * A per-tournament scoring strategy. Selected from {@see ScoringStrategy} by
 * {@see ScoringRulesFactory}, so different tournaments can score knockouts
 * differently (this World Cup uses bracket progression; a future between-phases tournament will
 * score knockouts on the scoreline, exactly like the group stage).
 *
 * The `score*` methods return just the points (what gets persisted); the `evaluate*` methods return
 * the richer breakdown the leaderboards aggregate. `score*` delegate to `evaluate*`, so the points
 * are always identical.
 */
interface ScoringRules
{
    public function scoreGroup(GroupPrediction $prediction, Fixture $fixture, ScoringConfig $config): int;

    public function scoreKnockout(KnockoutPrediction $prediction, Fixture $fixture, ScoringConfig $config): int;

    public function evaluateGroup(GroupPrediction $prediction, Fixture $fixture, ScoringConfig $config): PredictionBreakdown;

    public function evaluateKnockout(KnockoutPrediction $prediction, Fixture $fixture, ScoringConfig $config): PredictionBreakdown;
}
