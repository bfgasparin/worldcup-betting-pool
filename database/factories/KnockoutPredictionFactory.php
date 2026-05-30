<?php

namespace Database\Factories;

use App\Models\Entry;
use App\Models\Fixture;
use App\Models\KnockoutPrediction;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnockoutPrediction>
 */
class KnockoutPredictionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $home = Team::factory();
        $away = Team::factory();

        return [
            'entry_id' => Entry::factory(),
            'fixture_id' => Fixture::factory()->knockout(),
            'predicted_home_team_id' => $home,
            'predicted_away_team_id' => $away,
            'home_goals' => fake()->numberBetween(0, 4),
            'away_goals' => fake()->numberBetween(0, 4),
            'advancing_team_id' => $home,
            'points_awarded' => null,
        ];
    }
}
