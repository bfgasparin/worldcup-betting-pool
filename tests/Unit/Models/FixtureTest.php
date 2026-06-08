<?php

namespace Tests\Unit\Models;

use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\FixtureLiveState;
use App\Models\ScoreBatch;
use App\Models\ScoreProposal;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
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

    public function test_reschedule_moves_the_kickoff_venue_and_timezone(): void
    {
        $fixture = Fixture::factory()->create([
            'kicks_off_at' => CarbonImmutable::parse('2026-06-11 16:00:00'),
            'venue' => 'Atlanta Stadium',
            'venue_timezone' => 'America/New_York',
        ]);

        $newKickoff = CarbonImmutable::parse('2026-06-12 21:30:00');
        $fixture->reschedule($newKickoff, 'Los Angeles Stadium', 'America/Los_Angeles');

        $fresh = $fixture->fresh();
        $this->assertTrue($fresh->kicks_off_at->equalTo($newKickoff));
        $this->assertSame('Los Angeles Stadium', $fresh->venue);
        $this->assertSame('America/Los_Angeles', $fresh->venue_timezone);
    }

    public function test_reschedule_reverts_a_live_fixture_to_scheduled(): void
    {
        $fixture = Fixture::factory()->ended()->create();
        $this->assertSame(FixtureStatus::Live, $fixture->status);

        $fixture->reschedule(CarbonImmutable::parse('2026-06-20 18:00:00'), 'Miami Stadium', 'America/New_York');

        $this->assertSame(FixtureStatus::Scheduled, $fixture->fresh()->status);
    }

    public function test_reschedule_deletes_pending_proposals_in_an_open_batch_only(): void
    {
        $fixture = Fixture::factory()->ended()->create();

        $openProposal = ScoreProposal::factory()->create([
            'score_batch_id' => ScoreBatch::factory()->create()->id,
            'fixture_id' => $fixture->id,
        ]);
        $publishedProposal = ScoreProposal::factory()->create([
            'score_batch_id' => ScoreBatch::factory()->approved()->create()->id,
            'fixture_id' => $fixture->id,
        ]);

        $fixture->reschedule(CarbonImmutable::parse('2026-06-20 18:00:00'), 'Miami Stadium', 'America/New_York');

        $this->assertDatabaseMissing('score_proposals', ['id' => $openProposal->id]);
        $this->assertDatabaseHas('score_proposals', ['id' => $publishedProposal->id]);
        $this->assertDatabaseHas('score_batches', ['id' => $openProposal->score_batch_id]);
    }

    public function test_reschedule_clears_the_live_state(): void
    {
        $fixture = Fixture::factory()->ended()->create();
        FixtureLiveState::factory()->for($fixture)->withScore(1, 0)->create();

        $fixture->reschedule(CarbonImmutable::parse('2026-06-20 18:00:00'), 'Miami Stadium', 'America/New_York');

        $this->assertNull($fixture->fresh()->liveState);
    }

    public function test_reschedule_rejects_a_finished_fixture(): void
    {
        $fixture = Fixture::factory()->withResult()->create([
            'kicks_off_at' => CarbonImmutable::parse('2026-06-11 16:00:00'),
        ]);

        try {
            $fixture->reschedule(CarbonImmutable::parse('2026-06-20 18:00:00'), 'Miami Stadium', 'America/New_York');
            $this->fail('Expected a RuntimeException for a finished fixture.');
        } catch (RuntimeException) {
            // expected
        }

        $fresh = $fixture->fresh();
        $this->assertSame(FixtureStatus::Finished, $fresh->status);
        $this->assertTrue($fresh->kicks_off_at->equalTo(CarbonImmutable::parse('2026-06-11 16:00:00')));
    }
}
