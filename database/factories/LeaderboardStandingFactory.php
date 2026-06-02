<?php

namespace Database\Factories;

use App\Enums\LeaderboardCategory;
use App\Models\Entry;
use App\Models\LeaderboardStanding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaderboardStanding>
 */
class LeaderboardStandingFactory extends Factory
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
            'category' => LeaderboardCategory::Overall,
            'value' => 0,
            'tiebreaker' => 0,
        ];
    }
}
