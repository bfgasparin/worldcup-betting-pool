<?php

namespace Tests\Unit\Services\Scoring;

use App\Enums\PhaseKey;
use App\Services\Scoring\ScoringConfig;
use PHPUnit\Framework\TestCase;

class ScoringConfigTest extends TestCase
{
    private function phasedConfig(): ScoringConfig
    {
        return new ScoringConfig([
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

    public function test_knockout_tiers_are_unmultiplied_in_the_round_of_32(): void
    {
        $tiers = $this->phasedConfig()->knockoutTiers(PhaseKey::RoundOf32);

        $this->assertSame(20, $tiers->exactScore);
        $this->assertSame(15, $tiers->correctOutcomeAndOneTeamExact);
        $this->assertSame(10, $tiers->correctOutcome);
        $this->assertSame(5, $tiers->oneTeamExact);
    }

    public function test_knockout_tiers_apply_the_rounds_multiplier(): void
    {
        // The Final carries the ×8 multiplier: 20/15/10/5 → 160/120/80/40.
        $tiers = $this->phasedConfig()->knockoutTiers(PhaseKey::Final);

        $this->assertSame(160, $tiers->exactScore);
        $this->assertSame(120, $tiers->correctOutcomeAndOneTeamExact);
        $this->assertSame(80, $tiers->correctOutcome);
        $this->assertSame(40, $tiers->oneTeamExact);
    }

    public function test_knockout_tiers_default_to_a_multiplier_of_one_when_unconfigured(): void
    {
        $config = new ScoringConfig([
            'knockout' => [
                'exact_score' => 20,
                'winner_and_one_team_exact_goals' => 15,
                'correct_outcome_wrong_goals' => 10,
                'one_team_exact_goals_wrong_outcome' => 5,
            ],
        ]);

        $tiers = $config->knockoutTiers(PhaseKey::SemiFinals);

        $this->assertSame(20, $tiers->exactScore);
        $this->assertSame(5, $tiers->oneTeamExact);
    }

    public function test_advancing_bonus_is_flat_regardless_of_round(): void
    {
        $config = $this->phasedConfig();

        $this->assertSame(10, $config->knockoutAdvancingBonus());
    }

    public function test_advancing_bonus_defaults_to_zero_when_unconfigured(): void
    {
        $this->assertSame(0, (new ScoringConfig([]))->knockoutAdvancingBonus());
    }
}
