<?php

namespace Tests\Unit\Services\Pools;

use App\Enums\PhaseKey;
use App\Models\Entry;
use App\Models\KnockoutPrediction;
use App\Models\Pool;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Pools\PredictionAttention;
use App\Services\Predictions\BracketResolver;
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

    public function test_upfront_with_group_complete_but_no_knockout_picks_needs_attention(): void
    {
        // Every group score in (and ties resolved), but the self-derived knockout bracket — which an
        // upfront pool predicts up front too — is untouched, so the player still has 32 picks to make.
        $entry = $this->entryIn($this->upfront);
        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores());

        $summary = $this->attention()->summary($this->upfront, $entry);

        $this->assertTrue($summary->needsAttention);
        $this->assertCount(1, $summary->openWindows);
        $this->assertSame('knockout', $summary->openWindows[0]['phase_key']);
        $this->assertSame('Knockout bracket', $summary->openWindows[0]['label']);
        $this->assertSame(32, $summary->openWindows[0]['total_count']);
        $this->assertSame(32, $summary->openWindows[0]['missing_count']);
    }

    public function test_upfront_fully_predicted_needs_no_attention(): void
    {
        $entry = $this->entryIn($this->upfront);
        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores());
        // Fill the whole knockout bracket to the final by advancing the home team every round.
        $this->advanceAllHome($entry, new BracketResolver);

        $this->assertFalse($this->attention()->needsAttention($this->upfront, $entry->fresh()));
    }

    public function test_upfront_partially_predicted_knockout_still_needs_attention(): void
    {
        $entry = $this->entryIn($this->upfront);
        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores());
        // Resolve the bracket, then pick advancers for the Round of 32 only — the rest is unfinished.
        $resolver = new BracketResolver;
        $resolver->persist($entry);
        $entry->load('knockoutPredictions');
        foreach ($entry->knockoutPredictions as $prediction) {
            if ($prediction->predicted_home_team_id !== null && $prediction->advancing_team_id === null) {
                $prediction->update([
                    'advancing_team_id' => $prediction->predicted_home_team_id,
                    'home_goals' => 1,
                    'away_goals' => 0,
                ]);
            }
        }
        $resolver->persist($entry);

        $summary = $this->attention()->summary($this->upfront, $entry->fresh());

        $this->assertTrue($summary->needsAttention);
        $this->assertSame('knockout', $summary->openWindows[0]['phase_key']);
        // The 16 Round of 32 ties are decided; the remaining 16 knockout fixtures are still open.
        $this->assertSame(16, $summary->openWindows[0]['missing_count']);
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

    public function test_completion_is_false_without_an_entry(): void
    {
        $this->assertFalse($this->attention()->completion($this->upfront, null)->isComplete);
    }

    public function test_upfront_completion_is_false_while_picks_are_incomplete(): void
    {
        $entry = $this->entryIn($this->upfront);

        $this->assertFalse($this->attention()->completion($this->upfront, $entry)->isComplete);
    }

    public function test_upfront_completion_is_true_once_fully_predicted(): void
    {
        $entry = $this->entryIn($this->upfront);
        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores());
        $this->advanceAllHome($entry, new BracketResolver);

        $completion = $this->attention()->completion($this->upfront, $entry->fresh());

        $this->assertTrue($completion->isComplete);
        // Upfront pools collapse to a single window — the whole bracket locks with the group stage.
        $this->assertCount(1, $completion->openWindows);
        $this->assertSame(PhaseKey::Group->value, $completion->openWindows[0]['phase_key']);
        $this->assertSame('Your bracket', $completion->openWindows[0]['label']);
        $this->assertNotNull($completion->openWindows[0]['deadline']);
    }

    public function test_upfront_completion_is_false_once_the_window_is_closed(): void
    {
        $entry = $this->entryIn($this->upfront);
        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores());
        $this->advanceAllHome($entry, new BracketResolver);
        // Past the lock there is nothing open to celebrate, even though every pick is in.
        $this->upfront->update(['predictions_lock_at' => now()->subDay()]);

        $completion = $this->attention()->completion($this->upfront, $entry->fresh());

        $this->assertFalse($completion->isComplete);
        $this->assertSame([], $completion->openWindows);
    }

    public function test_upfront_completion_is_false_with_unresolved_ties(): void
    {
        $entry = $this->entryIn($this->upfront);
        // Every fixture a 0-0 draw leaves the standings tied with no ordering recorded, so the
        // bracket can't resolve — picks look complete but there is still work to do.
        $this->predictAllGroups($entry, $this->tournament, fn (): array => [0, 0], resolveTies: false);

        $this->assertFalse($this->attention()->completion($this->upfront, $entry)->isComplete);
    }

    public function test_phased_completion_is_true_once_the_group_window_is_complete(): void
    {
        $entry = $this->entryIn($this->phased);
        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores());

        $completion = $this->attention()->completion($this->phased, $entry->fresh());

        $this->assertTrue($completion->isComplete);
        $this->assertCount(1, $completion->openWindows);
        $this->assertSame(PhaseKey::Group->value, $completion->openWindows[0]['phase_key']);
        $this->assertSame('Group stage', $completion->openWindows[0]['label']);
    }

    public function test_phased_completion_is_false_while_the_group_window_is_incomplete(): void
    {
        $entry = $this->entryIn($this->phased);

        $this->assertFalse($this->attention()->completion($this->phased, $entry)->isComplete);
    }

    public function test_phased_completion_is_true_once_an_open_knockout_window_is_filled(): void
    {
        // Close the group window so completion can only come from the open knockout round.
        $this->phased->update(['predictions_lock_at' => now()->subDay()]);
        $entry = $this->entryIn($this->phased);

        $this->recordOfficialGroupResults($this->tournament, $this->seedOrderScores());
        (new OfficialBracketProjector)->project($this->tournament);
        $this->tournament->syncStatus();
        $this->setPhaseKickoff(PhaseKey::RoundOf32, now()->addDays(5));

        // Fill every Round of 32 pick (both goals set) so the only open window is complete.
        $round = $this->tournament->phases()->where('key', PhaseKey::RoundOf32->value)->firstOrFail();
        foreach ($round->fixtures as $fixture) {
            KnockoutPrediction::updateOrCreate(
                ['entry_id' => $entry->id, 'fixture_id' => $fixture->id],
                ['home_goals' => 1, 'away_goals' => 0, 'advancing_team_id' => $fixture->home_team_id],
            );
        }

        $completion = $this->attention()->completion($this->phased, $entry->fresh());

        $this->assertTrue($completion->isComplete);
        $this->assertCount(1, $completion->openWindows);
        $this->assertSame(PhaseKey::RoundOf32->value, $completion->openWindows[0]['phase_key']);
        $this->assertSame('Round of 32', $completion->openWindows[0]['label']);
        $this->assertNotNull($completion->openWindows[0]['deadline']);
    }

    public function test_phased_completion_is_false_while_an_open_knockout_window_is_empty(): void
    {
        $this->phased->update(['predictions_lock_at' => now()->subDay()]);
        $entry = $this->entryIn($this->phased);

        $this->recordOfficialGroupResults($this->tournament, $this->seedOrderScores());
        (new OfficialBracketProjector)->project($this->tournament);
        $this->tournament->syncStatus();
        $this->setPhaseKickoff(PhaseKey::RoundOf32, now()->addDays(5));

        $this->assertFalse($this->attention()->completion($this->phased, $entry->fresh())->isComplete);
    }

    public function test_phased_completion_is_false_when_nothing_is_open(): void
    {
        // Group window closed and no round projected → nothing open, so nothing to celebrate.
        $this->phased->update(['predictions_lock_at' => now()->subDay()]);
        $this->tournament->syncStatus();
        $entry = $this->entryIn($this->phased);

        $completion = $this->attention()->completion($this->phased, $entry);

        $this->assertFalse($completion->isComplete);
        $this->assertSame([], $completion->openWindows);
    }
}
