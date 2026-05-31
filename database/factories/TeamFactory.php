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
            // A synthetic 3-char code with a digit in the middle (e.g. "A7Q"). Real team codes
            // are all-letter ISO codes, so a digit guarantees this never collides with a seeded
            // code on the shared unique `code` column — fake()->unique() only dedupes within the
            // Faker instance, not against rows the seeder already inserted.
            'code' => Str::upper(fake()->unique()->bothify('?#?')),
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
