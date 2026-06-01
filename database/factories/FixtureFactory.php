<?php

namespace Database\Factories;

use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Group;
use App\Models\Phase;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fixture>
 */
class FixtureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tournament = Tournament::factory();

        return [
            'tournament_id' => $tournament,
            'phase_id' => Phase::factory()->for($tournament),
            'group_id' => Group::factory()->for($tournament),
            'match_number' => fake()->unique()->numberBetween(1, 104),
            'bracket_slot' => null,
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'home_placeholder_label' => null,
            'away_placeholder_label' => null,
            'kicks_off_at' => fake()->dateTimeBetween('2026-06-11', '2026-07-19'),
            'status' => FixtureStatus::Scheduled,
        ];
    }

    /**
     * Indicate that the fixture is a knockout bracket slot with no teams resolved yet.
     */
    public function knockout(): static
    {
        return $this->state(fn (array $attributes) => [
            'group_id' => null,
            'bracket_slot' => 'R32-'.fake()->unique()->numberBetween(1, 16),
            'home_team_id' => null,
            'away_team_id' => null,
            'home_placeholder_label' => 'Winner Group A',
            'away_placeholder_label' => 'Runner-up Group B',
        ]);
    }

    /**
     * Indicate that the fixture has a final result recorded.
     */
    public function withResult(int $homeGoals = 1, int $awayGoals = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'home_goals' => $homeGoals,
            'away_goals' => $awayGoals,
            'status' => FixtureStatus::Finished,
        ]);
    }

    /**
     * Indicate that the match is over (live and past full time) and awaiting an official score.
     */
    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FixtureStatus::Live,
            'kicks_off_at' => now()->subMinutes((int) config('scoring.match_duration_minutes') + 1),
        ]);
    }
}
