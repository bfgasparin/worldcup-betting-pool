<?php

namespace Database\Factories;

use App\Models\Entry;
use App\Models\Pool;
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
            'pool_id' => Pool::factory(),
            'user_id' => User::factory(),
            'total_points' => null,
        ];
    }
}
