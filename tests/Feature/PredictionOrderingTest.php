<?php

namespace Tests\Feature;

use App\Enums\OrderingScope;
use App\Enums\ScoringStrategy;
use App\Models\Entry;
use App\Models\Pool;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Predictions\TieResolutionState;
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

    public function test_confirming_one_group_tie_keeps_a_second_tie_in_the_same_group_resolved(): void
    {
        $entry = Entry::factory()->for($this->pool)->for($this->user)->create();

        // Clean winners everywhere, then make group A hold two independent ties: positions 1 & 2
        // level on 7pts (the 1st/2nd cluster) and positions 3 & 4 level on 1pt (the 3rd/4th cluster).
        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores(), resolveTies: false);
        $this->predictGroup($entry, $this->tournament, 'A', $this->twoClusterScores());

        [$first, $second, $third, $fourth] = $this->groupTeamIdsByPosition('A');

        // Sanity: the group really has the two separate, independent tied clusters.
        $this->assertCount(2, (new TieResolutionState)->forEntry($entry)->groupTies['A'] ?? []);

        // Confirm the 1st/2nd tie, runner-up first so the choice differs from seed order.
        $this->confirmOrdering([$second, $first]);

        // Only the top tie is resolved so far; the 3rd/4th tie still needs ordering.
        $afterFirst = (new TieResolutionState)->forEntry($entry->fresh());
        $this->assertFalse($afterFirst->groupsResolved);
        $this->assertSame($second, $afterFirst->standings['A']->winner());
        $this->assertNull($afterFirst->standings['A']->thirdStanding());

        // Confirm the 3rd/4th tie. This must NOT wipe the just-saved 1st/2nd ordering.
        $this->confirmOrdering([$fourth, $third]);

        $afterSecond = (new TieResolutionState)->forEntry($entry->fresh());
        $this->assertTrue($afterSecond->groupsResolved, 'Both group ties should be resolved.');
        $this->assertSame($second, $afterSecond->standings['A']->winner(), 'The first tie must stay resolved.');
        $this->assertSame($fourth, $afterSecond->standings['A']->thirdStanding()->teamId);

        // Both clusters live in a single per-group ordering row, as the union of their orders.
        $row = $entry->groupOrderings()->where('scope', OrderingScope::WithinGroup->value)->sole();
        $this->assertEqualsCanonicalizing([$first, $second, $third, $fourth], $row->ordered_team_ids);
    }

    public function test_re_confirming_a_group_tie_replaces_its_order_without_dropping_the_other(): void
    {
        $entry = Entry::factory()->for($this->pool)->for($this->user)->create();
        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores(), resolveTies: false);
        $this->predictGroup($entry, $this->tournament, 'A', $this->twoClusterScores());

        [$first, $second, $third, $fourth] = $this->groupTeamIdsByPosition('A');

        $this->confirmOrdering([$second, $first]);
        $this->confirmOrdering([$fourth, $third]);

        // Re-order the 1st/2nd tie. The 3rd/4th ordering must survive and no id may be duplicated.
        $this->confirmOrdering([$first, $second]);

        $state = (new TieResolutionState)->forEntry($entry->fresh());
        $this->assertTrue($state->groupsResolved);
        $this->assertSame($first, $state->standings['A']->winner(), 'The re-ordered tie wins flip.');
        $this->assertSame($fourth, $state->standings['A']->thirdStanding()->teamId, 'The other tie stays intact.');

        $row = $entry->groupOrderings()->where('scope', OrderingScope::WithinGroup->value)->sole();
        $this->assertCount(4, $row->ordered_team_ids);
        $this->assertEqualsCanonicalizing([$first, $second, $third, $fourth], $row->ordered_team_ids);
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

    /**
     * PUT a within-group ordering for group A as the joined user.
     *
     * @param  list<int>  $orderedTeamIds
     */
    private function confirmOrdering(array $orderedTeamIds): void
    {
        $this->actingAs($this->user)
            ->put(route('pools.predict.ordering', $this->pool), [
                'scope' => OrderingScope::WithinGroup->value,
                'group' => 'A',
                'ordered_team_ids' => $orderedTeamIds,
            ])
            ->assertRedirect(route('pools.predict.edit', $this->pool));
    }

    /**
     * The group's team ids in seed-position order [pos1, pos2, pos3, pos4].
     *
     * @return list<int>
     */
    private function groupTeamIdsByPosition(string $groupName): array
    {
        return $this->tournament->groups()->where('name', $groupName)->firstOrFail()
            ->teams()->orderByPivot('position')->pluck('teams.id')->all();
    }

    /**
     * A position-pair result rule that leaves two independent unbreakable ties in a group:
     * positions 1 & 2 draw and each beat 3 & 4 (level on 7pts), while 3 & 4 draw and lose the rest
     * (level on 1pt). Orientation-independent, so it does not assume the seeder's home/away order.
     *
     * @return callable(int, int): array{int, int}
     */
    private function twoClusterScores(): callable
    {
        return function (int $homePosition, int $awayPosition): array {
            $pair = [$homePosition, $awayPosition];
            sort($pair);

            if ($pair === [1, 2] || $pair === [3, 4]) {
                return [0, 0];
            }

            // Cross-pair match: the top pair {1,2} team (the lower position) wins.
            return $homePosition === $pair[0] ? [1, 0] : [0, 1];
        };
    }
}
