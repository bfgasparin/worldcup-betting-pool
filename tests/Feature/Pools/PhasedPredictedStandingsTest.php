<?php

namespace Tests\Feature\Pools;

use App\Models\Entry;
use App\Models\Pool;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithPredictions;
use Tests\TestCase;

/**
 * Phased-bracket pools predict the official knockout bracket, so a player's projected group order
 * decides nothing. The pool page and compare mode therefore drop the predicted/projected standings
 * (upfront pools keep them), while the meaningful per-fixture score picks stay.
 */
class PhasedPredictedStandingsTest extends TestCase
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

    public function test_phased_main_page_omits_predicted_standings_but_keeps_per_fixture_picks(): void
    {
        $pool = $this->phasedPool(now()->addWeek());
        $entry = Entry::factory()->for($pool)->for($this->user)->create();
        $this->predictGroup($entry, $this->tournament, 'A', $this->seedOrderScores());

        $this->actingAs($this->user)
            ->get(route('pools.show', $pool->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('groups.0.standings')
                ->missing('groups.0.predicted_standings')
                ->whereNot('groups.0.fixtures.0.prediction', null)
            );
    }

    public function test_upfront_main_page_keeps_predicted_standings(): void
    {
        $pool = $this->upfrontPool(now()->addWeek());
        $entry = Entry::factory()->for($pool)->for($this->user)->create();
        $this->predictGroup($entry, $this->tournament, 'A', $this->seedOrderScores());

        $this->actingAs($this->user)
            ->get(route('pools.show', $pool->slug))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->whereNot('groups.0.predicted_standings', null)
            );
    }

    public function test_phased_compare_omits_projected_standings_but_keeps_score_comparison(): void
    {
        $pool = $this->phasedPool(now()->subDay()); // group window locked → opponent revealed
        $viewerEntry = Entry::factory()->for($pool)->for($this->user)->create();
        $opponent = Entry::factory()->for($pool)->for(User::factory()->create(['name' => 'Rival']))->create();

        $this->predictGroup($viewerEntry, $this->tournament, 'A', $this->seedOrderScores());
        $this->predictGroup($opponent, $this->tournament, 'A', $this->seedOrderScores());

        $fixture = $this->tournament->groups()->where('name', 'A')->firstOrFail()
            ->fixtures()->orderBy('match_number')->firstOrFail();

        $this->actingAs($this->user)
            ->get(route('pools.show', ['pool' => $pool->slug, 'compare' => (string) $opponent->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('comparison.windows.group', 'locked')
                // No projected standings for a phased pool — not even the viewer's own or a revealed opponent's.
                ->where('comparison.players.0.projected_standings.A', null)
                ->where('comparison.players.1.projected_standings.A', null)
                // The per-fixture score comparison is kept (opponent revealed post-lock).
                ->has("comparison.players.1.group_predictions.{$fixture->id}")
            );
    }

    private function upfrontPool(\DateTimeInterface|string|null $lockAt): Pool
    {
        return Pool::factory()->create([
            'tournament_id' => $this->tournament->id,
            'predictions_lock_at' => $lockAt,
        ]);
    }

    private function phasedPool(\DateTimeInterface|string|null $lockAt): Pool
    {
        return Pool::factory()->phasedBracket()->create([
            'tournament_id' => $this->tournament->id,
            'predictions_lock_at' => $lockAt,
        ]);
    }
}
