<?php

namespace Database\Factories;

use App\Enums\ScoringStrategy;
use App\Enums\Sport;
use App\Enums\TournamentStatus;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tournament>
 */
class TournamentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true).' Cup';

        return [
            'slug' => Str::slug($name),
            'name' => Str::title($name),
            'sport' => Sport::Soccer,
            'status' => TournamentStatus::Upcoming,
            'scoring_strategy' => ScoringStrategy::WorldCupStandard,
            'scoring_config' => [
                'group' => [
                    'exact_score' => 20,
                    'winner_and_one_team_exact_goals' => 15,
                    'correct_outcome_wrong_goals' => 10,
                    'one_team_exact_goals_wrong_outcome' => 5,
                ],
                'knockout' => [
                    'correct_team' => 10,
                    'team_goal_count_bonus' => 5,
                    'champion' => 30,
                ],
            ],
            'predictions_lock_at' => fake()->dateTimeBetween('+1 week', '+1 month'),
            'starts_on' => '2026-06-11',
            'ends_on' => '2026-07-19',
        ];
    }

    /**
     * A tournament that is currently underway.
     */
    public function inProgress(): static
    {
        return $this->state(['status' => TournamentStatus::InProgress]);
    }

    /**
     * A tournament that has finished.
     */
    public function completed(): static
    {
        return $this->state(['status' => TournamentStatus::Completed]);
    }
}
