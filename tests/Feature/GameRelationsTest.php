<?php

namespace Tests\Feature;

use App\Enums\ScoringStrategy;
use App\Enums\TournamentStatus;
use App\Models\Entry;
use App\Models\Game;
use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_game_casts_resolve_to_enum_and_array(): void
    {
        $game = Game::factory()->create();

        $this->assertSame(ScoringStrategy::UpfrontBracket, $game->scoring_strategy);
        $this->assertIsArray($game->scoring_config);
        $this->assertSame(20, $game->scoring_config['group']['exact_score']);
    }

    public function test_game_belongs_to_a_tournament(): void
    {
        $tournament = Tournament::factory()->create();
        $game = Game::factory()->for($tournament)->create();

        $this->assertTrue($game->tournament->is($tournament));
    }

    public function test_game_has_entries(): void
    {
        $game = Game::factory()->create();
        Entry::factory()->count(2)->for($game)->create();

        $this->assertCount(2, $game->entries);
    }

    public function test_predicts_knockout_bracket_for_the_upfront_bracket_strategy(): void
    {
        $game = Game::factory()->create(['scoring_strategy' => ScoringStrategy::UpfrontBracket]);

        $this->assertTrue($game->predictsKnockoutBracket());
    }

    public function test_accepts_predictions_before_the_lock_time(): void
    {
        $game = Game::factory()->create([
            'predictions_lock_at' => now()->addDay(),
        ]);

        $this->assertTrue($game->acceptsPredictions());
    }

    public function test_does_not_accept_predictions_after_the_lock_time(): void
    {
        $game = Game::factory()->create([
            'predictions_lock_at' => now()->subMinute(),
        ]);

        $this->assertFalse($game->acceptsPredictions());
    }

    public function test_prediction_window_is_independent_of_the_tournaments_lifecycle_status(): void
    {
        // The game's prediction window alone decides; the competition's lifecycle status must not affect it.
        foreach (TournamentStatus::cases() as $status) {
            $tournament = Tournament::factory()->create(['status' => $status]);

            $open = Game::factory()->for($tournament)->create(['predictions_lock_at' => now()->addWeek()]);
            $this->assertTrue($open->acceptsPredictions());

            $closed = Game::factory()->for($tournament)->create(['predictions_lock_at' => now()->subMinute()]);
            $this->assertFalse($closed->acceptsPredictions());
        }
    }
}
