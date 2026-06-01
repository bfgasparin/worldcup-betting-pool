<?php

namespace Tests\Unit\Models;

use App\Enums\FixtureStatus;
use App\Models\Fixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixtureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('scoring.match_duration_minutes', 150);
    }

    public function test_has_kicked_off_reflects_the_kickoff_time(): void
    {
        $this->assertTrue((new Fixture(['kicks_off_at' => now()->subMinute()]))->hasKickedOff());
        $this->assertFalse((new Fixture(['kicks_off_at' => now()->addMinute()]))->hasKickedOff());
        $this->assertFalse((new Fixture(['kicks_off_at' => null]))->hasKickedOff());
    }

    public function test_a_scheduled_fixture_has_not_ended_even_long_after_kickoff(): void
    {
        $fixture = new Fixture([
            'status' => FixtureStatus::Scheduled,
            'kicks_off_at' => now()->subDay(),
        ]);

        $this->assertFalse($fixture->hasEnded());
    }

    public function test_a_live_fixture_has_not_ended_before_full_time(): void
    {
        $fixture = new Fixture([
            'status' => FixtureStatus::Live,
            'kicks_off_at' => now()->subMinutes(149),
        ]);

        $this->assertFalse($fixture->hasEnded());
    }

    public function test_a_live_fixture_has_ended_once_past_full_time(): void
    {
        $fixture = new Fixture([
            'status' => FixtureStatus::Live,
            'kicks_off_at' => now()->subMinutes(151),
        ]);

        $this->assertTrue($fixture->hasEnded());
    }

    public function test_a_finished_fixture_is_not_considered_ended(): void
    {
        $fixture = new Fixture([
            'status' => FixtureStatus::Finished,
            'kicks_off_at' => now()->subDay(),
        ]);

        $this->assertFalse($fixture->hasEnded());
    }

    public function test_a_fixture_without_a_kickoff_has_not_ended(): void
    {
        $fixture = new Fixture([
            'status' => FixtureStatus::Live,
            'kicks_off_at' => null,
        ]);

        $this->assertFalse($fixture->hasEnded());
    }

    public function test_scope_ended_returns_only_ended_fixtures(): void
    {
        $ended = Fixture::factory()->ended()->create();
        Fixture::factory()->create([
            'status' => FixtureStatus::Live,
            'kicks_off_at' => now()->subMinutes(10),
        ]);
        Fixture::factory()->create([
            'status' => FixtureStatus::Scheduled,
            'kicks_off_at' => now()->subDay(),
        ]);

        $ids = Fixture::ended()->pluck('id');

        $this->assertEquals([$ended->id], $ids->all());
    }

    public function test_penalties_are_fillable(): void
    {
        $fixture = Fixture::factory()->create();

        $fixture->update(['home_penalties' => 4, 'away_penalties' => 2]);

        $fresh = $fixture->fresh();
        $this->assertSame(4, $fresh->home_penalties);
        $this->assertSame(2, $fresh->away_penalties);
    }
}
