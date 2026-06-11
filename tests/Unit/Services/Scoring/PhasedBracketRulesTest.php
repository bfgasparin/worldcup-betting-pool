<?php

namespace Tests\Unit\Services\Scoring;

use App\Enums\PhaseKey;
use App\Models\Fixture;
use App\Models\GroupPrediction;
use App\Models\KnockoutPrediction;
use App\Models\Phase;
use App\Services\Scoring\ScoringConfig;
use App\Services\Scoring\Strategies\PhasedBracketRules;
use PHPUnit\Framework\TestCase;

class PhasedBracketRulesTest extends TestCase
{
    private PhasedBracketRules $rules;

    private ScoringConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rules = new PhasedBracketRules;
        $this->config = new ScoringConfig([
            'group' => [
                'exact_score' => 20,
                'winner_and_one_team_exact_goals' => 15,
                'correct_outcome_wrong_goals' => 10,
                'one_team_exact_goals_wrong_outcome' => 5,
            ],
            'knockout' => [
                'exact_score' => 20,
                'winner_and_one_team_exact_goals' => 15,
                'correct_outcome_wrong_goals' => 10,
                'one_team_exact_goals_wrong_outcome' => 5,
                'advancing_team' => 10,
                'round_multipliers' => [
                    'round_of_32' => 1,
                    'round_of_16' => 2,
                    'quarter_finals' => 4,
                    'semi_finals' => 6,
                    'third_place' => 4,
                    'final' => 8,
                ],
            ],
        ]);
    }

    public function test_group_scoring_uses_the_scoreline_ladder(): void
    {
        // Exact group scoreline → top tier (like the upfront strategy).
        $exact = $this->rules->scoreGroup(
            new GroupPrediction(['home_goals' => 2, 'away_goals' => 1]),
            $this->groupFixture(2, 1),
            $this->config,
        );
        $this->assertSame(20, $exact);

        // Right result, wrong goals → third tier.
        $outcome = $this->rules->scoreGroup(
            new GroupPrediction(['home_goals' => 3, 'away_goals' => 0]),
            $this->groupFixture(2, 1),
            $this->config,
        );
        $this->assertSame(10, $outcome);
    }

    public function test_an_unscored_group_prediction_scores_nothing(): void
    {
        $score = $this->rules->scoreGroup(
            new GroupPrediction(['home_goals' => null, 'away_goals' => null]),
            $this->groupFixture(2, 1),
            $this->config,
        );

        $this->assertSame(0, $score);
    }

    public function test_knockout_exact_scoreline_in_the_round_of_32_plus_advancing(): void
    {
        // R32 (×1): exact score 20 + flat advancing bonus 10 = 30.
        $fixture = $this->knockoutFixture(PhaseKey::RoundOf32, 2, 1, winnerTeamId: 1);
        $prediction = new KnockoutPrediction(['home_goals' => 2, 'away_goals' => 1, 'advancing_team_id' => 1]);

        $this->assertSame(30, $this->rules->scoreKnockout($prediction, $fixture, $this->config));
    }

    public function test_knockout_tiers_scale_with_the_round(): void
    {
        // Final (×8): exact score 20 × 8 = 160, + advancing 10 = 170. No champion bonus.
        $fixture = $this->knockoutFixture(PhaseKey::Final, 2, 1, winnerTeamId: 1);
        $prediction = new KnockoutPrediction(['home_goals' => 2, 'away_goals' => 1, 'advancing_team_id' => 1]);

        $this->assertSame(170, $this->rules->scoreKnockout($prediction, $fixture, $this->config));
    }

    public function test_correct_outcome_tier_scales_with_the_round(): void
    {
        // Quarter-final (×4). Predicted 2–1, actual 3–0: right result but neither goal count exact
        // → "correct outcome" tier 10 × 4 = 40, + flat advancing 10 = 50. (The Final's
        // no-champion-bonus is guarded by test_knockout_tiers_scale_with_the_round: 170, not 200.)
        $fixture = $this->knockoutFixture(PhaseKey::QuarterFinals, 3, 0, winnerTeamId: 1);
        $prediction = new KnockoutPrediction(['home_goals' => 2, 'away_goals' => 1, 'advancing_team_id' => 1]);

        $this->assertSame(50, $this->rules->scoreKnockout($prediction, $fixture, $this->config));
    }

    public function test_advancing_bonus_is_the_safety_net_when_the_scoreline_is_wrong(): void
    {
        // Predict 1–1 (pick the home team on penalties); actual 2–1 home win in the R32 (×1).
        // Scoreline: wrong outcome, away goals exact → one-team tier 5. Advancing: home, and home
        // won → flat +10. Total 15.
        $fixture = $this->knockoutFixture(PhaseKey::RoundOf32, 2, 1, winnerTeamId: 7);
        $prediction = new KnockoutPrediction(['home_goals' => 1, 'away_goals' => 1, 'advancing_team_id' => 7]);

        $this->assertSame(15, $this->rules->scoreKnockout($prediction, $fixture, $this->config));
    }

    public function test_no_advancing_bonus_when_the_pick_did_not_go_through(): void
    {
        // Exact scoreline (20) but the advancing pick lost → scoreline only, no bonus.
        $fixture = $this->knockoutFixture(PhaseKey::RoundOf32, 2, 1, winnerTeamId: 7);
        $prediction = new KnockoutPrediction(['home_goals' => 2, 'away_goals' => 1, 'advancing_team_id' => 99]);

        $breakdown = $this->rules->evaluateKnockout($prediction, $fixture, $this->config);

        $this->assertSame(20, $breakdown->points);
        $this->assertFalse($breakdown->isCorrectOutcome);
        $this->assertSame(2, $breakdown->teamGoalsHit);
    }

    public function test_a_penalty_shootout_call_earns_the_scoreline_tier_and_the_advancing_bonus(): void
    {
        // R16 (×2) ends 1–1 and goes to penalties; the away side (7) is recorded as the winner.
        // Predicting 1–1 with the right shootout pick banks exact 20 × 2 = 40, + advancing 10 = 50.
        $fixture = $this->knockoutFixture(PhaseKey::RoundOf16, 1, 1, winnerTeamId: 7);
        $prediction = new KnockoutPrediction(['home_goals' => 1, 'away_goals' => 1, 'advancing_team_id' => 7]);

        $breakdown = $this->rules->evaluateKnockout($prediction, $fixture, $this->config);

        $this->assertSame(50, $breakdown->points);
        $this->assertTrue($breakdown->isCorrectOutcome);
        $this->assertSame(2, $breakdown->teamGoalsHit);
    }

    public function test_a_wrong_shootout_pick_keeps_the_scoreline_tier_but_not_the_outcome(): void
    {
        // Same 1–1 decided on penalties, but the pick (1) lost the shootout: exact tier 40 only,
        // and the Match Winners board does not count the game.
        $fixture = $this->knockoutFixture(PhaseKey::RoundOf16, 1, 1, winnerTeamId: 7);
        $prediction = new KnockoutPrediction(['home_goals' => 1, 'away_goals' => 1, 'advancing_team_id' => 1]);

        $breakdown = $this->rules->evaluateKnockout($prediction, $fixture, $this->config);

        $this->assertSame(40, $breakdown->points);
        $this->assertFalse($breakdown->isCorrectOutcome);
    }

    public function test_an_unplayed_knockout_fixture_scores_nothing(): void
    {
        $fixture = $this->knockoutFixture(PhaseKey::RoundOf16, null, null);
        $prediction = new KnockoutPrediction(['home_goals' => 1, 'away_goals' => 0, 'advancing_team_id' => 1]);

        $this->assertSame(0, $this->rules->scoreKnockout($prediction, $fixture, $this->config));
    }

    public function test_evaluate_knockout_exposes_correct_outcome_from_the_advancing_pick(): void
    {
        $fixture = $this->knockoutFixture(PhaseKey::SemiFinals, 1, 0, winnerTeamId: 1);
        $breakdown = $this->rules->evaluateKnockout(
            new KnockoutPrediction(['home_goals' => 1, 'away_goals' => 0, 'advancing_team_id' => 1]),
            $fixture,
            $this->config,
        );

        // SF (×6): exact 20 × 6 = 120, + advancing 10 = 130.
        $this->assertSame(130, $breakdown->points);
        $this->assertTrue($breakdown->isCorrectOutcome);
        $this->assertSame(2, $breakdown->teamGoalsHit);
    }

    private function groupFixture(int $homeGoals, int $awayGoals): Fixture
    {
        return new Fixture(['home_goals' => $homeGoals, 'away_goals' => $awayGoals]);
    }

    private function knockoutFixture(PhaseKey $key, ?int $homeGoals, ?int $awayGoals, ?int $winnerTeamId = null): Fixture
    {
        $phase = new Phase;
        $phase->key = $key;

        $fixture = new Fixture([
            'home_goals' => $homeGoals,
            'away_goals' => $awayGoals,
            'winner_team_id' => $winnerTeamId,
        ]);
        $fixture->setRelation('phase', $phase);

        return $fixture;
    }
}
