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
}
