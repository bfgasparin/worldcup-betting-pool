<?php

namespace Database\Factories;

use App\Enums\BatchStatus;
use App\Models\ScoreBatch;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScoreBatch>
 */
class ScoreBatchFactory extends Factory
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
            'status' => BatchStatus::Open,
            'source' => 'manual',
            'fetched_at' => now(),
            'approved_at' => null,
            'approved_by' => null,
        ];
    }

    /**
     * A batch that has already been approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BatchStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}
