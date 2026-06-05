<?php

namespace Tests\Feature\Predictions;

use App\Models\Entry;
use App\Models\Pool;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithPredictions;
use Tests\TestCase;

class PhasedTieMarkTest extends TestCase
{
    use InteractsWithPredictions;
    use RefreshDatabase;

    private Tournament $tournament;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
        $this->user = User::factory()->create();
    }

    public function test_a_phased_pool_flags_genuinely_tied_standings_rows(): void
    {
        $pool = $this->phasedPool();
        $entry = Entry::factory()->for($pool)->for($this->user)->create();
        // Every fixture 0-0 leaves all four teams level on every tiebreaker — a true tie.
        $this->predictGroup($entry, $this->tournament, 'A', fn (): array => [0, 0]);

        $this->actingAs($this->user)
            ->get(route('pools.predict.edit', $pool->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('groups.0.standings.0.tied', true)
                ->where('groups.0.standings.3.tied', true)
            );
    }

    public function test_a_phased_pool_does_not_flag_cleanly_ordered_rows(): void
    {
        $pool = $this->phasedPool();
        $entry = Entry::factory()->for($pool)->for($this->user)->create();
        $this->predictGroup($entry, $this->tournament, 'A', $this->seedOrderScores());

        $this->actingAs($this->user)
            ->get(route('pools.predict.edit', $pool->slug))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('groups.0.standings.0.tied', false));
    }

    public function test_an_upfront_pool_keeps_the_editable_panel_and_does_not_flag_rows(): void
    {
        $pool = $this->upfrontPool();
        $entry = Entry::factory()->for($pool)->for($this->user)->create();
        $this->predictGroup($entry, $this->tournament, 'A', fn (): array => [0, 0]);

        $this->actingAs($this->user)
            ->get(route('pools.predict.edit', $pool->slug))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('groups.0.standings.0.tied', false)
                ->has('groups.0.tied_clusters', 1)
            );
    }

    private function upfrontPool(): Pool
    {
        return Pool::factory()->create([
            'tournament_id' => $this->tournament->id,
            'predictions_lock_at' => now()->addWeek(),
        ]);
    }

    private function phasedPool(): Pool
    {
        return Pool::factory()->phasedBracket()->create([
            'tournament_id' => $this->tournament->id,
            'predictions_lock_at' => now()->addWeek(),
        ]);
    }
}
