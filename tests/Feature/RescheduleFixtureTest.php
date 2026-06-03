<?php

namespace Tests\Feature;

use App\Enums\FixtureStatus;
use App\Enums\TournamentStatus;
use App\Models\Fixture;
use App\Models\Game;
use App\Models\ScoreBatch;
use App\Models\ScoreProposal;
use App\Models\Tournament;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOfficialResults;
use Tests\TestCase;

class RescheduleFixtureTest extends TestCase
{
    use InteractsWithOfficialResults;
    use RefreshDatabase;

    private Tournament $tournament;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
        $this->game = $this->tournament->games()->where('slug', 'world-cup-2026-ffa')->firstOrFail();

        // Freeze the clock to just before the seeded schedule so reschedules to mid-2026 are future.
        $this->travelTo(CarbonImmutable::parse('2026-06-03 12:00:00'));

        // The admin enters kickoffs in their browser timezone, shared via the `timezone` cookie.
        $this->withUnencryptedCookie('timezone', 'America/New_York');
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        config()->set('admin.emails', [$admin->email]);

        return $admin;
    }

    private function groupFixture(): Fixture
    {
        return $this->tournament->groupFixtures()->orderBy('match_number')->firstOrFail();
    }

    public function test_an_admin_reschedules_a_scheduled_fixture_to_a_new_time_and_venue(): void
    {
        $fixture = $this->groupFixture();

        $this->actingAs($this->admin())
            ->patch(route('games.fixtures.reschedule', [$this->game, $fixture]), [
                'kicks_off_at' => '2026-07-01T21:30',
                'venue' => 'Los Angeles Stadium',
            ])
            ->assertRedirect(route('games.schedule.index', $this->game));

        $fresh = $fixture->fresh();
        // The time is read in the admin's timezone (New York); the venue still carries its own zone.
        $this->assertTrue($fresh->kicks_off_at->equalTo(CarbonImmutable::parse('2026-07-01 21:30:00', 'America/New_York')));
        $this->assertSame('Los Angeles Stadium', $fresh->venue);
        $this->assertSame('America/Los_Angeles', $fresh->venue_timezone);
        $this->assertSame(FixtureStatus::Scheduled, $fresh->status);
    }

    public function test_the_kickoff_is_read_in_the_admins_timezone_not_the_venue(): void
    {
        $fixture = $this->groupFixture();

        // Admin's browser zone is São Paulo while the venue is in Los Angeles: the stored instant
        // must reflect São Paulo, proving the venue's zone never drives the kickoff.
        $this->actingAs($this->admin())
            ->withUnencryptedCookie('timezone', 'America/Sao_Paulo')
            ->patch(route('games.fixtures.reschedule', [$this->game, $fixture]), [
                'kicks_off_at' => '2026-07-01T18:00',
                'venue' => 'Los Angeles Stadium',
            ])
            ->assertRedirect(route('games.schedule.index', $this->game));

        $this->assertTrue(
            $fixture->fresh()->kicks_off_at->equalTo(
                CarbonImmutable::parse('2026-07-01 18:00:00', 'America/Sao_Paulo'),
            ),
        );
    }

    public function test_rescheduling_a_live_fixture_reverts_it_to_scheduled(): void
    {
        $fixture = $this->markEnded($this->groupFixture());
        $this->assertSame(FixtureStatus::Live, $fixture->status);

        $this->actingAs($this->admin())
            ->patch(route('games.fixtures.reschedule', [$this->game, $fixture]), [
                'kicks_off_at' => '2026-07-01T21:30',
                'venue' => 'Miami Stadium',
            ])
            ->assertRedirect(route('games.schedule.index', $this->game));

        $this->assertSame(FixtureStatus::Scheduled, $fixture->fresh()->status);
    }

    public function test_rescheduling_the_only_live_fixture_reverts_the_tournament_to_upcoming(): void
    {
        $fixture = $this->markEnded($this->groupFixture());
        $this->tournament->syncStatus();
        $this->assertSame(TournamentStatus::InProgress, $this->tournament->fresh()->status);

        $this->actingAs($this->admin())
            ->patch(route('games.fixtures.reschedule', [$this->game, $fixture]), [
                'kicks_off_at' => '2026-07-01T21:30',
                'venue' => 'Miami Stadium',
            ])
            ->assertRedirect(route('games.schedule.index', $this->game));

        // Nothing is live or played anymore, so the tournament is no longer underway.
        $this->assertSame(TournamentStatus::Upcoming, $this->tournament->fresh()->status);
    }

    public function test_rescheduling_a_live_fixture_deletes_its_pending_open_batch_proposal(): void
    {
        $fixture = $this->markEnded($this->groupFixture());
        $batch = ScoreBatch::factory()->for($this->tournament)->create();
        $proposal = ScoreProposal::factory()->create([
            'score_batch_id' => $batch->id,
            'fixture_id' => $fixture->id,
        ]);

        $this->actingAs($this->admin())
            ->patch(route('games.fixtures.reschedule', [$this->game, $fixture]), [
                'kicks_off_at' => '2026-07-01T21:30',
                'venue' => 'Miami Stadium',
            ])
            ->assertRedirect(route('games.schedule.index', $this->game));

        $this->assertDatabaseMissing('score_proposals', ['id' => $proposal->id]);
        // Only the proposal is removed; the shared open batch survives for other fixtures.
        $this->assertDatabaseHas('score_batches', ['id' => $batch->id]);
    }

    public function test_a_finished_fixture_cannot_be_rescheduled(): void
    {
        $fixture = $this->groupFixture();
        $originalKickoff = $fixture->kicks_off_at;
        $fixture->update(['status' => FixtureStatus::Finished]);

        $this->actingAs($this->admin())
            ->patch(route('games.fixtures.reschedule', [$this->game, $fixture]), [
                'kicks_off_at' => '2026-07-01T21:30',
                'venue' => 'Miami Stadium',
            ])
            ->assertSessionHasErrors('kicks_off_at');

        $fresh = $fixture->fresh();
        $this->assertSame(FixtureStatus::Finished, $fresh->status);
        $this->assertTrue($fresh->kicks_off_at->equalTo($originalKickoff));
    }

    public function test_a_kickoff_in_the_past_is_rejected(): void
    {
        $fixture = $this->groupFixture();

        $this->actingAs($this->admin())
            ->patch(route('games.fixtures.reschedule', [$this->game, $fixture]), [
                'kicks_off_at' => '2020-01-01T12:00',
                'venue' => 'Miami Stadium',
            ])
            ->assertSessionHasErrors('kicks_off_at');
    }

    public function test_a_venue_outside_the_existing_list_is_rejected(): void
    {
        $fixture = $this->groupFixture();

        $this->actingAs($this->admin())
            ->patch(route('games.fixtures.reschedule', [$this->game, $fixture]), [
                'kicks_off_at' => '2026-07-01T21:30',
                'venue' => 'Wembley Stadium',
            ])
            ->assertSessionHasErrors('venue');
    }

    public function test_a_missing_kickoff_is_rejected(): void
    {
        $fixture = $this->groupFixture();

        $this->actingAs($this->admin())
            ->patch(route('games.fixtures.reschedule', [$this->game, $fixture]), [
                'venue' => 'Miami Stadium',
            ])
            ->assertSessionHasErrors('kicks_off_at');
    }

    public function test_a_non_admin_is_forbidden(): void
    {
        $fixture = $this->groupFixture();

        $this->actingAs(User::factory()->create())
            ->patch(route('games.fixtures.reschedule', [$this->game, $fixture]), [
                'kicks_off_at' => '2026-07-01T21:30',
                'venue' => 'Miami Stadium',
            ])
            ->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $fixture = $this->groupFixture();

        $this->patch(route('games.fixtures.reschedule', [$this->game, $fixture]), [
            'kicks_off_at' => '2026-07-01T21:30',
            'venue' => 'Miami Stadium',
        ])->assertRedirect(route('login'));
    }

    public function test_a_fixture_from_another_tournament_is_not_found(): void
    {
        $otherFixture = Fixture::factory()->create();

        $this->actingAs($this->admin())
            ->patch(route('games.fixtures.reschedule', [$this->game, $otherFixture]), [
                'kicks_off_at' => '2026-07-01T21:30',
                'venue' => 'Miami Stadium',
            ])
            ->assertNotFound();
    }

    public function test_rescheduling_the_earliest_group_fixture_shifts_the_derived_prediction_lock(): void
    {
        $earliest = $this->tournament->groupFixtures()
            ->whereNotNull('kicks_off_at')
            ->orderBy('kicks_off_at')
            ->orderBy('id')
            ->firstOrFail();

        // Move it even earlier (but still future) so it remains the tournament's first kickoff.
        $this->actingAs($this->admin())
            ->patch(route('games.fixtures.reschedule', [$this->game, $earliest]), [
                'kicks_off_at' => '2026-06-05T18:00',
                'venue' => $earliest->venue,
            ])
            ->assertRedirect(route('games.schedule.index', $this->game));

        $buffer = (int) config('scoring.prediction_lock_buffer_minutes');
        // The kickoff is read in the admin's timezone (New York), then the buffer is applied.
        $expectedLock = CarbonImmutable::parse('2026-06-05 18:00:00', 'America/New_York')
            ->utc()
            ->subMinutes($buffer);

        $lock = Game::where('slug', 'world-cup-2026-ffa')->firstOrFail()->predictionsLockAt();
        $this->assertNotNull($lock);
        $this->assertTrue($lock->equalTo($expectedLock));
    }
}
