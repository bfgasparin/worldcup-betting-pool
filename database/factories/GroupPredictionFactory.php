<?php

namespace Database\Factories;

use App\Models\Entry;
use App\Models\Fixture;
use App\Models\GroupPrediction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupPrediction>
 */
class GroupPredictionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entry_id' => Entry::factory(),
            'fixture_id' => Fixture::factory(),
            'home_goals' => fake()->numberBetween(0, 5),
            'away_goals' => fake()->numberBetween(0, 5),
            'points_awarded' => null,
        ];
    }
}
