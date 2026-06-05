<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithPredictions;
use Tests\TestCase;

class JoinedGamesSidebarTest extends TestCase
{
    use InteractsWithPredictions;
    use RefreshDatabase;

    private Game $game;

    private Tournament $tournament;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->game = Game::where('slug', 'world-cup-2026-ffa')->firstOrFail();
        $this->tournament = $this->game->tournament;
        $this->user = User::factory()->create();
    }

    public function test_a_player_who_has_joined_nothing_gets_an_empty_list(): void
    {
        $this->actingAs($this->user)
            ->get(route('games.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('joinedGames', []));
    }

    public function test_a_joined_game_with_unfinished_picks_needs_attention(): void
    {
        $this->actingAs($this->user)->post(route('games.join', $this->game->slug));

        $this->actingAs($this->user)
            ->get(route('games.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('joinedGames', 1)
                ->where('joinedGames.0.slug', 'world-cup-2026-ffa')
                ->where('joinedGames.0.source', 'FF&A')
                ->where('joinedGames.0.needs_attention', true)
                ->has('joinedGames.0.name')
                ->has('joinedGames.0.accent')
            );
    }

    public function test_a_joined_game_with_every_group_pick_made_needs_no_attention(): void
    {
        $this->actingAs($this->user)->post(route('games.join', $this->game->slug));
        $entry = $this->game->entryFor($this->user);

        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores());

        $this->actingAs($this->user)
            ->get(route('games.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('joinedGames.0.slug', 'world-cup-2026-ffa')
                ->where('joinedGames.0.needs_attention', false)
            );
    }

    public function test_a_joined_game_needs_no_attention_once_the_window_is_closed(): void
    {
        // Join while open, then shut the window: even with zero predictions there is nothing
        // the player can do, so the game should not nag for attention.
        $this->actingAs($this->user)->post(route('games.join', $this->game->slug));
        $this->game->update(['predictions_lock_at' => now()->subDay()]);

        $this->actingAs($this->user)
            ->get(route('games.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('joinedGames.0.slug', 'world-cup-2026-ffa')
                ->where('joinedGames.0.needs_attention', false)
            );
    }

    public function test_a_guest_gets_no_joined_games(): void
    {
        // The welcome page renders for guests and carries the shared prop.
        $this->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('joinedGames', []));
    }
}
