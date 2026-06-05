<?php

namespace Tests\Feature;

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

class PhasedPredictionControllerTest extends TestCase
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

    private function phasedPool(\DateTimeInterface|string|null $lockAt): Pool
    {
        return Pool::factory()->phasedBracket()->create([
            'tournament_id' => $this->tournament->id,
            'predictions_lock_at' => $lockAt,
        ]);
    }

    /** The acting user joins the pool — the prerequisite for predicting. */
    private function join(Pool $pool): Entry
    {
        return Entry::factory()->for($pool)->for($this->user)->create();
    }

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

    public function test_predict_page_shows_official_knockout_teams_and_phase_windows(): void
    {
        $pool = $this->phasedPool(now()->subDay()); // group already locked
        $this->join($pool);
        $this->projectRoundOf32();
        $this->setPhaseKickoff(PhaseKey::RoundOf32, now()->addDay());

        $this->actingAs($this->user)
            ->get(route('pools.predict.edit', $pool->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('pools/predict')
                ->where('pool.scoring_strategy', 'phased-bracket')
                ->where('pool.can_edit', false)            // the group window is shut
                ->where('bracket.0.phase_key', 'round_of_32')
                ->where('bracket.0.window', 'open')         // teams known, not kicked off
                ->whereNot('bracket.0.fixtures.0.home', null) // official participant, not a placeholder
                ->where('bracket.1.phase_key', 'round_of_16')
                ->where('bracket.1.window', 'pending')      // teams not known yet
                ->where('thirds', null)
            );
    }

    public function test_saving_a_knockout_round_persists_the_scoreline_without_cascading(): void
    {
        $pool = $this->phasedPool(now()->subDay());
        $this->join($pool);
        $this->projectRoundOf32();
        $this->setPhaseKickoff(PhaseKey::RoundOf32, now()->addDay());

        $r32 = $this->knockoutFixture($this->tournament, 'R32-1')->fresh();
        $officialHome = $r32->home_team_id;

        $this->actingAs($this->user)
            ->put(route('pools.predict.knockout', $pool->slug), ['predictions' => [[
                'fixture_id' => $r32->id,
                'home_goals' => 2,
                'away_goals' => 1,
            ]]])
            ->assertRedirect(route('pools.predict.edit', $pool->slug));

        $entry = Entry::where('pool_id', $pool->id)->where('user_id', $this->user->id)->firstOrFail();

        $this->assertDatabaseHas('knockout_predictions', [
            'entry_id' => $entry->id,
            'fixture_id' => $r32->id,
            'home_goals' => 2,
            'away_goals' => 1,
            'advancing_team_id' => $officialHome, // derived from the decisive score + official slot
            'predicted_home_team_id' => $officialHome,
            'predicted_away_team_id' => $r32->away_team_id,
        ]);

        // No cascade: phased predictions stand alone, so only the one saved row exists.
        $this->assertSame(1, $entry->knockoutPredictions()->count());
    }

    public function test_saving_a_pending_knockout_round_is_forbidden(): void
    {
        $pool = $this->phasedPool(now()->addDay()); // group open, but no rounds projected
        $this->join($pool);
        $r16 = $this->knockoutFixture($this->tournament, 'R16-1');

        $this->actingAs($this->user)
            ->put(route('pools.predict.knockout', $pool->slug), ['predictions' => [[
                'fixture_id' => $r16->id,
                'home_goals' => 1,
                'away_goals' => 0,
            ]]])
            ->assertForbidden();

        $this->assertDatabaseCount('knockout_predictions', 0);
    }

    public function test_saving_a_round_after_kickoff_is_forbidden(): void
    {
        $pool = $this->phasedPool(now()->subDay());
        $this->join($pool);
        $this->projectRoundOf32();
        $this->setPhaseKickoff(PhaseKey::RoundOf32, now()->subHour()); // round has kicked off

        $r32 = $this->knockoutFixture($this->tournament, 'R32-1');

        $this->actingAs($this->user)
            ->put(route('pools.predict.knockout', $pool->slug), ['predictions' => [[
                'fixture_id' => $r32->id,
                'home_goals' => 1,
                'away_goals' => 0,
            ]]])
            ->assertForbidden();
    }

    public function test_group_save_does_not_create_knockout_rows(): void
    {
        $pool = $this->phasedPool(now()->addDay()); // group open
        $this->join($pool);
        $fixtures = $this->tournament->groups()->where('name', 'A')->firstOrFail()
            ->fixtures()->orderBy('match_number')->get();

        $payload = ['predictions' => $fixtures->map(fn ($fixture): array => [
            'fixture_id' => $fixture->id,
            'home_goals' => 1,
            'away_goals' => 0,
        ])->all()];

        $this->actingAs($this->user)
            ->put(route('pools.predict.group', $pool->slug), $payload)
            ->assertRedirect(route('pools.predict.edit', $pool->slug));

        $entry = Entry::where('pool_id', $pool->id)->where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame(6, $entry->groupPredictions()->count());
        // Phased pools never cascade group scores into a self-derived bracket.
        $this->assertSame(0, $entry->knockoutPredictions()->count());
    }

    public function test_a_phased_draw_validates_the_advancing_pick_against_official_teams(): void
    {
        $pool = $this->phasedPool(now()->subDay());
        $this->join($pool);
        $this->projectRoundOf32();
        $this->setPhaseKickoff(PhaseKey::RoundOf32, now()->addDay());

        $r32 = $this->knockoutFixture($this->tournament, 'R32-1')->fresh();
        $notInMatch = $this->tournament->groups()->where('name', 'B')->firstOrFail()
            ->teams()->wherePivot('position', 1)->firstOrFail()->id; // a group winner, not in this tie

        $this->actingAs($this->user)
            ->put(route('pools.predict.knockout', $pool->slug), ['predictions' => [[
                'fixture_id' => $r32->id,
                'home_goals' => 1,
                'away_goals' => 1,
                'advancing_team_id' => $notInMatch,
            ]]])
            ->assertSessionHasErrors('predictions.0.advancing_team_id');
    }
}
