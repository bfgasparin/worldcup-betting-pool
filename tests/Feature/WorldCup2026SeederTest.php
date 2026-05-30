<?php

namespace Tests\Feature;

use App\Enums\FeederOutcome;
use App\Enums\PhaseType;
use App\Models\Fixture;
use App\Models\Group;
use App\Models\Team;
use App\Models\Tournament;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorldCup2026SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_the_full_world_cup_2026_structure(): void
    {
        $this->seed(WorldCup2026Seeder::class);

        $tournament = Tournament::where('slug', 'world-cup-2026')->firstOrFail();

        $this->assertSame('World Cup 2026', $tournament->name);
        $this->assertSame(7, $tournament->phases()->count());
        $this->assertSame(12, $tournament->groups()->count());
        $this->assertSame(48, Team::count());
        $this->assertSame(104, $tournament->fixtures()->count());
        $this->assertSame(72, $tournament->groupFixtures()->count());
        $this->assertSame(32, $tournament->knockoutFixtures()->count());
    }

    public function test_every_group_has_exactly_four_teams(): void
    {
        $this->seed(WorldCup2026Seeder::class);

        $groups = Group::with('teams')->get();

        $this->assertCount(12, $groups);

        foreach ($groups as $group) {
            $this->assertCount(4, $group->teams, "Group {$group->name} should have 4 teams.");
        }
    }

    public function test_it_creates_the_expected_knockout_bracket_counts(): void
    {
        $this->seed(WorldCup2026Seeder::class);

        $this->assertSame(16, Fixture::where('bracket_slot', 'like', 'R32-%')->count());
        $this->assertSame(8, Fixture::where('bracket_slot', 'like', 'R16-%')->count());
        $this->assertSame(4, Fixture::where('bracket_slot', 'like', 'QF-%')->count());
        $this->assertSame(2, Fixture::where('bracket_slot', 'like', 'SF-%')->count());
        $this->assertSame(1, Fixture::where('bracket_slot', 'TP')->count());
        $this->assertSame(1, Fixture::where('bracket_slot', 'F')->count());
    }

    public function test_round_of_32_slots_are_unresolved_but_labelled(): void
    {
        $this->seed(WorldCup2026Seeder::class);

        $r32 = Fixture::where('bracket_slot', 'like', 'R32-%')->get();

        foreach ($r32 as $fixture) {
            $this->assertNull($fixture->home_team_id);
            $this->assertNull($fixture->away_team_id);
            $this->assertNull($fixture->home_feeder_fixture_id);
            $this->assertNotNull($fixture->home_placeholder_label);
            $this->assertNotNull($fixture->away_placeholder_label);
        }
    }

    public function test_later_knockout_rounds_have_resolvable_feeders(): void
    {
        $this->seed(WorldCup2026Seeder::class);

        $final = Fixture::where('bracket_slot', 'F')->firstOrFail();
        $this->assertSame('SF-1', $final->homeFeeder->bracket_slot);
        $this->assertSame('SF-2', $final->awayFeeder->bracket_slot);
        $this->assertSame(FeederOutcome::Winner, $final->home_feeder_outcome);

        $thirdPlace = Fixture::where('bracket_slot', 'TP')->firstOrFail();
        $this->assertSame(FeederOutcome::Loser, $thirdPlace->home_feeder_outcome);
        $this->assertSame(FeederOutcome::Loser, $thirdPlace->away_feeder_outcome);
        $this->assertSame(PhaseType::Knockout, $thirdPlace->phase->type);
    }

    public function test_it_seeds_placeholder_and_real_teams(): void
    {
        $this->seed(WorldCup2026Seeder::class);

        $this->assertSame(2, Team::where('is_placeholder', true)->count());

        $brazil = Team::where('code', 'BRA')->firstOrFail();
        $this->assertFalse($brazil->is_placeholder);
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $this->seed(WorldCup2026Seeder::class);

        $this->assertSame(1, Tournament::count());
        $this->assertSame(48, Team::count());
        $this->assertSame(104, Fixture::count());
        $this->assertSame(48, \DB::table('group_team')->count());
    }
}
