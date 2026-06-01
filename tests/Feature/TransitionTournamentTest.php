<?php

namespace Tests\Feature;

use App\Enums\TournamentStatus;
use App\Events\TournamentStatusChanged;
use App\Models\Game;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TransitionTournamentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        config()->set('admin.emails', [$admin->email]);

        return $admin;
    }

    public function test_an_admin_can_advance_through_a_legal_transition(): void
    {
        Event::fake([TournamentStatusChanged::class]);

        $tournament = Tournament::factory()->create(['status' => TournamentStatus::Upcoming]);
        $game = Game::factory()->for($tournament)->create();

        $this->actingAs($this->admin())
            ->patch(route('games.status.update', $game), ['status' => 'in_progress'])
            ->assertRedirect(route('games.show', $game));

        $this->assertSame(TournamentStatus::InProgress, $tournament->fresh()->status);

        Event::assertDispatched(
            TournamentStatusChanged::class,
            fn (TournamentStatusChanged $event): bool => $event->tournament->is($tournament)
                && $event->from === TournamentStatus::Upcoming
                && $event->to === TournamentStatus::InProgress,
        );
    }

    public function test_an_illegal_transition_is_rejected(): void
    {
        $tournament = Tournament::factory()->create(['status' => TournamentStatus::Upcoming]);
        $game = Game::factory()->for($tournament)->create();

        $this->actingAs($this->admin())
            ->patch(route('games.status.update', $game), ['status' => 'completed'])
            ->assertSessionHasErrors('status');

        $this->assertSame(TournamentStatus::Upcoming, $tournament->fresh()->status);
    }

    public function test_a_non_admin_is_forbidden(): void
    {
        $tournament = Tournament::factory()->create(['status' => TournamentStatus::Upcoming]);
        $game = Game::factory()->for($tournament)->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('games.status.update', $game), ['status' => 'in_progress'])
            ->assertForbidden();

        $this->assertSame(TournamentStatus::Upcoming, $tournament->fresh()->status);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $tournament = Tournament::factory()->create(['status' => TournamentStatus::Upcoming]);
        $game = Game::factory()->for($tournament)->create();

        $this->patch(route('games.status.update', $game), ['status' => 'in_progress'])
            ->assertRedirect(route('login'));
    }

    public function test_show_exposes_allowed_transitions_and_admin_flag(): void
    {
        $tournament = Tournament::factory()->create(['status' => TournamentStatus::Upcoming]);
        $game = Game::factory()->for($tournament)->create();

        $this->actingAs($this->admin())
            ->get(route('games.show', $game))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('game.allowed_transitions', ['in_progress'])
                ->where('auth.isAdmin', true)
            );
    }

    public function test_non_admin_is_not_flagged_as_admin(): void
    {
        $game = Game::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('games.show', $game))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.isAdmin', false)
            );
    }
}
