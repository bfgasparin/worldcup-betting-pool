<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\Fixture;
use App\Models\GroupPrediction;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class GameControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_games_index(): void
    {
        $this->get(route('games.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_see_the_seeded_game_on_the_index(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $this->actingAs(User::factory()->create());

        $this->get(route('games.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('games/index')
                ->has('games', 1)
                ->where('games.0.slug', 'world-cup-2026')
            );
    }

    public function test_show_renders_groups_and_bracket(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $this->actingAs(User::factory()->create());

        $this->get(route('games.show', 'world-cup-2026'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('games/show')
                ->where('game.slug', 'world-cup-2026')
                ->has('groups', 12)
                ->has('groups.0.teams', 4)
                ->has('groups.0.fixtures', 6)
                ->has('bracket', 6)
                ->where('bracket.0.phase_key', 'round_of_32')
                ->has('bracket.0.fixtures', 16)
                ->where('bracket.0.fixtures.0.home', null)
                ->whereNot('bracket.0.fixtures.0.home_label', null)
            );
    }

    public function test_show_resolves_group_fixture_teams(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $this->actingAs(User::factory()->create());

        $this->get(route('games.show', 'world-cup-2026'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->whereNot('groups.0.fixtures.0.home', null)
                ->whereNot('groups.0.fixtures.0.away', null)
            );
    }

    public function test_show_exposes_knockout_kick_off_dates(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $this->actingAs(User::factory()->create());

        $this->get(route('games.show', 'world-cup-2026'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->whereNot('bracket.0.fixtures.0.kicks_off_at', null)
                ->whereNot('bracket.0.fixtures.0.venue', null)
                ->whereNot('bracket.0.fixtures.0.venue_timezone', null)
                ->whereNot('groups.0.fixtures.0.kicks_off_at', null)
                ->whereNot('groups.0.fixtures.0.venue', null)
                ->whereNot('groups.0.fixtures.0.venue_timezone', null)
            );
    }

    public function test_show_includes_no_prediction_without_an_entry(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $this->actingAs(User::factory()->create());

        $this->get(route('games.show', 'world-cup-2026'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('groups.0.fixtures.0.prediction', null)
            );
    }

    public function test_show_surfaces_the_viewers_group_prediction(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $tournament = Tournament::firstOrFail();
        $user = User::factory()->create();
        $entry = Entry::factory()->for($tournament)->for($user)->create();
        $fixture = $tournament->fixtures()->where('match_number', 1)->firstOrFail();

        GroupPrediction::factory()->create([
            'entry_id' => $entry->id,
            'fixture_id' => $fixture->id,
            'home_goals' => 2,
            'away_goals' => 1,
        ]);

        $this->actingAs($user);

        $this->get(route('games.show', $tournament->slug))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('groups.0.fixtures.0.prediction.home_goals', 2)
                ->where('groups.0.fixtures.0.prediction.away_goals', 1)
            );
    }

    public function test_the_final_is_seeded_with_its_kick_off_date(): void
    {
        $this->seed(WorldCup2026Seeder::class);

        $final = Fixture::where('match_number', 104)->firstOrFail();

        $this->assertNotNull($final->kicks_off_at);
        $this->assertSame('2026-07-19', $final->kicks_off_at->toDateString());
    }

    public function test_show_includes_the_pool_summary(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $tournament = Tournament::firstOrFail();
        $user = User::factory()->create();
        Entry::factory()->for($tournament)->for($user)->create();

        $this->actingAs($user);

        $this->get(route('games.show', $tournament->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pool.participants', 1)
                ->where('pool.has_scores', false)
                ->where('pool.me.is_me', true)
                ->where('pool.me.points', null)
                ->has('pool.top', 1)
            );
    }

    public function test_guests_are_redirected_from_the_leaderboard(): void
    {
        $this->get(route('games.leaderboard', 'world-cup-2026'))
            ->assertRedirect(route('login'));
    }

    public function test_leaderboard_lists_pool_participants_without_scores(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $tournament = Tournament::firstOrFail();
        Entry::factory()->count(2)->for($tournament)->create();
        $me = User::factory()->create();
        Entry::factory()->for($tournament)->for($me)->create();

        $this->actingAs($me);

        $this->get(route('games.leaderboard', $tournament->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('games/leaderboard')
                ->where('game.slug', $tournament->slug)
                ->where('has_scores', false)
                ->has('rows', 3)
            );
    }

    public function test_leaderboard_ranks_entries_by_total_points(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $tournament = Tournament::firstOrFail();

        Entry::factory()->for($tournament)
            ->for(User::factory()->create(['name' => 'Top Scorer']))
            ->create(['total_points' => 120]);

        $me = User::factory()->create(['name' => 'Runner Up']);
        Entry::factory()->for($tournament)->for($me)->create(['total_points' => 40]);

        $this->actingAs($me);

        $this->get(route('games.leaderboard', $tournament->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('has_scores', true)
                ->where('rows.0.rank', 1)
                ->where('rows.0.name', 'Top Scorer')
                ->where('rows.0.points', 120)
                ->where('rows.1.rank', 2)
                ->where('rows.1.name', 'You')
                ->where('rows.1.is_me', true)
            );
    }
}
