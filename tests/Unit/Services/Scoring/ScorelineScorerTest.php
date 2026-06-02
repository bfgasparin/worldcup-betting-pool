<?php

namespace Tests\Unit\Services\Scoring;

use App\Services\Scoring\ScorelineScorer;
use App\Services\Scoring\ScorelineTiers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ScorelineScorerTest extends TestCase
{
    private ScorelineScorer $scorer;

    private ScorelineTiers $tiers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scorer = new ScorelineScorer;
        // The seeded World Cup group tiers: 20 / 15 / 10 / 5.
        $this->tiers = new ScorelineTiers(20, 15, 10, 5);
    }

    /**
     * @param  array{int, int}  $prediction
     * @param  array{int, int}  $actual
     */
    #[DataProvider('scorelineCases')]
    public function test_it_scores_each_tier(array $prediction, array $actual, int $expected): void
    {
        $this->assertSame(
            $expected,
            $this->scorer->score($prediction[0], $prediction[1], $actual[0], $actual[1], $this->tiers),
        );
    }

    /**
     * @param  array{int, int}  $prediction
     * @param  array{int, int}  $actual
     */
    #[DataProvider('breakdownCases')]
    public function test_evaluate_exposes_the_per_category_breakdown(
        array $prediction,
        array $actual,
        int $points,
        bool $isCorrectOutcome,
        int $teamGoalsHit,
    ): void {
        $breakdown = $this->scorer->evaluate($prediction[0], $prediction[1], $actual[0], $actual[1], $this->tiers);

        $this->assertSame($points, $breakdown->points);
        $this->assertSame($isCorrectOutcome, $breakdown->isCorrectOutcome);
        $this->assertSame($teamGoalsHit, $breakdown->teamGoalsHit);
    }

    /**
     * @return array<string, array{array{int, int}, array{int, int}, int, bool, int}>
     */
    public static function breakdownCases(): array
    {
        return [
            // prediction, actual, points, correct outcome?, team goals hit (0-2)
            'exact win' => [[2, 1], [2, 1], 20, true, 2],
            'exact draw' => [[0, 0], [0, 0], 20, true, 2],
            'correct win, home goals exact' => [[2, 0], [2, 1], 15, true, 1],
            'correct draw, no exact goals' => [[1, 1], [2, 2], 10, true, 0],
            'wrong outcome, one goal exact' => [[2, 2], [2, 0], 5, false, 1],
            'total miss' => [[0, 3], [2, 1], 0, false, 0],
        ];
    }

    /**
     * @return array<string, array{array{int, int}, array{int, int}, int}>
     */
    public static function scorelineCases(): array
    {
        return [
            'exact win' => [[2, 1], [2, 1], 20],
            'exact draw' => [[1, 1], [1, 1], 20],
            'exact nil-nil' => [[0, 0], [0, 0], 20],
            'correct home win + home goals exact' => [[2, 0], [2, 1], 15],
            'correct home win + away goals exact' => [[3, 1], [2, 1], 15],
            'correct draw, no exact goal count' => [[1, 1], [2, 2], 10], // draw outcome, neither goal exact
            'predicted draw, home goals exact, wrong outcome' => [[2, 2], [2, 0], 5],
            'correct outcome, both goals wrong' => [[3, 0], [2, 1], 10],
            'wrong outcome but one goal count exact' => [[1, 2], [1, 0], 5], // home goals 1 exact, outcome flipped
            'total miss' => [[0, 3], [2, 1], 0],
        ];
    }
}
