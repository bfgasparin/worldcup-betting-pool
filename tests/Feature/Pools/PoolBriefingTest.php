<?php

namespace Tests\Feature\Pools;

use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PoolBriefingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
    }

    public function test_show_reports_the_briefing_unseen_for_a_first_time_viewer(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('pools.show', 'world-cup-2026-ffa'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pool.has_seen_briefing', false)
            );
    }

    public function test_marking_the_briefing_seen_records_it_and_show_then_reports_it(): void
    {
        $user = User::factory()->create();
        $pool = Tournament::firstOrFail()->pools()->where('slug', 'world-cup-2026-ffa')->firstOrFail();

        $this->actingAs($user)
            ->post(route('pools.briefing.seen', $pool->slug))
            ->assertNoContent();

        $this->assertDatabaseHas('pool_briefing_views', [
            'pool_id' => $pool->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('pools.show', $pool->slug))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pool.has_seen_briefing', true)
            );
    }

    public function test_marking_the_briefing_seen_is_idempotent(): void
    {
        $user = User::factory()->create();
        $pool = Tournament::firstOrFail()->pools()->where('slug', 'world-cup-2026-ffa')->firstOrFail();

        $this->actingAs($user)->post(route('pools.briefing.seen', $pool->slug))->assertNoContent();
        $this->actingAs($user)->post(route('pools.briefing.seen', $pool->slug))->assertNoContent();

        $this->assertDatabaseCount('pool_briefing_views', 1);
    }

    public function test_marking_the_briefing_seen_survives_a_concurrent_insert_race(): void
    {
        $user = User::factory()->create();
        $pool = Tournament::firstOrFail()->pools()->where('slug', 'world-cup-2026-ffa')->firstOrFail();

        // Simulate a concurrent request winning the race: right after syncWithoutDetaching reads the
        // (empty) pivot, sneak in the conflicting row so its follow-up insert hits the unique index.
        $injected = false;
        DB::listen(function ($query) use (&$injected, $pool, $user) {
            if (! $injected
                && str_contains($query->sql, 'select')
                && str_contains($query->sql, 'pool_briefing_views')) {
                $injected = true;
                DB::table('pool_briefing_views')->insert([
                    'pool_id' => $pool->id,
                    'user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $pool->markBriefingSeenBy($user); // must not throw

        $this->assertTrue($injected, 'the existence-check SELECT should have fired');
        $this->assertDatabaseCount('pool_briefing_views', 1);
    }

    public function test_guests_cannot_mark_the_briefing_seen(): void
    {
        $this->post(route('pools.briefing.seen', 'world-cup-2026-ffa'))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('pool_briefing_views', 0);
    }

    public function test_the_briefing_seen_state_is_per_user(): void
    {
        $seen = User::factory()->create();
        $fresh = User::factory()->create();
        $pool = Tournament::firstOrFail()->pools()->where('slug', 'world-cup-2026-ffa')->firstOrFail();

        $this->actingAs($seen)->post(route('pools.briefing.seen', $pool->slug))->assertNoContent();

        // The flag belongs to the user, not the pool: a different user is still a first-time viewer.
        $this->actingAs($fresh)
            ->get(route('pools.show', $pool->slug))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pool.has_seen_briefing', false)
            );
    }
}
