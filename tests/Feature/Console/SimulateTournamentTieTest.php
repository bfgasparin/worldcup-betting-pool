<?php

namespace Tests\Feature\Console;

use App\Enums\BatchStatus;
use App\Models\Entry;
use App\Models\Pool;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Predictions\TieResolutionState;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulateTournamentTieTest extends TestCase
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

    public function test_tie_thirds_stages_an_unresolved_best_thirds_tie(): void
    {
        $this->artisan('tournament:simulate', ['--tie' => 'thirds', '--players' => 2, '--reset' => true])
            ->assertSuccessful();

        $batch = $this->tournament->scoreBatches()->where('status', BatchStatus::Open)->firstOrFail();
        $this->assertSame(72, $batch->proposals()->count()); // 12 groups x 6 fixtures

        // No official ordering recorded -> the tie is genuinely unresolved and awaiting the admin.
        $this->assertDatabaseCount('tournament_group_orderings', 0);

        $state = (new TieResolutionState)->forTournament($this->tournament, $batch);
        $this->assertNotEmpty($state->thirds);
        $this->assertTrue($state->blocked());

        // Approval is blocked until the admin orders the tied thirds.
        $this->actingAs($this->admin())
            ->post(route('pools.scores.approve', $this->pool))
            ->assertSessionHasErrors('ties');
    }

    public function test_tie_group_stages_an_unresolved_within_group_tie(): void
    {
        $this->artisan('tournament:simulate', ['--tie' => 'group', '--players' => 2, '--reset' => true])
            ->assertSuccessful();

        $batch = $this->tournament->scoreBatches()->where('status', BatchStatus::Open)->firstOrFail();
        $state = (new TieResolutionState)->forTournament($this->tournament, $batch);

        $this->assertNotEmpty($state->groupTies);
        $this->assertTrue($state->blocked());
    }

    public function test_an_unknown_tie_value_fails_and_stages_nothing(): void
    {
        $this->artisan('tournament:simulate', ['--tie' => 'bogus'])->assertFailed();

        $this->assertDatabaseCount('score_batches', 0);
    }

    public function test_player_tie_thirds_leaves_the_me_user_with_an_unresolved_thirds_tie(): void
    {
        $this->artisan('tournament:simulate', ['--player-tie' => 'thirds', '--players' => 2, '--predict-only' => true, '--reset' => true])
            ->assertSuccessful();

        $entry = $this->meEntryInUpfrontPool();
        $state = (new TieResolutionState)->forEntry($entry);

        $this->assertNotEmpty($state->thirds);
        $this->assertTrue($state->blocked());

        // Auto-resolution is disabled for everyone, so no default entry orderings were recorded —
        // the --me deliberate tie and any demo player's natural tie are left for a human.
        $this->assertDatabaseCount('entry_group_orderings', 0);
    }

    public function test_player_tie_group_leaves_the_me_user_with_an_unresolved_group_tie(): void
    {
        $this->artisan('tournament:simulate', ['--player-tie' => 'group', '--players' => 2, '--predict-only' => true, '--reset' => true])
            ->assertSuccessful();

        $entry = $this->meEntryInUpfrontPool();
        $state = (new TieResolutionState)->forEntry($entry);

        $this->assertNotEmpty($state->groupTies);
        $this->assertTrue($state->blocked());
        $this->assertDatabaseCount('entry_group_orderings', 0);
    }

    public function test_an_unknown_player_tie_value_fails_and_predicts_nothing(): void
    {
        $this->artisan('tournament:simulate', ['--player-tie' => 'bogus'])->assertFailed();

        $this->assertDatabaseCount('group_predictions', 0);
    }

    private function meEntryInUpfrontPool(): Entry
    {
        $upfront = $this->tournament->pools()->where('slug', 'world-cup-2026-ffa')->firstOrFail();
        $me = User::firstWhere('email', 'test@example.com');

        return $upfront->entries()->where('user_id', $me->id)->firstOrFail();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        config()->set('admin.emails', [$admin->email]);

        return $admin;
    }
}
