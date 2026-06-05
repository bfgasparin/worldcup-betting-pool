<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\Game;
use App\Models\User;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class JoinGameTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->game = Game::where('slug', 'world-cup-2026-ffa')->firstOrFail();
        $this->user = User::factory()->create();
    }

    public function test_guests_cannot_join(): void
    {
        $this->post(route('games.join', 'world-cup-2026-ffa'))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('entries', 0);
    }

    public function test_a_player_joins_and_an_entry_is_created(): void
    {
        $this->actingAs($this->user)
            ->post(route('games.join', 'world-cup-2026-ffa'))
            ->assertRedirect(route('games.show', 'world-cup-2026-ffa'));

        $this->assertDatabaseHas('entries', [
            'game_id' => $this->game->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseCount('entries', 1);
    }

    public function test_joining_is_idempotent(): void
    {
        $this->actingAs($this->user)->post(route('games.join', 'world-cup-2026-ffa'));
        $this->actingAs($this->user)->post(route('games.join', 'world-cup-2026-ffa'));

        $this->assertDatabaseCount('entries', 1);
    }

    public function test_joining_is_forbidden_after_predictions_lock(): void
    {
        $this->game->update(['predictions_lock_at' => now()->subDay()]);

        $this->actingAs($this->user)
            ->post(route('games.join', 'world-cup-2026-ffa'))
            ->assertForbidden();

        $this->assertDatabaseCount('entries', 0);
    }

    public function test_the_game_page_exposes_pricing_and_the_join_window(): void
    {
        $this->actingAs($this->user)
            ->get(route('games.show', 'world-cup-2026-ffa'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('game.can_join', true)
                ->where('game.pricing.entry_price', 50)
                ->where('game.pricing.currency', 'BRL')
                ->where('game.pricing.house_fee_percentage', 7)
                ->has('game.pricing.prizes', 3)
                ->where('game.pricing.prizes.0.place', 1)
                ->where('game.pricing.prizes.0.percentage', 70)
            );
    }

    public function test_the_index_computes_prize_amounts_from_the_pool(): void
    {
        // Three players in the FF&A pool: 3 × R$50 = R$150, less 7% fee = R$139.50 net.
        Entry::factory()->count(3)->for($this->game)->create();

        $this->actingAs($this->user)
            ->get(route('games.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('games.data.0.slug', 'world-cup-2026-ffa')
                ->where('games.data.0.pricing.players', 3)
                ->where('games.data.0.pricing.pool', 150)
                ->where('games.data.0.pricing.net', 139.5)
                ->where('games.data.0.pricing.prizes.0.amount', 97.65)
            );
    }

    public function test_the_index_flags_pools_the_player_has_joined(): void
    {
        $this->actingAs($this->user)->post(route('games.join', 'world-cup-2026-ffa'));

        $this->actingAs($this->user)
            ->get(route('games.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('games.data.0.slug', 'world-cup-2026-ffa')
                ->where('games.data.0.joined', true)
                ->where('games.data.1.slug', 'world-cup-2026-brothers')
                ->where('games.data.1.joined', false)
            );
    }

    public function test_the_index_exposes_whether_joining_is_still_open(): void
    {
        // The card reads can_join to show the buy-in and a percentage prize split while the pool is
        // still filling, then switches to the now-final raw amounts once joining closes.
        $this->actingAs($this->user)
            ->get(route('games.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('games.data.0.slug', 'world-cup-2026-ffa')
                ->where('games.data.0.can_join', true)
            );

        $this->game->update(['predictions_lock_at' => now()->subDay()]);

        $this->actingAs($this->user)
            ->get(route('games.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('games.data.0.slug', 'world-cup-2026-ffa')
                ->where('games.data.0.can_join', false)
            );
    }
}
