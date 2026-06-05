<?php

namespace Tests\Unit\Services\Pools;

use App\Enums\PhaseKey;
use App\Models\Entry;
use App\Models\Pool;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Pools\PredictionAttention;
use App\Services\Predictions\OfficialBracketProjector;
use App\Services\Predictions\TieResolutionState;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOfficialResults;
use Tests\Concerns\InteractsWithPredictions;
use Tests\TestCase;

class PredictionAttentionTest extends TestCase
{
    use InteractsWithOfficialResults;
    use InteractsWithPredictions;
    use RefreshDatabase;

    private Tournament $tournament;

    private Pool $upfront;

    private Pool $phased;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
        $this->upfront = $this->tournament->pools()->where('slug', 'world-cup-2026-ffa')->firstOrFail();
        $this->phased = $this->tournament->pools()->where('slug', 'world-cup-2026-brothers')->firstOrFail();
    }

    private function attention(): PredictionAttention
    {
        return new PredictionAttention;
    }

    private function entryIn(Pool $pool): Entry
    {
        return Entry::factory()->for($pool)->for(User::factory())->create();
    }

    private function setPhaseKickoff(PhaseKey $key, \DateTimeInterface $when): void
    {
        $phase = $this->tournament->phases()->where('key', $key->value)->firstOrFail();
        $phase->fixtures()->update(['kicks_off_at' => $when]);
    }

    public function test_no_entry_never_needs_attention(): void
    {
        $this->assertFalse($this->attention()->needsAttention($this->upfront, null));
    }

    public function test_upfront_with_unfinished_group_picks_needs_attention(): void
    {
        $entry = $this->entryIn($this->upfront);

        $summary = $this->attention()->summary($this->upfront, $entry);

        $this->assertTrue($summary->needsAttention);
        $this->assertCount(1, $summary->openWindows);
        $this->assertSame(PhaseKey::Group->value, $summary->openWindows[0]['phase_key']);
        $this->assertSame(72, $summary->openWindows[0]['missing_count']);
        $this->assertFalse($summary->openWindows[0]['has_unresolved_ties']);
    }

    public function test_upfront_with_every_group_pick_and_no_ties_needs_no_attention(): void
    {
        $entry = $this->entryIn($this->upfront);
        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores());

        $this->assertFalse($this->attention()->needsAttention($this->upfront, $entry));
    }

    public function test_upfront_with_only_unresolved_ties_needs_attention(): void
    {
        $entry = $this->entryIn($this->upfront);
        // Every fixture a 0-0 draw ties every team in every group on points, GD and GF, and leaves
        // the straddling-thirds cut tied too — with no ordering recorded, the ties stay unresolved.
        $this->predictAllGroups($entry, $this->tournament, fn (): array => [0, 0], resolveTies: false);

        // Guard: the picks are complete, but the engine genuinely cannot rank without a human.
        $this->assertTrue((new TieResolutionState)->forEntry($entry)->blocked());

        $summary = $this->attention()->summary($this->upfront, $entry);

        $this->assertTrue($summary->needsAttention);
        $this->assertSame(0, $summary->openWindows[0]['missing_count']);
        $this->assertTrue($summary->openWindows[0]['has_unresolved_ties']);
    }

    public function test_upfront_needs_no_attention_once_the_window_is_closed(): void
    {
        $this->upfront->update(['predictions_lock_at' => now()->subDay()]);
        $entry = $this->entryIn($this->upfront);

        $this->assertFalse($this->attention()->needsAttention($this->upfront, $entry));
    }

    public function test_phased_with_unfinished_group_picks_needs_attention(): void
    {
        $entry = $this->entryIn($this->phased);

        $this->assertTrue($this->attention()->needsAttention($this->phased, $entry));
    }

    public function test_phased_open_knockout_window_with_missing_picks_needs_attention(): void
    {
        // Close the group window so attention can only come from the knockout round.
        $this->phased->update(['predictions_lock_at' => now()->subDay()]);
        $entry = $this->entryIn($this->phased);

        $this->recordOfficialGroupResults($this->tournament, $this->seedOrderScores());
        (new OfficialBracketProjector)->project($this->tournament);
        $this->tournament->syncStatus();
        $this->setPhaseKickoff(PhaseKey::RoundOf32, now()->addDays(5));

        $summary = $this->attention()->summary($this->phased, $entry->fresh());

        $this->assertTrue($summary->needsAttention);
        $this->assertCount(1, $summary->openWindows);
        $this->assertSame(PhaseKey::RoundOf32->value, $summary->openWindows[0]['phase_key']);
        $this->assertSame('Round of 32', $summary->openWindows[0]['label']);
        $this->assertSame(16, $summary->openWindows[0]['total_count']);
        $this->assertSame(16, $summary->openWindows[0]['missing_count']);
    }

    public function test_phased_locked_knockout_window_does_not_need_attention(): void
    {
        $this->phased->update(['predictions_lock_at' => now()->subDay()]);
        $entry = $this->entryIn($this->phased);

        $this->recordOfficialGroupResults($this->tournament, $this->seedOrderScores());
        (new OfficialBracketProjector)->project($this->tournament);
        $this->tournament->syncStatus();
        // Kickoff already passed → the round is locked, so missing picks no longer need attention.
        $this->setPhaseKickoff(PhaseKey::RoundOf32, now()->subHour());

        $this->assertFalse($this->attention()->needsAttention($this->phased, $entry->fresh()));
    }

    public function test_phased_pending_knockout_window_does_not_need_attention(): void
    {
        // Group window closed and the round never projected → it is pending, not open.
        $this->phased->update(['predictions_lock_at' => now()->subDay()]);
        $this->tournament->syncStatus();
        $entry = $this->entryIn($this->phased);

        $this->assertFalse($this->attention()->needsAttention($this->phased, $entry));
    }
}
