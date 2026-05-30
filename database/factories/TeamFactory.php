<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->country(),
            'code' => Str::upper(fake()->unique()->lexify('???')),
            'is_placeholder' => false,
        ];
    }

    /**
     * Indicate that the team is a placeholder for an unknown qualifier.
     */
    public function placeholder(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->unique()->words(2, true),
            'code' => null,
            'is_placeholder' => true,
        ]);
    }
}
