<?php

namespace Database\Factories;

use App\Enums\LiveStatus;
use App\Models\Fixture;
use App\Models\FixtureLiveState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FixtureLiveState>
 */
class FixtureLiveStateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fixture_id' => Fixture::factory(),
            'status' => LiveStatus::Live,
            'home_goals' => null,
            'away_goals' => null,
            'started_at' => now(),
            'ended_at' => null,
        ];
    }

    /**
     * Indicate that the fixture has not been marked live yet.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LiveStatus::Scheduled,
            'started_at' => null,
        ]);
    }

    /**
     * Indicate that the admin has ended the live match (final score handed to the proposal flow).
     */
    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LiveStatus::Ended,
            'ended_at' => now(),
        ]);
    }

    /**
     * Set the live scoreline.
     */
    public function withScore(int $home, int $away): static
    {
        return $this->state(fn (array $attributes) => [
            'home_goals' => $home,
            'away_goals' => $away,
        ]);
    }
}
