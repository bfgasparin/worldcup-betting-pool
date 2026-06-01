<?php

namespace Database\Factories;

use App\Enums\ProposalStatus;
use App\Models\Fixture;
use App\Models\ScoreBatch;
use App\Models\ScoreProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScoreProposal>
 */
class ScoreProposalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'score_batch_id' => ScoreBatch::factory(),
            'fixture_id' => Fixture::factory(),
            'home_goals' => fake()->numberBetween(0, 4),
            'away_goals' => fake()->numberBetween(0, 4),
            'winner_team_id' => null,
            'home_penalties' => null,
            'away_penalties' => null,
            'status' => ProposalStatus::Pending,
        ];
    }
}
