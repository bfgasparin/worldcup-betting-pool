<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\Pool;
use App\Models\User;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class JoinPoolTest extends TestCase
{
    use RefreshDatabase;

    private Pool $pool;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->pool = Pool::where('slug', 'world-cup-2026-ffa')->firstOrFail();
        $this->user = User::factory()->create();
    }

    public function test_guests_cannot_join(): void
    {
        $this->post(route('pools.join', 'world-cup-2026-ffa'))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('entries', 0);
    }

    public function test_a_player_joins_and_an_entry_is_created(): void
    {
        $this->actingAs($this->user)
            ->post(route('pools.join', 'world-cup-2026-ffa'))
            ->assertRedirect(route('pools.show', 'world-cup-2026-ffa'));

        $this->assertDatabaseHas('entries', [
            'pool_id' => $this->pool->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseCount('entries', 1);
    }

    public function test_joining_is_idempotent(): void
    {
        $this->actingAs($this->user)->post(route('pools.join', 'world-cup-2026-ffa'));
        $this->actingAs($this->user)->post(route('pools.join', 'world-cup-2026-ffa'));

        $this->assertDatabaseCount('entries', 1);
    }

    public function test_joining_is_forbidden_after_predictions_lock(): void
    {
        $this->pool->update(['predictions_lock_at' => now()->subDay()]);

        $this->actingAs($this->user)
            ->post(route('pools.join', 'world-cup-2026-ffa'))
            ->assertForbidden();

        $this->assertDatabaseCount('entries', 0);
    }

    public function test_the_pool_page_exposes_pricing_and_the_join_window(): void
    {
        $this->actingAs($this->user)
            ->get(route('pools.show', 'world-cup-2026-ffa'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pool.can_join', true)
                ->where('pool.pricing.entry_price', 50)
                ->where('pool.pricing.currency', 'BRL')
                ->where('pool.pricing.house_fee_percentage', 7)
                ->has('pool.pricing.prizes', 3)
                ->where('pool.pricing.prizes.0.place', 1)
                ->where('pool.pricing.prizes.0.percentage', 70)
            );
    }

    public function test_the_index_computes_prize_amounts_from_the_pool(): void
    {
        // Three players in the FF&A pool: 3 × R$50 = R$150, less 7% fee = R$139.50 net.
        Entry::factory()->count(3)->for($this->pool)->create();

        $this->actingAs($this->user)
            ->get(route('pools.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pools.data.0.slug', 'world-cup-2026-ffa')
                ->where('pools.data.0.pricing.players', 3)
                ->where('pools.data.0.pricing.pot', 150)
                ->where('pools.data.0.pricing.net', 139.5)
                ->where('pools.data.0.pricing.prizes.0.amount', 97.65)
            );
    }

    public function test_the_index_flags_pools_the_player_has_joined(): void
    {
        $this->actingAs($this->user)->post(route('pools.join', 'world-cup-2026-ffa'));

        $this->actingAs($this->user)
            ->get(route('pools.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pools.data.0.slug', 'world-cup-2026-ffa')
                ->where('pools.data.0.joined', true)
                ->where('pools.data.1.slug', 'world-cup-2026-brothers')
                ->where('pools.data.1.joined', false)
            );
    }

    public function test_the_index_exposes_whether_joining_is_still_open(): void
    {
        // The card reads can_join to show the buy-in and a percentage prize split while the pool is
        // still filling, then switches to the now-final raw amounts once joining closes.
        $this->actingAs($this->user)
            ->get(route('pools.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pools.data.0.slug', 'world-cup-2026-ffa')
                ->where('pools.data.0.can_join', true)
            );

        $this->pool->update(['predictions_lock_at' => now()->subDay()]);

        $this->actingAs($this->user)
            ->get(route('pools.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pools.data.0.slug', 'world-cup-2026-ffa')
                ->where('pools.data.0.can_join', false)
            );
    }
}
