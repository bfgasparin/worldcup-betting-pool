<?php

namespace Tests\Unit\Models;

use App\Enums\FixtureStatus;
use App\Enums\LiveStatus;
use App\Models\Fixture;
use App\Models\FixtureLiveState;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixtureLiveStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('scoring.go_live_buffer_minutes', 15);
    }

    public function test_it_casts_attributes(): void
    {
        $state = FixtureLiveState::factory()->create([
            'status' => LiveStatus::Live,
            'home_goals' => 2,
            'away_goals' => 1,
            'started_at' => now(),
        ]);

        $fresh = $state->fresh();
        $this->assertInstanceOf(LiveStatus::class, $fresh->status);
        $this->assertSame(LiveStatus::Live, $fresh->status);
        $this->assertSame(2, $fresh->home_goals);
        $this->assertSame(1, $fresh->away_goals);
        $this->assertInstanceOf(CarbonImmutable::class, $fresh->started_at);
    }

    public function test_it_belongs_to_a_fixture(): void
    {
        $fixture = Fixture::factory()->create();
        $state = FixtureLiveState::factory()->for($fixture)->create();

        $this->assertTrue($state->fixture->is($fixture));
    }

    public function test_is_live_and_is_ended_reflect_status(): void
    {
        $this->assertTrue((new FixtureLiveState(['status' => LiveStatus::Live]))->isLive());
        $this->assertFalse((new FixtureLiveState(['status' => LiveStatus::Ended]))->isLive());

        $this->assertTrue((new FixtureLiveState(['status' => LiveStatus::Ended]))->isEnded());
        $this->assertFalse((new FixtureLiveState(['status' => LiveStatus::Live]))->isEnded());
    }

    public function test_fixture_exposes_its_live_state(): void
    {
        $fixture = Fixture::factory()->create();
        $state = FixtureLiveState::factory()->for($fixture)->create();

        $this->assertTrue($fixture->fresh()->liveState->is($state));
    }

    public function test_can_go_live_only_within_the_buffer_or_after_kickoff(): void
    {
        $tooEarly = new Fixture([
            'status' => FixtureStatus::Scheduled,
            'kicks_off_at' => now()->addMinutes(30),
        ]);
        $this->assertFalse($tooEarly->canGoLive(), 'A match 30m out (buffer 15m) cannot go live yet.');

        $withinBuffer = new Fixture([
            'status' => FixtureStatus::Scheduled,
            'kicks_off_at' => now()->addMinutes(10),
        ]);
        $this->assertTrue($withinBuffer->canGoLive(), 'A match 10m out is within the 15m buffer.');

        $kickedOff = new Fixture([
            'status' => FixtureStatus::Scheduled,
            'kicks_off_at' => now()->subMinutes(5),
        ]);
        $this->assertTrue($kickedOff->canGoLive(), 'A match past kickoff can go live.');
    }

    public function test_can_go_live_is_false_for_non_scheduled_or_undated_fixtures(): void
    {
        $alreadyLive = new Fixture([
            'status' => FixtureStatus::Live,
            'kicks_off_at' => now()->subMinutes(5),
        ]);
        $this->assertFalse($alreadyLive->canGoLive());

        $finished = new Fixture([
            'status' => FixtureStatus::Finished,
            'kicks_off_at' => now()->subMinutes(5),
        ]);
        $this->assertFalse($finished->canGoLive());

        $undated = new Fixture([
            'status' => FixtureStatus::Scheduled,
            'kicks_off_at' => null,
        ]);
        $this->assertFalse($undated->canGoLive());
    }

    public function test_has_live_state_scope_returns_only_fixtures_currently_live(): void
    {
        $live = Fixture::factory()->create();
        FixtureLiveState::factory()->for($live)->create(['status' => LiveStatus::Live]);

        $ended = Fixture::factory()->create();
        FixtureLiveState::factory()->for($ended)->create(['status' => LiveStatus::Ended]);

        Fixture::factory()->create(); // no live state at all

        $ids = Fixture::query()->hasLiveState()->pluck('id');

        $this->assertEquals([$live->id], $ids->all());
    }
}
