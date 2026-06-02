<?php

namespace Database\Factories;

use App\Enums\ScoringStrategy;
use App\Models\Game;
use App\Models\Tournament;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The scoring_config below is duplicated verbatim in {@see WorldCup2026Seeder} —
     * keep them in sync.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true).' Cup';

        return [
            'tournament_id' => Tournament::factory(),
            'slug' => Str::slug($name),
            'name' => Str::title($name),
            'source' => fake()->company(),
            'scoring_strategy' => ScoringStrategy::UpfrontBracket,
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
        ];
    }

    /**
     * The phased-bracket strategy: the group stage is predicted upfront, each knockout round is
     * predicted against the official match-ups once known, and scores carry rising round
     * multipliers. The scoring_config below is duplicated verbatim in {@see WorldCup2026Seeder}
     * for the Brothers Association game — keep them in sync.
     */
    public function phasedBracket(): static
    {
        return $this->state(fn (): array => [
            'scoring_strategy' => ScoringStrategy::PhasedBracket,
            'scoring_config' => [
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
            ],
        ]);
    }
}
