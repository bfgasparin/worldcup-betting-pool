<?php

namespace Tests\Feature\Console;

use App\Enums\FixtureStatus;
use App\Enums\TournamentStatus;
use App\Models\Entry;
use App\Models\GroupPrediction;
use App\Models\KnockoutPrediction;
use App\Models\Pool;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Predictions\TieResolutionState;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulateTournamentTest extends TestCase
{
    use RefreshDatabase;

    private Tournament $tournament;

    private Pool $pool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
        $this->pool = $this->tournament->pools()->firstOrFail();
    }

    public function test_it_fails_when_the_tournament_is_missing(): void
    {
        $this->artisan('tournament:simulate', ['slug' => 'nope'])->assertFailed();
    }

    public function test_it_simulates_a_full_scored_tournament(): void
    {
        $this->artisan('tournament:simulate', ['--players' => 3, '--through' => 'final'])
            ->assertSuccessful();

        // 3 demo players + the --me user.
        $this->assertSame(4, $this->pool->entries()->count());

        $demo = $this->pool->entries()
            ->whereHas('user', fn ($query) => $query->where('email', 'sim-player-1@ffa.test'))
            ->firstOrFail();
        $this->assertSame(72, $demo->groupPredictions()->count());
        $this->assertSame(32, $demo->knockoutPredictions()->count());

        // Every match is played, the champion is decided, and the board is scored + ranked.
        $this->assertSame(104, $this->tournament->fixtures()->where('status', FixtureStatus::Finished)->count());
        $this->assertNotNull($this->tournament->fixtures()->where('match_number', 104)->value('winner_team_id'));

        $this->pool->entries->each(function (Entry $entry): void {
            $this->assertNotNull($entry->total_points);
            $this->assertNotNull($entry->rank);
        });

        // No override is written; the schedule-derived lock governs, and with the simulated clock
        // past the first kickoff the window is closed. The lifecycle reflects completion.
        $this->assertNull($this->pool->fresh()->predictions_lock_at);
        $this->travelTo($this->tournament->firstGroupKickoffAt()->addDay(), function (): void {
            $this->assertFalse($this->pool->fresh()->acceptsPredictions());
        });
        $this->assertSame(TournamentStatus::Completed, $this->tournament->fresh()->status);

        // Staged per-round snapshots leave a movement baseline.
        $this->assertGreaterThan(0, $this->pool->entries()->whereNotNull('previous_rank')->count());
    }

    public function test_by_default_every_upfront_entry_has_a_fully_resolved_bracket(): void
    {
        // The default has no human to break ties, so it must auto-resolve every player's standings
        // — including a thirds tie hidden behind a within-group tie — leaving no bracket blocked.
        $this->artisan('tournament:simulate', ['--players' => 3, '--predict-only' => true])
            ->assertSuccessful();

        $exercisedThirdsCut = false;

        $this->pool->entries->each(function (Entry $entry) use (&$exercisedThirdsCut): void {
            $state = (new TieResolutionState)->forEntry($entry);

            $this->assertFalse(
                $state->blocked(),
                "Entry {$entry->id} should have a fully-resolved bracket by default.",
            );

            // A non-empty straddling run means this entry's bracket hid a best-thirds cut tie that
            // the default had to resolve (here, behind a within-group tie) — the hard case.
            if ($state->thirds !== []) {
                $exercisedThirdsCut = true;
            }
        });

        // Guard the invariant against going vacuous: the fixed default world must actually present
        // the hard case (a best-thirds cut tie the no-human default resolves), not a tie-free board
        // that would pass for free if the resolver regressed. One player's bracket reliably does.
        $this->assertTrue(
            $exercisedThirdsCut,
            'Expected the default simulation to exercise a straddling best-thirds cut, but none did.',
        );
    }

    public function test_it_also_simulates_the_phased_bracket_pool(): void
    {
        $this->artisan('tournament:simulate', ['--players' => 3, '--through' => 'final'])
            ->assertSuccessful();

        $phased = $this->tournament->pools()->where('slug', 'world-cup-2026-brothers')->firstOrFail();

        // The same roster joins the phased pool (3 demo players + the --me user).
        $this->assertSame(4, $phased->entries()->count());

        $demo = $phased->entries()
            ->whereHas('user', fn ($query) => $query->where('email', 'sim-player-1@ffa.test'))
            ->firstOrFail();

        // Group predictions, plus a phased knockout pick for every knockout fixture (all 32, since
        // the whole bracket was played and projected), recorded against the OFFICIAL teams — not a
        // self-derived bracket.
        $this->assertSame(72, $demo->groupPredictions()->count());
        $this->assertSame(32, $demo->knockoutPredictions()->count());
        $this->assertSame(32, $demo->knockoutPredictions()->whereNotNull('predicted_home_team_id')->count());
        $this->assertSame(32, $demo->knockoutPredictions()->whereNotNull('advancing_team_id')->count());

        // The phased board is scored and ranked, with points actually awarded on the knockout picks.
        $phased->entries->each(function (Entry $entry): void {
            $this->assertNotNull($entry->total_points);
            $this->assertNotNull($entry->rank);
        });
        $this->assertGreaterThan(0, $demo->knockoutPredictions()->whereNotNull('points_awarded')->count());
    }

    public function test_it_generates_some_drawn_knockout_predictions(): void
    {
        $this->artisan('tournament:simulate', ['--players' => 4, '--through' => 'final'])
            ->assertSuccessful();

        $phased = $this->tournament->pools()->where('slug', 'world-cup-2026-brothers')->firstOrFail();

        // Some knockout picks are level scorelines (e.g. 1–1) decided on penalties — the player still
        // names who advances — so drawn-knockout features are exercised. True for both pools.
        foreach ([$this->pool, $phased] as $pool) {
            $draws = KnockoutPrediction::query()
                ->whereIn('entry_id', $pool->entries()->pluck('id'))
                ->whereColumn('home_goals', 'away_goals')
                ->whereNotNull('advancing_team_id')
                ->count();

            $this->assertGreaterThan(0, $draws, "Expected drawn knockout predictions for {$pool->slug}.");
        }
    }

    public function test_through_group_leaves_the_knockout_stage_unplayed(): void
    {
        $this->artisan('tournament:simulate', ['--players' => 2, '--through' => 'group'])
            ->assertSuccessful();

        $this->assertSame(72, $this->tournament->groupFixtures()->where('status', FixtureStatus::Finished)->count());

        $final = $this->tournament->fixtures()->where('match_number', 104)->firstOrFail();
        $this->assertNull($final->home_goals);
        $this->assertSame(FixtureStatus::Scheduled, $final->status);

        // Group results still score the board.
        $this->assertNotNull($this->pool->entries()->first()->total_points);
    }

    public function test_reset_clears_a_prior_simulation(): void
    {
        $this->artisan('tournament:simulate', ['--players' => 2, '--through' => 'final'])->assertSuccessful();
        $this->assertNotNull($this->tournament->fixtures()->where('match_number', 104)->value('home_goals'));

        $this->artisan('tournament:simulate', ['--players' => 2, '--through' => 'group', '--reset' => true])
            ->assertSuccessful();

        $final = $this->tournament->fixtures()->where('match_number', 104)->firstOrFail();
        $this->assertNull($final->home_goals);
        $this->assertNull($final->home_team_id);
        $this->assertSame(FixtureStatus::Scheduled, $final->status);
    }

    public function test_predict_only_sets_up_predictions_without_results(): void
    {
        $this->artisan('tournament:simulate', ['--players' => 2, '--predict-only' => true])
            ->assertSuccessful();

        $demo = $this->pool->entries()
            ->whereHas('user', fn ($query) => $query->where('email', 'sim-player-1@ffa.test'))
            ->firstOrFail();
        $this->assertSame(72, $demo->groupPredictions()->count());

        // No official results were filled and no fixture was settled.
        $this->assertSame(0, $this->tournament->fixtures()->whereNotNull('home_goals')->count());
        $this->assertSame(0, $this->tournament->fixtures()->where('status', FixtureStatus::Finished)->count());
        $this->assertDatabaseCount('score_batches', 0);

        // No override is written; the lock is schedule-derived. Predict-only doesn't advance the
        // clock, so before the first kickoff the window is (correctly) still open — closure is
        // driven by the clock passing the derived lock. No match has kicked off, so the
        // fixture-derived status is still Upcoming.
        $this->assertNull($this->pool->fresh()->predictions_lock_at);
        $this->travelTo($this->tournament->firstGroupKickoffAt()->addDay(), function (): void {
            $this->assertFalse($this->pool->fresh()->acceptsPredictions());
        });
        $this->assertSame(TournamentStatus::Upcoming, $this->tournament->fresh()->status);
    }

    public function test_it_clears_a_stale_predictions_lock_override(): void
    {
        // A far-future override left by an older command version (or a prior run anchored to a
        // stale dev clock) would otherwise keep the window open against the derived schedule lock.
        $this->pool->update(['predictions_lock_at' => now()->addMonths(2)]);

        $this->artisan('tournament:simulate', ['--players' => 1, '--through' => 'group'])
            ->assertSuccessful();

        // The override is cleared every run, so the schedule-derived lock governs.
        $this->assertNull($this->pool->fresh()->predictions_lock_at);

        $this->travelTo($this->tournament->firstGroupKickoffAt()->addDay(), function (): void {
            $this->assertFalse($this->pool->fresh()->acceptsPredictions());
        });
    }

    public function test_until_plays_results_only_up_to_a_date(): void
    {
        $until = '2026-06-15 00:00';

        $this->artisan('tournament:simulate', ['--players' => 2, '--until' => $until])
            ->assertSuccessful();

        // Some — but not all — group matches have been played (mid-phase).
        $finishedGroup = $this->tournament->groupFixtures()->where('status', FixtureStatus::Finished)->count();
        $this->assertGreaterThan(0, $finishedGroup);
        $this->assertLessThan(72, $finishedGroup);

        // The knockout stage hasn't started and the final is untouched.
        $this->assertSame(0, $this->tournament->knockoutFixtures()->where('status', FixtureStatus::Finished)->count());
        $final = $this->tournament->fixtures()->where('match_number', 104)->firstOrFail();
        $this->assertNull($final->home_goals);
        $this->assertSame(FixtureStatus::Scheduled, $final->status);

        // No fixture whose kickoff has passed is still scheduled — each is live or finished.
        $this->assertSame(0, $this->tournament->fixtures()
            ->where('status', FixtureStatus::Scheduled)
            ->where('kicks_off_at', '<=', $until)
            ->count());

        $this->assertSame(TournamentStatus::InProgress, $this->tournament->fresh()->status);
    }

    public function test_existing_predictions_are_not_overwritten(): void
    {
        $user = User::factory()->create(['email' => 'keep-me@example.com']);
        $entry = $this->pool->entries()->create([
            'user_id' => $user->id,
        ]);
        $fixture = $this->tournament->groupFixtures()->orderBy('match_number')->first();
        GroupPrediction::create([
            'entry_id' => $entry->id,
            'fixture_id' => $fixture->id,
            'home_goals' => 5,
            'away_goals' => 0,
        ]);

        $this->artisan('tournament:simulate', [
            '--players' => 1,
            '--me' => 'keep-me@example.com',
            '--through' => 'group',
        ])->assertSuccessful();

        $prediction = $entry->groupPredictions()->where('fixture_id', $fixture->id)->firstOrFail();
        $this->assertSame(5, $prediction->home_goals);
        $this->assertSame(0, $prediction->away_goals);
        // The entry was left as-is, so no extra predictions were generated for it.
        $this->assertSame(1, $entry->groupPredictions()->count());
    }

    public function test_me_skip_leaves_the_me_user_empty_in_the_named_pool(): void
    {
        $ffa = $this->tournament->pools()->where('slug', 'world-cup-2026-ffa')->firstOrFail();
        $brothers = $this->tournament->pools()->where('slug', 'world-cup-2026-brothers')->firstOrFail();

        $this->artisan('tournament:simulate', [
            '--players' => 2,
            '--me' => 'me@example.com',
            '--predict-only' => true,
            '--me-skip' => 'world-cup-2026-brothers',
        ])->assertSuccessful();

        $me = User::firstWhere('email', 'me@example.com');
        $this->assertNotNull($me);

        // The --me user joined both pools (the entry exists so the predict page is reachable)...
        $ffaEntry = $ffa->entries()->where('user_id', $me->id)->firstOrFail();
        $brothersEntry = $brothers->entries()->where('user_id', $me->id)->firstOrFail();

        // ...but is filled only in the sibling, leaving the skipped pool empty so the import
        // suggestion fires there with the sibling as the source.
        $this->assertSame(72, $ffaEntry->groupPredictions()->count());
        $this->assertSame(0, $brothersEntry->groupPredictions()->count());
        $this->assertSame(0, $brothersEntry->knockoutPredictions()->count());

        // Demo players still fill the skipped pool — only the --me user is left empty there.
        $demo = $brothers->entries()
            ->whereHas('user', fn ($query) => $query->where('email', 'sim-player-1@ffa.test'))
            ->firstOrFail();
        $this->assertSame(72, $demo->groupPredictions()->count());
    }

    public function test_me_skip_also_matches_a_pool_by_source(): void
    {
        $brothers = $this->tournament->pools()->where('slug', 'world-cup-2026-brothers')->firstOrFail();

        $this->artisan('tournament:simulate', [
            '--players' => 1,
            '--me' => 'me@example.com',
            '--predict-only' => true,
            '--me-skip' => $brothers->source,
        ])->assertSuccessful();

        $me = User::firstWhere('email', 'me@example.com');
        $brothersEntry = $brothers->entries()->where('user_id', $me->id)->firstOrFail();
        $this->assertSame(0, $brothersEntry->groupPredictions()->count());
    }

    public function test_me_skip_fails_on_an_unknown_pool(): void
    {
        $this->artisan('tournament:simulate', [
            '--players' => 1,
            '--predict-only' => true,
            '--me-skip' => 'no-such-pool',
        ])->assertFailed();
    }
}
