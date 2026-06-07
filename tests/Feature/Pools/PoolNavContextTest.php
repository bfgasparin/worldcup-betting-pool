<?php

namespace Tests\Feature\Pools;

use App\Models\User;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The mobile bottom tab bar (and the sidebar's tournament nav) render off the presence of the
 * page-level `pool` prop. These tests pin that contract: in-pool screens share it, the hub does not.
 */
class PoolNavContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->actingAs(User::factory()->create());
    }

    public function test_the_overview_shares_the_pool_nav_context(): void
    {
        $this->get(route('pools.show', 'world-cup-2026-ffa'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('pools/show')
                ->where('pool.slug', 'world-cup-2026-ffa')
            );
    }

    public function test_the_leaderboard_shares_the_pool_nav_context(): void
    {
        $this->get(route('pools.leaderboard', 'world-cup-2026-ffa'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('pools/leaderboard')
                ->where('pool.slug', 'world-cup-2026-ffa')
            );
    }

    public function test_the_hub_has_no_pool_nav_context(): void
    {
        // No `pool` prop on the hub means the bottom tab bar stays hidden there.
        $this->get(route('pools.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('pools/index')
                ->missing('pool')
            );
    }
}
