<?php

namespace Database\Factories;

use App\Enums\EntryStatus;
use App\Models\Entry;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entry>
 */
class EntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'user_id' => User::factory(),
            'status' => EntryStatus::Draft,
            'submitted_at' => null,
            'total_points' => null,
        ];
    }

    /**
     * Indicate that the entry has been submitted.
     */
    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EntryStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }
}
