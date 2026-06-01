<?php

namespace Tests\Unit\Services\Scoring;

use App\Enums\PhaseKey;
use App\Models\Fixture;
use App\Models\KnockoutPrediction;
use App\Models\Phase;
use App\Services\Scoring\KnockoutProgressionScorer;
use App\Services\Scoring\ScoringConfig;
use PHPUnit\Framework\TestCase;

class KnockoutProgressionScorerTest extends TestCase
{
    private KnockoutProgressionScorer $scorer;

    private ScoringConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scorer = new KnockoutProgressionScorer;
        $this->config = new ScoringConfig([
            'knockout' => ['correct_team' => 10, 'team_goal_count_bonus' => 5, 'champion' => 30],
        ]);
    }

    public function test_both_correct_teams_and_scores_make_thirty(): void
    {
        $fixture = $this->fixture(PhaseKey::QuarterFinals, 1, 5, 2, 1);
        $prediction = new KnockoutPrediction([
            'predicted_home_team_id' => 1, 'home_goals' => 2,
            'predicted_away_team_id' => 5, 'away_goals' => 1,
        ]);

        // Each correct team (+10) with its correct score (+5).
        $this->assertSame(30, $this->scorer->score($prediction, $fixture, $this->config));
    }

    public function test_a_wrong_opponent_only_forfeits_that_team(): void
    {
        // The user's example: predicted Portugal (1) 3–1 France (99); the real match was
        // Portugal (1) 3–1 Brazil (2). Portugal is in the match (+10) with the right score (+5);
        // France never played, so it scores nothing.
        $fixture = $this->fixture(PhaseKey::RoundOf32, 1, 2, 3, 1);
        $prediction = new KnockoutPrediction([
            'predicted_home_team_id' => 1, 'home_goals' => 3,
            'predicted_away_team_id' => 99, 'away_goals' => 1,
        ]);

        $this->assertSame(15, $this->scorer->score($prediction, $fixture, $this->config));
    }

    public function test_a_correct_team_with_the_wrong_score_scores_ten(): void
    {
        $fixture = $this->fixture(PhaseKey::RoundOf32, 1, 2, 1, 0);
        $prediction = new KnockoutPrediction([
            'predicted_home_team_id' => 1, 'home_goals' => 3, // right team, wrong score
            'predicted_away_team_id' => 99, 'away_goals' => 0,
        ]);

        $this->assertSame(10, $this->scorer->score($prediction, $fixture, $this->config));
    }

    public function test_a_correct_team_with_the_right_score_scores_fifteen(): void
    {
        $fixture = $this->fixture(PhaseKey::RoundOf32, 1, 2, 1, 0);
        $prediction = new KnockoutPrediction([
            'predicted_home_team_id' => 1, 'home_goals' => 1,
            'predicted_away_team_id' => 99, 'away_goals' => 0,
        ]);

        $this->assertSame(15, $this->scorer->score($prediction, $fixture, $this->config));
    }

    public function test_a_swapped_line_up_still_scores_both_teams(): void
    {
        // Official home 1 (2 goals) v away 5 (1 goal); the player listed them swapped.
        $fixture = $this->fixture(PhaseKey::QuarterFinals, 1, 5, 2, 1);
        $prediction = new KnockoutPrediction([
            'predicted_home_team_id' => 5, 'home_goals' => 1, // team 5 scored 1
            'predicted_away_team_id' => 1, 'away_goals' => 2, // team 1 scored 2
        ]);

        $this->assertSame(30, $this->scorer->score($prediction, $fixture, $this->config));
    }

    public function test_the_final_adds_the_champion_bonus(): void
    {
        $fixture = $this->fixture(PhaseKey::Final, 1, 5, 2, 1, 1);
        $prediction = new KnockoutPrediction([
            'predicted_home_team_id' => 1, 'home_goals' => 2,
            'predicted_away_team_id' => 5, 'away_goals' => 1,
            'advancing_team_id' => 1,
        ]);

        // Both teams (+10 each) + both scores (+5 each) + champion (+30).
        $this->assertSame(60, $this->scorer->score($prediction, $fixture, $this->config));
    }

    public function test_the_champion_bonus_is_only_for_the_final(): void
    {
        // Same correct advancing pick on the third-place play-off — both teams (+10 each), no champion.
        $fixture = $this->fixture(PhaseKey::ThirdPlace, 3, 7, 1, 0, 3);
        $prediction = new KnockoutPrediction([
            'predicted_home_team_id' => 3,
            'predicted_away_team_id' => 7,
            'advancing_team_id' => 3,
        ]);

        $this->assertSame(20, $this->scorer->score($prediction, $fixture, $this->config));
    }

    public function test_teams_that_missed_the_match_score_nothing(): void
    {
        $fixture = $this->fixture(PhaseKey::QuarterFinals, 1, 2, 1, 0);
        $prediction = new KnockoutPrediction(['predicted_home_team_id' => 8, 'predicted_away_team_id' => 9]);

        $this->assertSame(0, $this->scorer->score($prediction, $fixture, $this->config));
    }

    public function test_an_unprojected_fixture_scores_nothing(): void
    {
        $fixture = $this->fixture(PhaseKey::RoundOf16, null, null);
        $prediction = new KnockoutPrediction(['predicted_home_team_id' => 1, 'predicted_away_team_id' => 2]);

        $this->assertSame(0, $this->scorer->score($prediction, $fixture, $this->config));
    }

    private function fixture(PhaseKey $key, ?int $homeTeamId, ?int $awayTeamId, ?int $homeGoals = null, ?int $awayGoals = null, ?int $winnerTeamId = null): Fixture
    {
        $phase = new Phase;
        $phase->key = $key;

        $fixture = new Fixture([
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
            'home_goals' => $homeGoals,
            'away_goals' => $awayGoals,
            'winner_team_id' => $winnerTeamId,
        ]);
        $fixture->setRelation('phase', $phase);

        return $fixture;
    }
}
