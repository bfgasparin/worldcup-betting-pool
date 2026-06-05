<?php

namespace Tests\Feature;

use App\Enums\ScoringStrategy;
use App\Enums\TournamentStatus;
use App\Models\Entry;
use App\Models\Pool;
use App\Models\Tournament;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PoolRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pool_casts_resolve_to_enum_and_array(): void
    {
        $pool = Pool::factory()->create();

        $this->assertSame(ScoringStrategy::UpfrontBracket, $pool->scoring_strategy);
        $this->assertIsArray($pool->scoring_config);
        $this->assertSame(20, $pool->scoring_config['group']['exact_score']);
    }

    public function test_pool_belongs_to_a_tournament(): void
    {
        $tournament = Tournament::factory()->create();
        $pool = Pool::factory()->for($tournament)->create();

        $this->assertTrue($pool->tournament->is($tournament));
    }

    public function test_pool_has_entries(): void
    {
        $pool = Pool::factory()->create();
        Entry::factory()->count(2)->for($pool)->create();

        $this->assertCount(2, $pool->entries);
    }

    public function test_predicts_knockout_bracket_for_the_upfront_bracket_strategy(): void
    {
        $pool = Pool::factory()->create(['scoring_strategy' => ScoringStrategy::UpfrontBracket]);

        $this->assertTrue($pool->predictsKnockoutBracket());
    }

    public function test_accepts_predictions_before_the_lock_time(): void
    {
        $pool = Pool::factory()->create([
            'predictions_lock_at' => now()->addDay(),
        ]);

        $this->assertTrue($pool->acceptsPredictions());
    }

    public function test_does_not_accept_predictions_after_the_lock_time(): void
    {
        $pool = Pool::factory()->create([
            'predictions_lock_at' => now()->subMinute(),
        ]);

        $this->assertFalse($pool->acceptsPredictions());
    }

    public function test_prediction_window_is_independent_of_the_tournaments_lifecycle_status(): void
    {
        // The pool's prediction window alone decides; the competition's lifecycle status must not affect it.
        foreach (TournamentStatus::cases() as $status) {
            $tournament = Tournament::factory()->create(['status' => $status]);

            $open = Pool::factory()->for($tournament)->create(['predictions_lock_at' => now()->addWeek()]);
            $this->assertTrue($open->acceptsPredictions());

            $closed = Pool::factory()->for($tournament)->create(['predictions_lock_at' => now()->subMinute()]);
            $this->assertFalse($closed->acceptsPredictions());
        }
    }

    public function test_lock_derives_from_the_first_group_kickoff_minus_the_buffer_without_an_override(): void
    {
        config(['scoring.prediction_lock_buffer_minutes' => 60]);
        $this->seed(WorldCup2026Seeder::class);
        $tournament = Tournament::firstOrFail();
        $pool = Pool::factory()->for($tournament)->create(['predictions_lock_at' => null]);

        $expected = $tournament->firstGroupKickoffAt()->copy()->subMinutes(60);

        $this->assertTrue($expected->equalTo($pool->predictionsLockAt()));
    }

    public function test_explicit_override_wins_verbatim_and_ignores_the_buffer(): void
    {
        config(['scoring.prediction_lock_buffer_minutes' => 60]);
        $this->seed(WorldCup2026Seeder::class);
        $tournament = Tournament::firstOrFail();
        $override = $tournament->firstGroupKickoffAt()->copy()->subDays(3);
        $pool = Pool::factory()->for($tournament)->create(['predictions_lock_at' => $override]);

        $this->assertTrue($override->equalTo($pool->predictionsLockAt()));
    }

    public function test_the_buffer_is_applied_to_the_derived_lock(): void
    {
        config(['scoring.prediction_lock_buffer_minutes' => 180]);
        $this->seed(WorldCup2026Seeder::class);
        $tournament = Tournament::firstOrFail();
        $pool = Pool::factory()->for($tournament)->create(['predictions_lock_at' => null]);

        $expected = $tournament->firstGroupKickoffAt()->copy()->subMinutes(180);

        $this->assertTrue($expected->equalTo($pool->predictionsLockAt()));
    }

    public function test_predictions_are_closed_without_an_override_or_any_group_kickoff(): void
    {
        // A bare factory tournament has no group fixtures, so there is nothing to derive from.
        $pool = Pool::factory()->create(['predictions_lock_at' => null]);

        $this->assertNull($pool->tournament->firstGroupKickoffAt());
        $this->assertNull($pool->predictionsLockAt());
        $this->assertFalse($pool->acceptsPredictions());
    }

    public function test_first_group_kickoff_at_returns_the_earliest_group_fixture_kickoff(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $tournament = Tournament::firstOrFail();

        $this->assertTrue(
            Carbon::parse('2026-06-11 19:00:00', 'UTC')->equalTo($tournament->firstGroupKickoffAt()),
        );
    }
}
