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

class PhasedTieNoteTest extends TestCase
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

    public function test_a_phased_pool_with_a_tie_shows_the_explanatory_note(): void
    {
        $pool = $this->phasedPool();
        $entry = Entry::factory()->for($pool)->for($this->user)->create();
        // seedOrderScores leaves all twelve third-placed teams level — a straddling best-thirds tie.
        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores());

        $this->actingAs($this->user)
            ->get(route('pools.predict.edit', $pool->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('show_tie_note', true));
    }

    public function test_an_upfront_pool_surfaces_the_editable_panel_not_the_note(): void
    {
        $pool = $this->upfrontPool();
        $entry = Entry::factory()->for($pool)->for($this->user)->create();
        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores());

        $this->actingAs($this->user)
            ->get(route('pools.predict.edit', $pool->slug))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('show_tie_note', false)
                ->whereNot('thirds_tie', null)
            );
    }

    public function test_a_phased_pool_without_ties_has_no_note(): void
    {
        $pool = $this->phasedPool();
        Entry::factory()->for($pool)->for($this->user)->create();

        $this->actingAs($this->user)
            ->get(route('pools.predict.edit', $pool->slug))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('show_tie_note', false));
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
