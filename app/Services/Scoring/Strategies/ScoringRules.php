<?php

namespace App\Services\Scoring\Strategies;

use App\Enums\ScoringStrategy;
use App\Models\Fixture;
use App\Models\GroupPrediction;
use App\Models\KnockoutPrediction;
use App\Services\Scoring\ScoringConfig;
use App\Services\Scoring\ScoringRulesFactory;

/**
 * A per-tournament scoring strategy. Selected from {@see ScoringStrategy} by
 * {@see ScoringRulesFactory}, so different tournaments can score knockouts
 * differently (this World Cup uses bracket progression; a future between-phases tournament will
 * score knockouts on the scoreline, exactly like the group stage).
 */
interface ScoringRules
{
    public function scoreGroup(GroupPrediction $prediction, Fixture $fixture, ScoringConfig $config): int;

    public function scoreKnockout(KnockoutPrediction $prediction, Fixture $fixture, ScoringConfig $config): int;
}
