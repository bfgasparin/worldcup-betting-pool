<?php

namespace Tests\Feature\Console;

use App\Enums\BatchStatus;
use App\Models\Game;
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

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
        $this->game = $this->tournament->games()->firstOrFail();
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
            ->post(route('games.scores.approve', $this->game))
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

    private function admin(): User
    {
        $admin = User::factory()->create();
        config()->set('admin.emails', [$admin->email]);

        return $admin;
    }
}
