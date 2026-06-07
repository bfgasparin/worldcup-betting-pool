<?php

namespace Tests\Unit\Services\Predictions;

use App\Enums\OrderingScope;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Predictions\BracketResolver;
use App\Services\Predictions\DefaultTieOrdering;
use App\Services\Predictions\TieResolutionState;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOfficialResults;
use Tests\Concerns\InteractsWithPredictions;
use Tests\TestCase;

class DefaultTieOrderingTest extends TestCase
{
    use InteractsWithOfficialResults;
    use InteractsWithPredictions;
    use RefreshDatabase;

    private Tournament $tournament;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
    }

    public function test_it_resolves_a_thirds_tie_hidden_behind_a_within_group_tie_for_an_entry(): void
    {
        $pool = $this->tournament->pools()->where('slug', 'world-cup-2026-ffa')->firstOrFail();
        $entry = $pool->entries()->create(['user_id' => User::factory()->create()->id]);

        // One group is fully level (every match drawn) so its third place is itself an unresolved
        // within-group tie; the rest resolve cleanly to seed order, leaving all eleven other thirds
        // identical and tied across the qualifying cut. Before the fix that straddling thirds tie was
        // missed (the tied group's third read as "unknown" while the cut was checked), so no default
        // ordering was recorded and the bracket could never resolve.
        $tiedGroup = $this->tournament->groups()->orderBy('sort_order')->firstOrFail()->name;

        foreach ($this->tournament->groups()->orderBy('sort_order')->get() as $group) {
            $rule = $group->name === $tiedGroup
                ? fn (int $home, int $away): array => [0, 0]
                : $this->seedOrderScores();

            $this->predictGroup($entry, $this->tournament, $group->name, $rule);
        }

        (new DefaultTieOrdering)->applyToEntry($entry);

        // A default ordering for the straddling thirds tie is now recorded...
        $this->assertTrue(
            $entry->groupOrderings()->where('scope', OrderingScope::Thirds)->exists(),
        );

        // ...so nothing is left blocking the bracket and it fills end to end.
        $this->assertFalse((new TieResolutionState)->forEntry($entry)->blocked());

        $this->advanceAllHome($entry, new BracketResolver);

        $entry->load('knockoutPredictions');
        $this->assertCount(32, $entry->knockoutPredictions);
        $this->assertSame(32, $entry->knockoutPredictions()->whereNotNull('predicted_home_team_id')->count());
        $this->assertSame(32, $entry->knockoutPredictions()->whereNotNull('predicted_away_team_id')->count());
    }

    public function test_it_resolves_a_thirds_tie_hidden_behind_a_within_group_tie_for_a_tournament(): void
    {
        // Same shape on the OFFICIAL side (the other caller of the shared helper): one group level,
        // the rest seed order.
        $tiedGroup = $this->tournament->groups()->orderBy('sort_order')->firstOrFail()->name;

        $this->recordOfficialGroupResults($this->tournament, $this->seedOrderScores(), resolveTies: false);
        $this->recordOfficialGroupResults($this->tournament, fn (int $home, int $away): array => [0, 0], onlyGroups: [$tiedGroup], resolveTies: false);

        (new DefaultTieOrdering)->applyToTournament($this->tournament);

        $this->assertTrue(
            $this->tournament->groupOrderings()->where('scope', OrderingScope::Thirds)->exists(),
        );
        $this->assertFalse((new TieResolutionState)->forTournament($this->tournament)->blocked());
    }
}
