<?php

namespace Tests\Feature\Live;

use App\Enums\FixtureStatus;
use App\Enums\LiveStatus;
use App\Enums\TournamentStatus;
use App\Models\Fixture;
use App\Models\Tournament;
use App\Services\Live\GoLive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class GoLiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('scoring.go_live_buffer_minutes', 15);
    }

    public function test_marking_a_fixture_live_sets_status_and_creates_the_live_state(): void
    {
        $tournament = Tournament::factory()->create(['status' => TournamentStatus::Upcoming]);
        $fixture = Fixture::factory()->for($tournament)->create([
            'status' => FixtureStatus::Scheduled,
            'kicks_off_at' => now()->addMinutes(10),
        ]);

        $state = app(GoLive::class)->mark($fixture);

        $this->assertSame(LiveStatus::Live, $state->status);
        $this->assertNotNull($state->started_at);
        $this->assertSame(FixtureStatus::Live, $fixture->fresh()->status);
        $this->assertSame(LiveStatus::Live, $fixture->fresh()->liveState->status);

        // Official result columns are never touched.
        $this->assertNull($fixture->fresh()->home_goals);
        $this->assertNull($fixture->fresh()->away_goals);

        // The tournament lifecycle advances.
        $this->assertSame(TournamentStatus::InProgress, $tournament->fresh()->status);
    }

    public function test_marking_live_is_rejected_before_the_buffer_opens(): void
    {
        $fixture = Fixture::factory()->create([
            'status' => FixtureStatus::Scheduled,
            'kicks_off_at' => now()->addMinutes(30),
        ]);

        try {
            app(GoLive::class)->mark($fixture);
            $this->fail('Expected an HttpException for an ineligible fixture.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame(FixtureStatus::Scheduled, $fixture->fresh()->status);
        $this->assertNull($fixture->fresh()->liveState);
    }

    public function test_force_ignores_eligibility_for_tooling(): void
    {
        $fixture = Fixture::factory()->create([
            'status' => FixtureStatus::Scheduled,
            'kicks_off_at' => now()->addDays(5),
        ]);

        $state = app(GoLive::class)->force($fixture);

        $this->assertSame(LiveStatus::Live, $state->status);
        $this->assertSame(FixtureStatus::Live, $fixture->fresh()->status);
    }
}
