<?php

namespace Tests\Unit\Services\Predictions;

use App\Enums\PhaseKey;
use App\Enums\PredictionWindowStatus;
use App\Enums\ScoringStrategy;
use App\Models\Game;
use App\Models\Tournament;
use App\Services\Predictions\OfficialBracketProjector;
use App\Services\Predictions\PredictionWindowResolver;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithOfficialResults;
use Tests\Concerns\InteractsWithPredictions;
use Tests\TestCase;

class PredictionWindowResolverTest extends TestCase
{
    use InteractsWithOfficialResults;
    use InteractsWithPredictions;
    use RefreshDatabase;

    private Tournament $tournament;

    private PredictionWindowResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
        $this->resolver = new PredictionWindowResolver;
    }

    private function game(ScoringStrategy $strategy, \DateTimeInterface|string|null $lockAt): Game
    {
        return Game::factory()->create([
            'tournament_id' => $this->tournament->id,
            'scoring_strategy' => $strategy,
            'predictions_lock_at' => $lockAt,
        ]);
    }

    private function setPhaseKickoff(PhaseKey $key, \DateTimeInterface $when): void
    {
        $phase = $this->tournament->phases()->where('key', $key->value)->firstOrFail();
        $phase->fixtures()->update(['kicks_off_at' => $when]);
    }

    public function test_upfront_game_opens_every_phase_until_the_single_lock(): void
    {
        $open = $this->game(ScoringStrategy::UpfrontBracket, now()->addDay());
        $locked = $this->game(ScoringStrategy::UpfrontBracket, now()->subDay());

        $this->assertSame(
            [PredictionWindowStatus::Open],
            array_values(array_unique($this->resolver->windows($open), SORT_REGULAR)),
        );
        $this->assertSame(
            [PredictionWindowStatus::Locked],
            array_values(array_unique($this->resolver->windows($locked), SORT_REGULAR)),
        );
    }

    public function test_phased_group_window_follows_the_game_lock(): void
    {
        $open = $this->game(ScoringStrategy::PhasedBracket, now()->addDay());
        $locked = $this->game(ScoringStrategy::PhasedBracket, now()->subDay());

        $this->assertSame(PredictionWindowStatus::Open, $this->resolver->windows($open)[PhaseKey::Group->value]);
        $this->assertSame(PredictionWindowStatus::Locked, $this->resolver->windows($locked)[PhaseKey::Group->value]);
    }

    public function test_phased_knockout_round_is_pending_until_its_teams_are_known(): void
    {
        $game = $this->game(ScoringStrategy::PhasedBracket, now()->addDay());

        $windows = $this->resolver->windows($game);

        $this->assertSame(PredictionWindowStatus::Pending, $windows[PhaseKey::RoundOf32->value]);
        $this->assertSame(PredictionWindowStatus::Pending, $windows[PhaseKey::Final->value]);
    }

    public function test_phased_knockout_round_opens_once_projected_then_locks_at_kickoff(): void
    {
        $game = $this->game(ScoringStrategy::PhasedBracket, now()->subDay());

        // Record every group result and project — the Round of 32 now has real participants.
        $this->recordOfficialGroupResults($this->tournament, $this->seedOrderScores());
        (new OfficialBracketProjector)->project($this->tournament);

        // Teams known + kickoff in the future → open. Later rounds still pending.
        $this->setPhaseKickoff(PhaseKey::RoundOf32, now()->addDay());
        $windows = $this->resolver->windows($game);
        $this->assertSame(PredictionWindowStatus::Open, $windows[PhaseKey::RoundOf32->value]);
        $this->assertSame(PredictionWindowStatus::Pending, $windows[PhaseKey::RoundOf16->value]);

        // Kickoff now in the past → the round locks.
        $this->setPhaseKickoff(PhaseKey::RoundOf32, now()->subHour());
        $this->assertSame(
            PredictionWindowStatus::Locked,
            $this->resolver->windows($game)[PhaseKey::RoundOf32->value],
        );
    }

    public function test_phased_knockout_round_locks_the_buffer_before_its_first_kickoff(): void
    {
        config(['scoring.prediction_lock_buffer_minutes' => 60]);
        $game = $this->game(ScoringStrategy::PhasedBracket, now()->subDay());

        // Give the Round of 32 real participants so its window is no longer pending.
        $this->recordOfficialGroupResults($this->tournament, $this->seedOrderScores());
        (new OfficialBracketProjector)->project($this->tournament);

        $kickoff = Carbon::parse('2026-06-28 19:00:00', 'UTC');
        $this->setPhaseKickoff(PhaseKey::RoundOf32, $kickoff);

        // One minute before the buffer window closes (kickoff − 60m) → still open.
        Carbon::setTestNow($kickoff->copy()->subMinutes(61));
        $this->assertSame(
            PredictionWindowStatus::Open,
            $this->resolver->windows($game)[PhaseKey::RoundOf32->value],
        );

        // Exactly the buffer before kickoff → locked.
        Carbon::setTestNow($kickoff->copy()->subMinutes(60));
        $this->assertSame(
            PredictionWindowStatus::Locked,
            $this->resolver->windows($game)[PhaseKey::RoundOf32->value],
        );

        Carbon::setTestNow();
    }

    public function test_is_open_answers_for_a_single_phase(): void
    {
        $game = $this->game(ScoringStrategy::PhasedBracket, now()->addDay());

        $this->assertTrue($this->resolver->isOpen($game, PhaseKey::Group));
        $this->assertFalse($this->resolver->isOpen($game, PhaseKey::RoundOf32));
    }
}
