<?php

namespace Tests\Unit\Models;

use App\Enums\FixtureStatus;
use App\Enums\TournamentStatus;
use App\Events\TournamentStatusChanged;
use App\Models\Fixture;
use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TournamentTest extends TestCase
{
    use RefreshDatabase;

    public function test_derive_status_is_upcoming_with_no_fixtures(): void
    {
        $tournament = Tournament::factory()->create();

        $this->assertSame(TournamentStatus::Upcoming, $tournament->deriveStatus());
    }

    public function test_derive_status_is_upcoming_when_every_fixture_is_still_scheduled(): void
    {
        $tournament = Tournament::factory()->create();
        Fixture::factory()->for($tournament)->count(2)->create();

        $this->assertSame(TournamentStatus::Upcoming, $tournament->deriveStatus());
    }

    public function test_derive_status_is_in_progress_when_a_fixture_is_live(): void
    {
        $tournament = Tournament::factory()->create();
        Fixture::factory()->for($tournament)->create();
        Fixture::factory()->for($tournament)->ended()->create();

        $this->assertSame(TournamentStatus::InProgress, $tournament->deriveStatus());
    }

    public function test_derive_status_is_in_progress_with_a_mix_of_finished_and_scheduled(): void
    {
        $tournament = Tournament::factory()->create();
        Fixture::factory()->for($tournament)->withResult()->create();
        Fixture::factory()->for($tournament)->create();

        $this->assertSame(TournamentStatus::InProgress, $tournament->deriveStatus());
    }

    public function test_derive_status_is_completed_only_when_every_fixture_is_finished(): void
    {
        $tournament = Tournament::factory()->create();
        Fixture::factory()->for($tournament)->count(3)->withResult()->create();

        $this->assertSame(TournamentStatus::Completed, $tournament->deriveStatus());
    }

    public function test_sync_status_advances_upcoming_to_in_progress_and_announces_it(): void
    {
        Event::fake([TournamentStatusChanged::class]);
        $tournament = Tournament::factory()->create();
        Fixture::factory()->for($tournament)->ended()->create();

        $tournament->syncStatus();

        $this->assertSame(TournamentStatus::InProgress, $tournament->fresh()->status);
        Event::assertDispatched(
            TournamentStatusChanged::class,
            fn (TournamentStatusChanged $event): bool => $event->tournament->is($tournament)
                && $event->from === TournamentStatus::Upcoming
                && $event->to === TournamentStatus::InProgress,
        );
    }

    public function test_sync_status_reverts_to_upcoming_when_nothing_is_live_anymore(): void
    {
        $tournament = Tournament::factory()->inProgress()->create();
        $fixture = Fixture::factory()->for($tournament)->ended()->create();

        // A reschedule moves the only live fixture back to scheduled in the future.
        $fixture->update(['status' => FixtureStatus::Scheduled, 'kicks_off_at' => now()->addDay()]);

        $tournament->syncStatus();

        $this->assertSame(TournamentStatus::Upcoming, $tournament->fresh()->status);
    }

    public function test_sync_status_is_a_noop_when_the_status_already_matches(): void
    {
        Event::fake([TournamentStatusChanged::class]);
        $tournament = Tournament::factory()->inProgress()->create();
        Fixture::factory()->for($tournament)->ended()->create();

        $tournament->syncStatus();

        $this->assertSame(TournamentStatus::InProgress, $tournament->fresh()->status);
        Event::assertNotDispatched(TournamentStatusChanged::class);
    }

    public function test_venue_timezones_returns_distinct_venues_mapped_to_their_timezone(): void
    {
        $tournament = Tournament::factory()->create();

        Fixture::factory()->for($tournament)->create(['venue' => 'Atlanta Stadium', 'venue_timezone' => 'America/New_York']);
        Fixture::factory()->for($tournament)->create(['venue' => 'Atlanta Stadium', 'venue_timezone' => 'America/New_York']);
        Fixture::factory()->for($tournament)->create(['venue' => 'Los Angeles Stadium', 'venue_timezone' => 'America/Los_Angeles']);
        Fixture::factory()->for($tournament)->create(['venue' => null, 'venue_timezone' => null]);

        // A venue belonging to another tournament must not leak in.
        Fixture::factory()->create(['venue' => 'Toronto Stadium', 'venue_timezone' => 'America/Toronto']);

        $this->assertSame([
            'Atlanta Stadium' => 'America/New_York',
            'Los Angeles Stadium' => 'America/Los_Angeles',
        ], $tournament->venueTimezones());
    }
}
