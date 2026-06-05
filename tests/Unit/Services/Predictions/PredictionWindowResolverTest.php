<?php

namespace Tests\Unit\Services\Predictions;

use App\Enums\PhaseKey;
use App\Enums\PredictionWindowStatus;
use App\Enums\ScoringStrategy;
use App\Models\Pool;
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

    private function pool(ScoringStrategy $strategy, \DateTimeInterface|string|null $lockAt): Pool
    {
        return Pool::factory()->create([
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

    public function test_upfront_pool_opens_every_phase_until_the_single_lock(): void
    {
        $open = $this->pool(ScoringStrategy::UpfrontBracket, now()->addDay());
        $locked = $this->pool(ScoringStrategy::UpfrontBracket, now()->subDay());

        $this->assertSame(
            [PredictionWindowStatus::Open],
            array_values(array_unique($this->resolver->windows($open), SORT_REGULAR)),
        );
        $this->assertSame(
            [PredictionWindowStatus::Locked],
            array_values(array_unique($this->resolver->windows($locked), SORT_REGULAR)),
        );
    }

    public function test_phased_group_window_follows_the_pool_lock(): void
    {
        $open = $this->pool(ScoringStrategy::PhasedBracket, now()->addDay());
        $locked = $this->pool(ScoringStrategy::PhasedBracket, now()->subDay());

        $this->assertSame(PredictionWindowStatus::Open, $this->resolver->windows($open)[PhaseKey::Group->value]);
        $this->assertSame(PredictionWindowStatus::Locked, $this->resolver->windows($locked)[PhaseKey::Group->value]);
    }

    public function test_phased_knockout_round_is_pending_until_its_teams_are_known(): void
    {
        $pool = $this->pool(ScoringStrategy::PhasedBracket, now()->addDay());

        $windows = $this->resolver->windows($pool);

        $this->assertSame(PredictionWindowStatus::Pending, $windows[PhaseKey::RoundOf32->value]);
        $this->assertSame(PredictionWindowStatus::Pending, $windows[PhaseKey::Final->value]);
    }

    public function test_phased_knockout_round_opens_once_projected_then_locks_at_kickoff(): void
    {
        $pool = $this->pool(ScoringStrategy::PhasedBracket, now()->subDay());

        // Record every group result and project — the Round of 32 now has real participants.
        $this->recordOfficialGroupResults($this->tournament, $this->seedOrderScores());
        (new OfficialBracketProjector)->project($this->tournament);

        // Teams known + kickoff in the future → open. Later rounds still pending.
        $this->setPhaseKickoff(PhaseKey::RoundOf32, now()->addDay());
        $windows = $this->resolver->windows($pool);
        $this->assertSame(PredictionWindowStatus::Open, $windows[PhaseKey::RoundOf32->value]);
        $this->assertSame(PredictionWindowStatus::Pending, $windows[PhaseKey::RoundOf16->value]);

        // Kickoff now in the past → the round locks.
        $this->setPhaseKickoff(PhaseKey::RoundOf32, now()->subHour());
        $this->assertSame(
            PredictionWindowStatus::Locked,
            $this->resolver->windows($pool)[PhaseKey::RoundOf32->value],
        );
    }

    public function test_phased_round_stays_pending_until_an_unresolved_thirds_tie_is_ordered(): void
    {
        $pool = $this->pool(ScoringStrategy::PhasedBracket, now()->subDay());

        // Results that tie all twelve thirds, with no ordering recorded — the best-third R32 slots
        // cannot be projected, so the round cannot open.
        $this->recordOfficialGroupResults($this->tournament, $this->seedOrderScores(), resolveTies: false);
        (new OfficialBracketProjector)->project($this->tournament);
        $this->setPhaseKickoff(PhaseKey::RoundOf32, now()->addDay());

        $this->assertSame(
            PredictionWindowStatus::Pending,
            $this->resolver->windows($pool)[PhaseKey::RoundOf32->value],
        );

        // Once an admin orders the tied thirds, the round's teams are all known and it opens.
        $this->resolveProjectedTies($this->tournament);
        (new OfficialBracketProjector)->project($this->tournament);

        $this->assertSame(
            PredictionWindowStatus::Open,
            $this->resolver->windows($pool)[PhaseKey::RoundOf32->value],
        );
    }

    public function test_phased_knockout_round_locks_the_buffer_before_its_first_kickoff(): void
    {
        config(['scoring.prediction_lock_buffer_minutes' => 60]);
        $pool = $this->pool(ScoringStrategy::PhasedBracket, now()->subDay());

        // Give the Round of 32 real participants so its window is no longer pending.
        $this->recordOfficialGroupResults($this->tournament, $this->seedOrderScores());
        (new OfficialBracketProjector)->project($this->tournament);

        $kickoff = Carbon::parse('2026-06-28 19:00:00', 'UTC');
        $this->setPhaseKickoff(PhaseKey::RoundOf32, $kickoff);

        // One minute before the buffer window closes (kickoff − 60m) → still open.
        Carbon::setTestNow($kickoff->copy()->subMinutes(61));
        $this->assertSame(
            PredictionWindowStatus::Open,
            $this->resolver->windows($pool)[PhaseKey::RoundOf32->value],
        );

        // Exactly the buffer before kickoff → locked.
        Carbon::setTestNow($kickoff->copy()->subMinutes(60));
        $this->assertSame(
            PredictionWindowStatus::Locked,
            $this->resolver->windows($pool)[PhaseKey::RoundOf32->value],
        );

        Carbon::setTestNow();
    }

    public function test_is_open_answers_for_a_single_phase(): void
    {
        $pool = $this->pool(ScoringStrategy::PhasedBracket, now()->addDay());

        $this->assertTrue($this->resolver->isOpen($pool, PhaseKey::Group));
        $this->assertFalse($this->resolver->isOpen($pool, PhaseKey::RoundOf32));
    }
}
