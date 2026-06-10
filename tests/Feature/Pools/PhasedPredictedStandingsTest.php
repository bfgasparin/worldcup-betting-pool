<?php

namespace Tests\Feature\Pools;

use App\Enums\PhaseKey;
use App\Models\Entry;
use App\Models\Pool;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Predictions\OfficialBracketProjector;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithOfficialResults;
use Tests\Concerns\InteractsWithPredictions;
use Tests\TestCase;

/**
 * Phased-bracket pools predict the official knockout bracket, so a player's projected group order
 * decides nothing. The pool page and compare mode therefore drop the predicted/projected standings
 * (upfront pools keep them), while the meaningful per-fixture score picks stay.
 */
class PhasedPredictedStandingsTest extends TestCase
{
    use InteractsWithOfficialResults;
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

    /**
     * The card footer for an UNPLAYED phased knockout fixture is driven by the scoreline pick, because
     * phased pools never expose predicted teams (the official match-up is shown on the card and the
     * advancer is resolved against it). This pins the bracket-prop contract the show page renders from.
     */
    public function test_phased_main_page_exposes_unplayed_knockout_pick(): void
    {
        $pool = $this->phasedPool(now()->subDay()); // group window shut; the R32 window is what we open
        Entry::factory()->for($pool)->for($this->user)->create();
        $this->projectRoundOf32();
        $this->setPhaseKickoff(PhaseKey::RoundOf32, now()->addDay());

        // The first R32 fixture as the bracket prop orders them (by match number).
        $r32 = $this->tournament->phases()->where('key', PhaseKey::RoundOf32->value)->firstOrFail()
            ->fixtures()->orderBy('match_number')->firstOrFail();
        $officialHome = $r32->home_team_id;

        // Predict a decisive scoreline for this unplayed, open knockout fixture.
        $this->actingAs($this->user)
            ->put(route('pools.predict.knockout', $pool->slug), ['predictions' => [[
                'fixture_id' => $r32->id,
                'home_goals' => 2,
                'away_goals' => 1,
            ]]])
            ->assertRedirect();

        $this->actingAs($this->user)
            ->get(route('pools.show', $pool->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('bracket.0.phase_key', 'round_of_32')
                ->where('bracket.0.fixtures.0.home_goals', null)   // the fixture itself is unplayed
                ->where('bracket.0.fixtures.0.prediction.home_goals', 2)
                ->where('bracket.0.fixtures.0.prediction.away_goals', 1)
                ->where('bracket.0.fixtures.0.prediction.advancing_team_id', $officialHome)
                ->where('bracket.0.fixtures.0.prediction.predicted_home', null) // phased never exposes predicted teams
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

    /** Settle the group stage and project the real Round-of-32 participants onto the fixtures. */
    private function projectRoundOf32(): void
    {
        $this->recordOfficialGroupResults($this->tournament, $this->seedOrderScores());
        (new OfficialBracketProjector)->project($this->tournament);
    }

    private function setPhaseKickoff(PhaseKey $key, \DateTimeInterface $when): void
    {
        $this->tournament->phases()->where('key', $key->value)->firstOrFail()
            ->fixtures()->update(['kicks_off_at' => $when]);
    }
}
