<?php

namespace Tests\Feature;

use App\Models\Tournament;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenumberGroupFixturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_maps_group_fixtures_between_legacy_and_fifa_numbering(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $tournament = Tournament::firstOrFail();

        $migration = require database_path('migrations/2026_06_12_000000_renumber_world_cup_2026_group_fixtures_to_fifa_order.php');

        $number = fn (string $home, string $away): int => $tournament->groupFixtures()
            ->whereHas('homeTeam', fn ($query) => $query->where('code', $home))
            ->whereHas('awayTeam', fn ($query) => $query->where('code', $away))
            ->firstOrFail()->match_number;

        // The seeder already produces FIFA chronological numbers.
        $this->assertSame(1, $number('MEX', 'RSA'));   // the opener
        $this->assertSame(3, $number('CAN', 'BIH'));   // was 7 under group-by-group
        $this->assertSame(63, $number('CPV', 'KSA'));  // a resolved simultaneous pair

        // down() restores the original group-by-group numbering (A = 1–6, B = 7–12, …).
        $migration->down();
        $this->assertSame(1, $number('MEX', 'RSA'));
        $this->assertSame(7, $number('CAN', 'BIH'));
        $this->assertSame(48, $number('CPV', 'KSA'));
        // Knockout fixtures (73–104) are never touched.
        $this->assertSame(32, $tournament->knockoutFixtures()->whereBetween('match_number', [73, 104])->count());

        // up() re-applies FIFA chronological numbering with no unique-index collisions.
        $migration->up();
        $this->assertSame(1, $number('MEX', 'RSA'));
        $this->assertSame(3, $number('CAN', 'BIH'));
        $this->assertSame(63, $number('CPV', 'KSA'));
        $this->assertSame(72, $tournament->groupFixtures()->distinct()->count('match_number'));
        $this->assertSame(32, $tournament->knockoutFixtures()->whereBetween('match_number', [73, 104])->count());

        // Re-running up() is a no-op (stable under re-run, keyed off team identity).
        $migration->up();
        $this->assertSame(3, $number('CAN', 'BIH'));
    }

    public function test_migration_is_a_no_op_without_the_seeded_tournament(): void
    {
        $migration = require database_path('migrations/2026_06_12_000000_renumber_world_cup_2026_group_fixtures_to_fifa_order.php');

        // No tournament present (fresh DB before seeding): must not throw.
        $migration->up();

        $this->assertSame(0, Tournament::count());
    }
}
