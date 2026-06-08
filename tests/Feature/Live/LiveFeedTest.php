<?php

namespace Tests\Feature\Live;

use App\Enums\FixtureStatus;
use App\Enums\LiveStatus;
use App\Models\Fixture;
use App\Models\FixtureLiveState;
use App\Models\ScoreProposal;
use App\Models\Tournament;
use App\Services\Live\LiveFeed;
use App\Services\Scoring\Providers\SimulatedScoreProvider;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveFeedTest extends TestCase
{
    use RefreshDatabase;

    private Tournament $tournament;

    protected function setUp(): void
    {
        parent::setUp();

        // The feed drives scores through the simulated provider (the local stand-in for a real API).
        config(['scoring.simulated_provider' => true]);

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
    }

    public function test_a_due_scheduled_fixture_is_taken_live_and_gets_an_initial_tick(): void
    {
        $fixture = $this->groupFixture(1);
        $fixture->update(['kicks_off_at' => now()]); // due now, still scheduled

        app(LiveFeed::class)->advance($this->tournament);

        $fixture->refresh();
        $this->assertSame(FixtureStatus::Live, $fixture->status);
        $this->assertSame(LiveStatus::Live, $fixture->liveState->status);
        // Ticked at kickoff → 0–0 written (not left null), and no official result.
        $this->assertSame(0, $fixture->liveState->home_goals);
        $this->assertSame(0, $fixture->liveState->away_goals);
        $this->assertNull($fixture->home_goals);
    }

    public function test_a_knockout_with_unresolved_teams_is_not_taken_live(): void
    {
        $ko = $this->tournament->knockoutFixtures()->orderBy('match_number')->firstOrFail();
        $this->assertNull($ko->home_team_id);
        $ko->update(['kicks_off_at' => now()]);

        app(LiveFeed::class)->advance($this->tournament);

        $ko->refresh();
        $this->assertSame(FixtureStatus::Scheduled, $ko->status);
        $this->assertNull($ko->liveState);
    }

    public function test_a_live_fixture_past_full_time_ticks_to_its_final_then_closes(): void
    {
        $fixture = $this->groupFixture(1);
        $fixture->update(['status' => FixtureStatus::Live, 'kicks_off_at' => now()->subMinutes(151)]);
        FixtureLiveState::factory()->for($fixture)->create(['status' => LiveStatus::Live]);

        $expected = collect((new SimulatedScoreProvider)->live($this->tournament))->firstWhere('matchNumber', 1);

        app(LiveFeed::class)->advance($this->tournament);

        $state = $fixture->fresh()->liveState;
        $this->assertSame(LiveStatus::Ended, $state->status);
        $this->assertNotNull($state->ended_at);
        // Ticked to the converged regulation final before the board closed.
        $this->assertSame($expected->homeGoals, $state->home_goals);
        $this->assertSame($expected->awayGoals, $state->away_goals);

        // The feed proposes nothing and writes no official result (that's scores:fetch + approval).
        $this->assertSame(0, ScoreProposal::count());
        $this->assertNull($fixture->fresh()->home_goals);
    }

    private function groupFixture(int $matchNumber): Fixture
    {
        return $this->tournament->groupFixtures()->where('match_number', $matchNumber)->firstOrFail();
    }
}
