<?php

namespace Tests\Feature;

use App\Enums\OrderingScope;
use App\Enums\ScoringStrategy;
use App\Models\Entry;
use App\Models\Pool;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithPredictions;
use Tests\TestCase;

class PredictionOrderingTest extends TestCase
{
    use InteractsWithPredictions;
    use RefreshDatabase;

    private Tournament $tournament;

    private Pool $pool;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
        $this->pool = $this->tournament->pools()->where('scoring_strategy', ScoringStrategy::UpfrontBracket)->firstOrFail();
        $this->user = User::factory()->create();
    }

    public function test_a_player_orders_a_within_group_tie_and_the_bracket_fills(): void
    {
        $entry = Entry::factory()->for($this->pool)->for($this->user)->create();
        // All goalless: every group is a four-way tie, so no group winner can be derived yet.
        $this->predictAllGroups($entry, $this->tournament, fn (int $home, int $away): array => [0, 0], resolveTies: false);

        $r32 = $this->knockoutFixture($this->tournament, 'R32-7'); // home slot = Winner Group A
        $this->assertNull($entry->knockoutPredictions()->where('fixture_id', $r32->id)->value('predicted_home_team_id'));

        // Order with the lowest seed first so the result differs from the default seed order.
        $groupA = $this->tournament->groups()->where('name', 'A')->firstOrFail()
            ->teams()->orderByPivot('position', 'desc')->get()->pluck('id')->all();

        $this->actingAs($this->user)
            ->put(route('pools.predict.ordering', $this->pool), [
                'scope' => OrderingScope::WithinGroup->value,
                'group' => 'A',
                'ordered_team_ids' => $groupA,
            ])
            ->assertRedirect(route('pools.predict.edit', $this->pool));

        $this->assertDatabaseHas('entry_group_orderings', [
            'entry_id' => $entry->id,
            'scope' => OrderingScope::WithinGroup->value,
        ]);

        // The player chose this team to top the group, so it now fills the Winner-Group-A slot.
        $this->assertSame(
            $groupA[0],
            $entry->knockoutPredictions()->where('fixture_id', $r32->id)->value('predicted_home_team_id'),
        );
    }

    public function test_a_stale_ordering_is_rejected(): void
    {
        $entry = Entry::factory()->for($this->pool)->for($this->user)->create();
        $this->predictAllGroups($entry, $this->tournament, fn (int $home, int $away): array => [0, 0], resolveTies: false);

        $this->actingAs($this->user)
            ->put(route('pools.predict.ordering', $this->pool), [
                'scope' => OrderingScope::WithinGroup->value,
                'group' => 'A',
                'ordered_team_ids' => [1, 2], // not the live four-way tie
            ])
            ->assertSessionHasErrors('ordered_team_ids');
    }

    public function test_the_ordering_endpoint_is_forbidden_for_a_phased_pool(): void
    {
        $phased = Pool::factory()->for($this->tournament)->create([
            'scoring_strategy' => ScoringStrategy::PhasedBracket,
            'predictions_lock_at' => now()->addWeek(),
        ]);
        // Joined and within the window, so the rejection is specifically the phased scoring strategy.
        Entry::factory()->for($phased)->for($this->user)->create();

        $this->actingAs($this->user)
            ->put(route('pools.predict.ordering', $phased), [
                'scope' => OrderingScope::WithinGroup->value,
                'group' => 'A',
                'ordered_team_ids' => [1, 2, 3, 4],
            ])
            ->assertForbidden();
    }
}
