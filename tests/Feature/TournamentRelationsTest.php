<?php

namespace Tests\Feature;

use App\Enums\FeederOutcome;
use App\Enums\PhaseType;
use App\Enums\Sport;
use App\Enums\TournamentStatus;
use App\Models\Entry;
use App\Models\Fixture;
use App\Models\Group;
use App\Models\GroupPrediction;
use App\Models\Pool;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tournament_casts_resolve_to_enums(): void
    {
        $tournament = Tournament::factory()->create();

        $this->assertSame(Sport::Soccer, $tournament->sport);
        $this->assertSame(TournamentStatus::Upcoming, $tournament->status);
    }

    public function test_group_team_pivot_carries_position(): void
    {
        $group = Group::factory()->create();
        $team = Team::factory()->create();

        $group->teams()->attach($team, ['position' => 3]);

        $this->assertSame(3, $group->teams()->first()->pivot->position);
    }

    public function test_fixture_feeder_self_relationship_resolves(): void
    {
        $feeder = Fixture::factory()->knockout()->create();
        $fixture = Fixture::factory()->knockout()->create([
            'home_feeder_fixture_id' => $feeder->id,
            'home_feeder_outcome' => FeederOutcome::Winner,
        ]);

        $this->assertTrue($fixture->homeFeeder->is($feeder));
        $this->assertSame(FeederOutcome::Winner, $fixture->home_feeder_outcome);
    }

    public function test_fixture_phase_type_helpers(): void
    {
        $tournament = Tournament::factory()->create();
        $groupPhase = $tournament->phases()->create([
            'key' => 'group', 'type' => PhaseType::Group->value, 'name' => 'Group Stage', 'sort_order' => 1,
        ]);

        $fixture = Fixture::factory()->for($tournament)->create(['phase_id' => $groupPhase->id]);

        $this->assertTrue($fixture->isGroup());
        $this->assertFalse($fixture->isKnockout());
    }

    public function test_entry_has_group_predictions(): void
    {
        $entry = Entry::factory()->create();
        GroupPrediction::factory()->count(2)->for($entry)->create();

        $this->assertCount(2, $entry->groupPredictions);
    }

    public function test_tournament_has_pools(): void
    {
        $tournament = Tournament::factory()->create();
        Pool::factory()->count(2)->for($tournament)->create();

        $this->assertCount(2, $tournament->pools);
    }
}
