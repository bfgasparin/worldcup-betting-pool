<?php

namespace Tests\Feature\Live;

use App\Enums\FixtureStatus;
use App\Enums\LiveStatus;
use App\Enums\ProposalStatus;
use App\Models\Fixture;
use App\Models\FixtureLiveState;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveControlControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('scoring.go_live_buffer_minutes', 15);
        $this->withoutVite();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        config()->set('admin.emails', [$admin->email]);

        return $admin;
    }

    public function test_admin_sees_the_control_console(): void
    {
        $tournament = Tournament::factory()->create();
        Fixture::factory()->for($tournament)->create([
            'status' => FixtureStatus::Scheduled,
            'kicks_off_at' => now()->addMinutes(5),
        ]);

        $this->actingAs($this->admin())
            ->get(route('manage.live.index', $tournament))
            ->assertInertia(fn ($page) => $page->component('manage/live', false)->has('fixtures', 1));
    }

    public function test_index_includes_ready_upcoming_and_ended_fixtures(): void
    {
        $tournament = Tournament::factory()->create();

        // Ready: scheduled and inside the go-live buffer.
        Fixture::factory()->for($tournament)->create([
            'status' => FixtureStatus::Scheduled,
            'kicks_off_at' => now()->addMinutes(5),
        ]);

        // Upcoming: scheduled with known teams, but well outside the buffer.
        Fixture::factory()->for($tournament)->create([
            'status' => FixtureStatus::Scheduled,
            'kicks_off_at' => now()->addDays(3),
        ]);

        // Ended: a live scoreboard the admin already closed.
        $ended = Fixture::factory()->for($tournament)->ended()->create();
        FixtureLiveState::factory()->for($ended)->ended()->withScore(2, 1)->create();

        $this->actingAs($this->admin())
            ->get(route('manage.live.index', $tournament))
            ->assertInertia(fn ($page) => $page
                ->component('manage/live', false)
                ->has('fixtures', 3)
                // Each row carries the fields the client buckets on (Live & ready / Upcoming / Ended).
                ->has('fixtures.0', fn ($fixture) => $fixture
                    ->has('can_go_live')
                    ->has('live_status')
                    ->etc()));
    }

    public function test_index_excludes_empty_placeholder_knockout_slots(): void
    {
        $tournament = Tournament::factory()->create();

        // A knockout slot with no resolved teams can't go live and isn't searchable — keep it out.
        Fixture::factory()->for($tournament)->knockout()->create([
            'status' => FixtureStatus::Scheduled,
            'kicks_off_at' => now()->addDays(3),
        ]);

        $this->actingAs($this->admin())
            ->get(route('manage.live.index', $tournament))
            ->assertInertia(fn ($page) => $page->component('manage/live', false)->has('fixtures', 0));
    }

    public function test_non_admins_cannot_access_the_console(): void
    {
        $tournament = Tournament::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('manage.live.index', $tournament))
            ->assertForbidden();
    }

    public function test_admin_marks_a_fixture_live(): void
    {
        $tournament = Tournament::factory()->create();
        $fixture = Fixture::factory()->for($tournament)->create([
            'status' => FixtureStatus::Scheduled,
            'kicks_off_at' => now()->addMinutes(5),
        ]);

        $this->actingAs($this->admin())
            ->post(route('manage.live.go-live', [$tournament, $fixture]))
            ->assertRedirect();

        $this->assertSame(FixtureStatus::Live, $fixture->fresh()->status);
        $this->assertSame(LiveStatus::Live, $fixture->fresh()->liveState->status);
    }

    public function test_going_live_is_rejected_before_the_buffer(): void
    {
        $tournament = Tournament::factory()->create();
        $fixture = Fixture::factory()->for($tournament)->create([
            'status' => FixtureStatus::Scheduled,
            'kicks_off_at' => now()->addHour(),
        ]);

        $this->actingAs($this->admin())
            ->post(route('manage.live.go-live', [$tournament, $fixture]))
            ->assertStatus(422);

        $this->assertSame(FixtureStatus::Scheduled, $fixture->fresh()->status);
    }

    public function test_admin_updates_the_live_score(): void
    {
        $tournament = Tournament::factory()->create();
        $fixture = Fixture::factory()->for($tournament)->create(['status' => FixtureStatus::Live]);
        FixtureLiveState::factory()->for($fixture)->create(['status' => LiveStatus::Live]);

        $this->actingAs($this->admin())
            ->patch(route('manage.live.score', [$tournament, $fixture]), ['home_goals' => 2, 'away_goals' => 1])
            ->assertRedirect();

        $this->assertSame(2, $fixture->fresh()->liveState->home_goals);
        $this->assertSame(1, $fixture->fresh()->liveState->away_goals);
    }

    public function test_admin_ends_a_match_handing_it_to_the_proposal_pipeline(): void
    {
        $tournament = Tournament::factory()->create();
        $fixture = Fixture::factory()->for($tournament)->create(['status' => FixtureStatus::Live]);
        FixtureLiveState::factory()->for($fixture)->withScore(2, 0)->create();

        $this->actingAs($this->admin())
            ->post(route('manage.live.end', [$tournament, $fixture]))
            ->assertRedirect();

        $this->assertSame(LiveStatus::Ended, $fixture->fresh()->liveState->status);
        $this->assertDatabaseHas('score_proposals', [
            'fixture_id' => $fixture->id,
            'home_goals' => 2,
            'away_goals' => 0,
            'status' => ProposalStatus::Pending->value,
        ]);
        $this->assertNull($fixture->fresh()->home_goals);
    }

    public function test_actions_404_for_a_fixture_outside_the_tournament(): void
    {
        $tournament = Tournament::factory()->create();
        $otherFixture = Fixture::factory()->create([
            'status' => FixtureStatus::Scheduled,
            'kicks_off_at' => now()->addMinutes(5),
        ]);

        $this->actingAs($this->admin())
            ->post(route('manage.live.go-live', [$tournament, $otherFixture]))
            ->assertNotFound();
    }
}
