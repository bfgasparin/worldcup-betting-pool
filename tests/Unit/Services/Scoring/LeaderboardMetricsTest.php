<?php

namespace Tests\Unit\Services\Scoring;

use App\Services\Scoring\LeaderboardMetrics;
use App\Services\Scoring\PredictionBreakdown;
use PHPUnit\Framework\TestCase;

class LeaderboardMetricsTest extends TestCase
{
    public function test_from_breakdowns_sums_points_outcomes_and_team_goals(): void
    {
        $metrics = LeaderboardMetrics::fromBreakdowns([
            new PredictionBreakdown(points: 20, isCorrectOutcome: true, teamGoalsHit: 2),
            new PredictionBreakdown(points: 10, isCorrectOutcome: true, teamGoalsHit: 0),
            new PredictionBreakdown(points: 0, isCorrectOutcome: false, teamGoalsHit: 1),
        ]);

        $this->assertSame(30, $metrics->points);
        $this->assertSame(2, $metrics->correctOutcomes);
        $this->assertSame(3, $metrics->teamGoalsHit);
    }

    public function test_from_breakdowns_is_zero_for_an_empty_set(): void
    {
        $metrics = LeaderboardMetrics::fromBreakdowns([]);

        $this->assertSame(0, $metrics->points);
        $this->assertSame(0, $metrics->correctOutcomes);
        $this->assertSame(0, $metrics->teamGoalsHit);
    }
}
