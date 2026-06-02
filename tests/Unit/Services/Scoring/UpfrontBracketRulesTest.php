<?php

namespace Tests\Unit\Services\Scoring;

use App\Models\Fixture;
use App\Models\GroupPrediction;
use App\Services\Scoring\ScoringConfig;
use App\Services\Scoring\Strategies\UpfrontBracketRules;
use PHPUnit\Framework\TestCase;

class UpfrontBracketRulesTest extends TestCase
{
    private UpfrontBracketRules $rules;

    private ScoringConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rules = new UpfrontBracketRules;
        $this->config = new ScoringConfig([
            'group' => [
                'exact_score' => 20,
                'winner_and_one_team_exact_goals' => 15,
                'correct_outcome_wrong_goals' => 10,
                'one_team_exact_goals_wrong_outcome' => 5,
            ],
        ]);
    }

    public function test_an_unscored_group_prediction_scores_nothing(): void
    {
        // Regression guard: the null-goal early return must construct a valid PredictionBreakdown.
        $breakdown = $this->rules->evaluateGroup(
            new GroupPrediction(['home_goals' => null, 'away_goals' => null]),
            new Fixture(['home_goals' => 2, 'away_goals' => 1]),
            $this->config,
        );

        $this->assertSame(0, $breakdown->points);
        $this->assertFalse($breakdown->isCorrectOutcome);
        $this->assertSame(0, $breakdown->teamGoalsHit);
    }

    public function test_an_exact_group_scoreline_scores_the_top_tier(): void
    {
        $breakdown = $this->rules->evaluateGroup(
            new GroupPrediction(['home_goals' => 2, 'away_goals' => 1]),
            new Fixture(['home_goals' => 2, 'away_goals' => 1]),
            $this->config,
        );

        $this->assertSame(20, $breakdown->points);
        $this->assertTrue($breakdown->isCorrectOutcome);
        $this->assertSame(2, $breakdown->teamGoalsHit);
    }
}
