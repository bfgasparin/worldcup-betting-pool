<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $letter = fake()->unique()->randomElement(range('A', 'L'));

        return [
            'tournament_id' => Tournament::factory(),
            'name' => $letter,
            'sort_order' => ord($letter) - ord('A') + 1,
        ];
    }
}
