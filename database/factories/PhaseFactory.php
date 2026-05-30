<?php

namespace Database\Factories;

use App\Enums\PhaseKey;
use App\Enums\PhaseType;
use App\Models\Phase;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Phase>
 */
class PhaseFactory extends Factory
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
            'key' => PhaseKey::Group,
            'type' => PhaseType::Group,
            'name' => 'Group Stage',
            'sort_order' => 1,
        ];
    }

    /**
     * Indicate that the phase is a knockout round.
     */
    public function knockout(PhaseKey $key = PhaseKey::Final, string $name = 'Final', int $sortOrder = 7): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => $key,
            'type' => PhaseType::Knockout,
            'name' => $name,
            'sort_order' => $sortOrder,
        ]);
    }
}
