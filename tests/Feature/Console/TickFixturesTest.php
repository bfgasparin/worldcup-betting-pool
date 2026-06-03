<?php

namespace Tests\Feature\Console;

use App\Enums\FixtureStatus;
use App\Enums\TournamentStatus;
use App\Events\TournamentStatusChanged;
use App\Models\Fixture;
use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TickFixturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_kicked_off_fixtures_live(): void
    {
        $past = Fixture::factory()->create([
            'status' => FixtureStatus::Scheduled,
            'kicks_off_at' => now()->subHour(),
        ]);
        $future = Fixture::factory()->create([
            'status' => FixtureStatus::Scheduled,
            'kicks_off_at' => now()->addHour(),
        ]);

        $this->artisan('fixtures:tick')->assertSuccessful();

        $this->assertSame(FixtureStatus::Live, $past->fresh()->status);
        $this->assertSame(FixtureStatus::Scheduled, $future->fresh()->status);
    }

    public function test_it_leaves_live_finished_and_kickoff_less_fixtures_untouched(): void
    {
        $live = Fixture::factory()->create([
            'status' => FixtureStatus::Live,
            'kicks_off_at' => now()->subHour(),
        ]);
        $finished = Fixture::factory()->create([
            'status' => FixtureStatus::Finished,
            'kicks_off_at' => now()->subHour(),
        ]);
        $noKickoff = Fixture::factory()->create([
            'status' => FixtureStatus::Scheduled,
            'kicks_off_at' => null,
        ]);

        $this->artisan('fixtures:tick')->assertSuccessful();

        $this->assertSame(FixtureStatus::Live, $live->fresh()->status);
        $this->assertSame(FixtureStatus::Finished, $finished->fresh()->status);
        $this->assertSame(FixtureStatus::Scheduled, $noKickoff->fresh()->status);
    }

    public function test_it_is_idempotent(): void
    {
        $fixture = Fixture::factory()->create([
            'status' => FixtureStatus::Scheduled,
            'kicks_off_at' => now()->subHour(),
        ]);

        $this->artisan('fixtures:tick')->assertSuccessful();
        $this->artisan('fixtures:tick')->assertSuccessful();

        $this->assertSame(FixtureStatus::Live, $fixture->fresh()->status);
    }

    public function test_it_can_target_a_single_tournament(): void
    {
        $target = Tournament::factory()->create();
        $other = Tournament::factory()->create();

        $inTarget = Fixture::factory()->for($target)->create([
            'status' => FixtureStatus::Scheduled,
            'kicks_off_at' => now()->subHour(),
        ]);
        $inOther = Fixture::factory()->for($other)->create([
            'status' => FixtureStatus::Scheduled,
            'kicks_off_at' => now()->subHour(),
        ]);

        $this->artisan('fixtures:tick', ['tournament' => $target->slug])->assertSuccessful();

        $this->assertSame(FixtureStatus::Live, $inTarget->fresh()->status);
        $this->assertSame(FixtureStatus::Scheduled, $inOther->fresh()->status);

        // Only the targeted tournament advances.
        $this->assertSame(TournamentStatus::InProgress, $target->fresh()->status);
        $this->assertSame(TournamentStatus::Upcoming, $other->fresh()->status);
    }

    public function test_it_advances_the_tournament_to_in_progress_when_a_fixture_kicks_off(): void
    {
        Event::fake([TournamentStatusChanged::class]);

        $tournament = Tournament::factory()->create();
        Fixture::factory()->for($tournament)->create([
            'status' => FixtureStatus::Scheduled,
            'kicks_off_at' => now()->subHour(),
        ]);

        $this->artisan('fixtures:tick')->assertSuccessful();

        $this->assertSame(TournamentStatus::InProgress, $tournament->fresh()->status);
        Event::assertDispatched(
            TournamentStatusChanged::class,
            fn (TournamentStatusChanged $event): bool => $event->tournament->is($tournament)
                && $event->to === TournamentStatus::InProgress,
        );
    }

    public function test_it_leaves_the_tournament_upcoming_when_no_fixture_starts(): void
    {
        $tournament = Tournament::factory()->create();
        Fixture::factory()->for($tournament)->create([
            'status' => FixtureStatus::Scheduled,
            'kicks_off_at' => now()->addHour(),
        ]);

        $this->artisan('fixtures:tick')->assertSuccessful();

        $this->assertSame(TournamentStatus::Upcoming, $tournament->fresh()->status);
    }
}
