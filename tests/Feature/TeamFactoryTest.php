<?php

namespace Tests\Feature;

use App\Models\Team;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_codes_never_collide_with_seeded_codes(): void
    {
        // The seeder fills the unique `code` column with 48 real all-letter ISO codes.
        $this->seed(WorldCup2026Seeder::class);

        // Creating many factory teams alongside them must never hit the unique constraint.
        $teams = Team::factory()->count(40)->create();

        $this->assertSame(40, $teams->pluck('code')->unique()->count());

        // Each factory code carries a digit, so it cannot match an all-letter seeded code.
        foreach ($teams as $team) {
            $this->assertMatchesRegularExpression('/\d/', (string) $team->code);
        }

        $this->assertSame(88, Team::count());
    }
}
