<?php

namespace Tests\Feature;

use App\Enums\FeederOutcome;
use App\Enums\PhaseType;
use App\Enums\ScoringStrategy;
use App\Enums\Sport;
use App\Enums\TournamentStatus;
use App\Models\Entry;
use App\Models\Fixture;
use App\Models\Group;
use App\Models\GroupPrediction;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tournament_casts_resolve_to_enums_and_array(): void
    {
        $tournament = Tournament::factory()->create();

        $this->assertSame(Sport::Soccer, $tournament->sport);
        $this->assertSame(ScoringStrategy::WorldCupStandard, $tournament->scoring_strategy);
        $this->assertIsArray($tournament->scoring_config);
        $this->assertSame(20, $tournament->scoring_config['group']['exact_score']);
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

    public function test_accepts_predictions_when_open_and_before_the_lock_time(): void
    {
        $tournament = Tournament::factory()->create([
            'status' => TournamentStatus::Open,
            'predictions_lock_at' => now()->addDay(),
        ]);

        $this->assertTrue($tournament->acceptsPredictions());
    }

    public function test_does_not_accept_predictions_after_the_lock_time(): void
    {
        $tournament = Tournament::factory()->create([
            'status' => TournamentStatus::Open,
            'predictions_lock_at' => now()->subMinute(),
        ]);

        $this->assertFalse($tournament->acceptsPredictions());
    }

    public function test_does_not_accept_predictions_once_the_tournament_has_progressed(): void
    {
        foreach ([TournamentStatus::Locked, TournamentStatus::InProgress, TournamentStatus::Completed] as $status) {
            $tournament = Tournament::factory()->create([
                'status' => $status,
                'predictions_lock_at' => now()->addWeek(),
            ]);

            $this->assertFalse($tournament->acceptsPredictions());
        }
    }
}
