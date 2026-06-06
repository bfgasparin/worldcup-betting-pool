<?php

namespace Tests\Unit\Enums;

use App\Enums\LeaderboardCategory;
use App\Services\Scoring\LeaderboardMetrics;
use PHPUnit\Framework\TestCase;

class LeaderboardCategoryTest extends TestCase
{
    public function test_ordered_leads_with_overall_and_lists_every_board(): void
    {
        $ordered = LeaderboardCategory::ordered();

        $this->assertSame(LeaderboardCategory::cases(), $ordered);
        $this->assertSame(LeaderboardCategory::Overall, $ordered[0]);
        $this->assertCount(3, $ordered);
    }

    public function test_only_the_overall_board_notifies(): void
    {
        $this->assertTrue(LeaderboardCategory::Overall->notifies());

        foreach (LeaderboardCategory::cases() as $category) {
            if ($category !== LeaderboardCategory::Overall) {
                $this->assertFalse($category->notifies(), "{$category->value} must not notify");
            }
        }
    }

    public function test_only_the_overall_board_awards_prizes(): void
    {
        $this->assertTrue(LeaderboardCategory::Overall->awardsPrizes());

        foreach (LeaderboardCategory::cases() as $category) {
            if ($category !== LeaderboardCategory::Overall) {
                $this->assertFalse($category->awardsPrizes(), "{$category->value} must not award prizes");
            }
        }
    }

    public function test_every_board_has_display_labels(): void
    {
        foreach (LeaderboardCategory::cases() as $category) {
            $this->assertNotSame('', $category->label());
            $this->assertNotSame('', $category->description());
            $this->assertNotSame('', $category->primaryStatLabel());
        }

        // Only the Overall board has no tie-break to show.
        $this->assertNull(LeaderboardCategory::Overall->secondaryStatLabel());
        $this->assertNotNull(LeaderboardCategory::MatchWinners->secondaryStatLabel());
    }

    public function test_each_board_reads_its_value_and_tie_break_from_the_metrics(): void
    {
        $metrics = new LeaderboardMetrics(
            points: 140,
            correctOutcomes: 12,
            teamGoalsHit: 18,
        );

        // value
        $this->assertSame(140, LeaderboardCategory::Overall->valueFor($metrics));
        $this->assertSame(12, LeaderboardCategory::MatchWinners->valueFor($metrics));
        $this->assertSame(18, LeaderboardCategory::GoalSniper->valueFor($metrics));

        // tie-break: the two boards cross-reference each other's metric.
        $this->assertSame(0, LeaderboardCategory::Overall->tiebreakerFor($metrics));
        $this->assertSame(18, LeaderboardCategory::MatchWinners->tiebreakerFor($metrics));
        $this->assertSame(12, LeaderboardCategory::GoalSniper->tiebreakerFor($metrics));
    }
}
