<?php

namespace Tests\Feature\Console;

use App\Enums\EntryStatus;
use App\Enums\FixtureStatus;
use App\Enums\TournamentStatus;
use App\Models\Entry;
use App\Models\GroupPrediction;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulateTournamentTest extends TestCase
{
    use RefreshDatabase;

    private Tournament $tournament;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
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
        $this->assertSame(4, $this->tournament->entries()->count());

        $demo = $this->tournament->entries()
            ->whereHas('user', fn ($query) => $query->where('email', 'sim-player-1@ffa.test'))
            ->firstOrFail();
        $this->assertSame(72, $demo->groupPredictions()->count());
        $this->assertSame(32, $demo->knockoutPredictions()->count());

        // Every match is played, the champion is decided, and the board is scored + ranked.
        $this->assertSame(104, $this->tournament->fixtures()->where('status', FixtureStatus::Finished)->count());
        $this->assertNotNull($this->tournament->fixtures()->where('match_number', 104)->value('winner_team_id'));

        $this->tournament->entries->each(function (Entry $entry): void {
            $this->assertNotNull($entry->total_points);
            $this->assertNotNull($entry->rank);
        });

        // Predictions are closed and the lifecycle reflects completion.
        $this->assertTrue($this->tournament->fresh()->predictions_lock_at->isPast());
        $this->assertSame(TournamentStatus::Completed, $this->tournament->fresh()->status);

        // Staged per-round snapshots leave a movement baseline.
        $this->assertGreaterThan(0, $this->tournament->entries()->whereNotNull('previous_rank')->count());
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
        $this->assertNotNull($this->tournament->entries()->first()->total_points);
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

    public function test_existing_predictions_are_not_overwritten(): void
    {
        $user = User::factory()->create(['email' => 'keep-me@example.com']);
        $entry = $this->tournament->entries()->create([
            'user_id' => $user->id,
            'status' => EntryStatus::Submitted,
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
}
