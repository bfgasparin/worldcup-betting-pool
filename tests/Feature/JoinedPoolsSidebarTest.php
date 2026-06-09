<?php

namespace Tests\Feature;

use App\Enums\PhaseKey;
use App\Models\Entry;
use App\Models\Pool;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Predictions\BracketResolver;
use App\Services\Predictions\OfficialBracketProjector;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithOfficialResults;
use Tests\Concerns\InteractsWithPredictions;
use Tests\TestCase;

class JoinedPoolsSidebarTest extends TestCase
{
    use InteractsWithOfficialResults;
    use InteractsWithPredictions;
    use RefreshDatabase;

    private Pool $pool;

    private Tournament $tournament;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->pool = Pool::where('slug', 'world-cup-2026-ffa')->firstOrFail();
        $this->tournament = $this->pool->tournament;
        $this->user = User::factory()->create();
    }

    public function test_a_player_who_has_joined_nothing_gets_an_empty_list(): void
    {
        $this->actingAs($this->user)
            ->get(route('pools.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('joinedPools', []));
    }

    public function test_a_joined_pool_with_unfinished_picks_needs_attention(): void
    {
        $this->actingAs($this->user)->post(route('pools.join', $this->pool->slug));

        $this->actingAs($this->user)
            ->get(route('pools.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('joinedPools', 1)
                ->where('joinedPools.0.slug', 'world-cup-2026-ffa')
                ->where('joinedPools.0.source', 'Wagner Figueiredo')
                ->where('joinedPools.0.needs_attention', true)
                ->has('joinedPools.0.name')
                ->has('joinedPools.0.accent')
            );
    }

    public function test_a_fully_predicted_pool_needs_no_attention(): void
    {
        $this->actingAs($this->user)->post(route('pools.join', $this->pool->slug));
        $entry = $this->pool->entryFor($this->user);

        // An upfront pool is only done when the whole bracket is predicted: every group score AND
        // every knockout pick, not just the group stage.
        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores());
        $this->advanceAllHome($entry, new BracketResolver);

        $this->actingAs($this->user)
            ->get(route('pools.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('joinedPools.0.slug', 'world-cup-2026-ffa')
                ->where('joinedPools.0.needs_attention', false)
            );
    }

    public function test_an_upfront_pool_with_unfinished_knockout_picks_needs_attention(): void
    {
        $this->actingAs($this->user)->post(route('pools.join', $this->pool->slug));
        $entry = $this->pool->entryFor($this->user);

        // Every group score is in, but the self-derived knockout bracket is untouched — an upfront
        // pool predicts that up front too, so there is still work to do.
        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores());

        $this->actingAs($this->user)
            ->get(route('pools.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('joinedPools.0.slug', 'world-cup-2026-ffa')
                ->where('joinedPools.0.needs_attention', true)
            );
    }

    public function test_a_joined_pool_with_only_unresolved_ties_needs_attention(): void
    {
        $this->actingAs($this->user)->post(route('pools.join', $this->pool->slug));
        $entry = $this->pool->entryFor($this->user);

        // Every group pick is made, but all-draw scores leave every standing tied with no ordering
        // recorded — the player still has work to do, so the sidebar must keep nagging.
        $this->predictAllGroups($entry, $this->tournament, fn (): array => [0, 0], resolveTies: false);

        $this->actingAs($this->user)
            ->get(route('pools.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('joinedPools.0.slug', 'world-cup-2026-ffa')
                ->where('joinedPools.0.needs_attention', true)
            );
    }

    public function test_a_joined_phased_pool_with_an_open_knockout_round_needs_attention(): void
    {
        $phased = $this->tournament->pools()->where('slug', 'world-cup-2026-brothers')->firstOrFail();
        // Close the group window so attention can only come from the open knockout round.
        $phased->update(['predictions_lock_at' => now()->subDay()]);
        Entry::factory()->for($phased)->for($this->user)->create();

        $this->recordOfficialGroupResults($this->tournament, $this->seedOrderScores());
        (new OfficialBracketProjector)->project($this->tournament);
        $this->tournament->syncStatus();
        $this->tournament->phases()->where('key', PhaseKey::RoundOf32->value)->firstOrFail()
            ->fixtures()->update(['kicks_off_at' => now()->addDays(5)]);

        $this->actingAs($this->user)
            ->get(route('pools.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('joinedPools.0.slug', 'world-cup-2026-brothers')
                ->where('joinedPools.0.needs_attention', true)
            );
    }

    public function test_a_joined_pool_needs_no_attention_once_the_window_is_closed(): void
    {
        // Join while open, then shut the window: even with zero predictions there is nothing
        // the player can do, so the pool should not nag for attention.
        $this->actingAs($this->user)->post(route('pools.join', $this->pool->slug));
        $this->pool->update(['predictions_lock_at' => now()->subDay()]);

        $this->actingAs($this->user)
            ->get(route('pools.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('joinedPools.0.slug', 'world-cup-2026-ffa')
                ->where('joinedPools.0.needs_attention', false)
            );
    }

    public function test_a_guest_gets_no_joined_pools(): void
    {
        // The welcome page renders for guests and carries the shared prop.
        $this->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('joinedPools', []));
    }
}
