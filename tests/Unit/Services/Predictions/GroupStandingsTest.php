<?php

namespace Tests\Unit\Services\Predictions;

use App\Models\Entry;
use App\Models\Fixture;
use App\Models\Game;
use App\Models\Group;
use App\Models\GroupPrediction;
use App\Models\Phase;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\Predictions\GroupStandings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupStandingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Position-based round-robin pairings, matching the seeder.
     *
     * @var list<array{int, int}>
     */
    private const PAIRINGS = [[1, 2], [3, 4], [1, 3], [4, 2], [4, 1], [2, 3]];

    public function test_orders_by_points_then_goal_difference_then_goals_for(): void
    {
        [$group, $entry, $teams] = $this->makeGroup();

        // T2 & T1 finish on 7pts (T2 better GD); T3 & T4 on 1pt (T3 better GD).
        $this->predict($entry, $group, [[0, 0], [0, 0], [1, 0], [0, 3], [0, 1], [1, 0]]);

        $ordered = $this->orderedTeamIds($group, $entry);

        $this->assertSame(
            [$teams[2]->id, $teams[1]->id, $teams[3]->id, $teams[4]->id],
            $ordered,
        );
    }

    public function test_breaks_ties_by_head_to_head(): void
    {
        [$group, $entry, $teams] = $this->makeGroup();

        // T1/T2 identical overall (6pts, +1, GF2) but T1 won their meeting; likewise T4 over T3.
        $this->predict($entry, $group, [[1, 0], [0, 1], [0, 1], [0, 1], [0, 1], [1, 0]]);

        $ordered = $this->orderedTeamIds($group, $entry);

        $this->assertSame(
            [$teams[1]->id, $teams[2]->id, $teams[4]->id, $teams[3]->id],
            $ordered,
        );
    }

    public function test_head_to_head_outranks_a_better_overall_goal_difference(): void
    {
        [$group, $entry, $teams] = $this->makeGroup();

        // T1 & T2 both finish on 6pts. T2 has the far better overall GD (+9 vs +1) from big
        // wins over T3/T4, but T1 beat T2 in their meeting, so the 2026 rules rank T1 first.
        $this->predict($entry, $group, [[1, 0], [1, 0], [1, 0], [0, 5], [1, 0], [5, 0]]);

        $ordered = $this->orderedTeamIds($group, $entry);

        $this->assertSame(
            [$teams[1]->id, $teams[2]->id, $teams[3]->id, $teams[4]->id],
            $ordered,
        );
    }

    public function test_unresolvable_tie_is_not_auto_broken_by_seed_position(): void
    {
        [$group, $entry, $teams] = $this->makeGroup();

        // Every match a goalless draw: identical records and head-to-head. The engine no longer
        // silently falls back to seed order -- it reports the whole group as one unresolved tie.
        $this->predict($entry, $group, [[0, 0], [0, 0], [0, 0], [0, 0], [0, 0], [0, 0]]);

        $standings = $this->standings($group, $entry);

        $this->assertTrue($standings->hasUnresolvedTies());
        $this->assertCount(4, $standings->ordered()); // still listed (default seed order) for display
        $this->assertNull($standings->winner());
        $this->assertNull($standings->runnerUp());
        $this->assertNull($standings->thirdStanding());

        $ties = $standings->unresolvedTies();
        $this->assertCount(1, $ties);
        $this->assertEqualsCanonicalizing(
            [$teams[1]->id, $teams[2]->id, $teams[3]->id, $teams[4]->id],
            $ties[0],
        );
    }

    public function test_a_manual_ordering_resolves_a_full_cluster(): void
    {
        [$group, $entry, $teams] = $this->makeGroup();
        $this->predict($entry, $group, [[0, 0], [0, 0], [0, 0], [0, 0], [0, 0], [0, 0]]);

        $manualOrder = [$teams[3]->id, $teams[1]->id, $teams[4]->id, $teams[2]->id];

        $standings = $this->standings($group, $entry, $manualOrder);

        $this->assertFalse($standings->hasUnresolvedTies());
        $this->assertSame($manualOrder, $this->orderedTeamIds($group, $entry, $manualOrder));
        $this->assertSame($teams[3]->id, $standings->winner());
        $this->assertSame($teams[1]->id, $standings->runnerUp());
    }

    public function test_a_partial_tie_only_marks_the_tied_subset(): void
    {
        [$group, $entry, $teams] = $this->makeGroup();

        // T1 wins all (9), T2 next (6); T3 & T4 perfectly level on 1pt (drew each other, lost the rest).
        $this->predict($entry, $group, [[1, 0], [0, 0], [1, 0], [0, 1], [0, 1], [1, 0]]);

        $standings = $this->standings($group, $entry);

        $this->assertTrue($standings->hasUnresolvedTies());
        $this->assertSame($teams[1]->id, $standings->winner());
        $this->assertSame($teams[2]->id, $standings->runnerUp());
        $this->assertNull($standings->thirdStanding()); // rank 3 sits inside the tied {T3,T4}

        $ties = $standings->unresolvedTies();
        $this->assertCount(1, $ties);
        $this->assertEqualsCanonicalizing([$teams[3]->id, $teams[4]->id], $ties[0]);
    }

    public function test_multiple_independent_clusters_are_each_marked(): void
    {
        [$group, $entry, $teams] = $this->makeGroup();

        // T1 & T2 level on 7pts; T3 & T4 level on 1pt.
        $this->predict($entry, $group, [[0, 0], [0, 0], [1, 0], [0, 1], [0, 1], [1, 0]]);

        $ties = $this->standings($group, $entry)->unresolvedTies();

        $this->assertCount(2, $ties);
        $sets = array_map(fn (array $set): array => $this->sorted($set), $ties);
        $this->assertContains($this->sorted([$teams[1]->id, $teams[2]->id]), $sets);
        $this->assertContains($this->sorted([$teams[3]->id, $teams[4]->id]), $sets);
    }

    public function test_a_manual_order_resolves_only_the_matching_cluster(): void
    {
        [$group, $entry, $teams] = $this->makeGroup();
        $this->predict($entry, $group, [[0, 0], [0, 0], [1, 0], [0, 1], [0, 1], [1, 0]]);

        // Resolve only the top {T1,T2} cluster, leaving {T3,T4} tied.
        $standings = $this->standings($group, $entry, [$teams[2]->id, $teams[1]->id]);

        $this->assertSame($teams[2]->id, $standings->winner());
        $this->assertSame($teams[1]->id, $standings->runnerUp());

        $ties = $standings->unresolvedTies();
        $this->assertCount(1, $ties);
        $this->assertEqualsCanonicalizing([$teams[3]->id, $teams[4]->id], $ties[0]);
    }

    public function test_a_stale_manual_ordering_is_ignored(): void
    {
        [$group, $entry, $teams] = $this->makeGroup();
        $this->predict($entry, $group, [[0, 0], [0, 0], [0, 0], [0, 0], [0, 0], [0, 0]]);

        // Order references only two of the four tied teams -> set mismatch -> ignored.
        $standings = $this->standings($group, $entry, [$teams[2]->id, $teams[1]->id]);

        $this->assertTrue($standings->hasUnresolvedTies());
        $this->assertNull($standings->winner());
    }

    public function test_a_manual_ordering_is_ignored_once_the_tie_disappears(): void
    {
        [$group, $entry, $teams] = $this->makeGroup();

        // Decisive scores: T1 9, T2 6, T3 3, T4 0 -- no tie at all.
        $this->predict($entry, $group, [[1, 0], [1, 0], [1, 0], [0, 1], [0, 1], [1, 0]]);

        // A leftover ordering from a previous all-square prediction.
        $manualOrder = [$teams[4]->id, $teams[3]->id, $teams[2]->id, $teams[1]->id];

        $standings = $this->standings($group, $entry, $manualOrder);

        $this->assertFalse($standings->hasUnresolvedTies());
        $this->assertSame($teams[1]->id, $standings->winner());
    }

    public function test_incomplete_group_is_not_complete_but_still_totally_ordered(): void
    {
        [$group, $entry] = $this->makeGroup();

        $this->predict($entry, $group, [[1, 0], [2, 1], [0, 0]]); // only 3 of 6 fixtures

        $standings = $this->standings($group, $entry);

        $this->assertFalse($standings->isComplete());
        $this->assertCount(4, $standings->ordered());
        $this->assertNull($standings->winner());
        $this->assertNull($standings->thirdStanding());
    }

    /**
     * Build a standalone group with four positioned teams and its six round-robin fixtures.
     *
     * @return array{Group, Entry, array<int, Team>}
     */
    private function makeGroup(): array
    {
        $tournament = Tournament::factory()->create();
        $phase = Phase::factory()->for($tournament)->create([
            'key' => 'group', 'type' => 'group', 'name' => 'Group Stage', 'sort_order' => 1,
        ]);
        $group = Group::factory()->for($tournament)->create(['name' => 'A', 'sort_order' => 1]);

        $teams = [];
        foreach (range(1, 4) as $position) {
            $team = Team::factory()->create();
            $group->teams()->attach($team, ['position' => $position]);
            $teams[$position] = $team;
        }

        $matchNumber = 1;
        foreach (self::PAIRINGS as [$home, $away]) {
            Fixture::factory()->for($tournament)->create([
                'phase_id' => $phase->id,
                'group_id' => $group->id,
                'match_number' => $matchNumber++,
                'home_team_id' => $teams[$home]->id,
                'away_team_id' => $teams[$away]->id,
                'bracket_slot' => null,
            ]);
        }

        return [$group, Entry::factory()->for(Game::factory()->for($tournament))->create(), $teams];
    }

    /**
     * @param  list<array{int, int}>  $scores  indexed by fixture order (match number)
     */
    private function predict(Entry $entry, Group $group, array $scores): void
    {
        $fixtures = $group->fixtures()->orderBy('match_number')->get();

        foreach ($scores as $index => [$home, $away]) {
            GroupPrediction::factory()->for($entry)->create([
                'fixture_id' => $fixtures[$index]->id,
                'home_goals' => $home,
                'away_goals' => $away,
            ]);
        }
    }

    /**
     * @param  list<int>  $manualOrder
     */
    private function standings(Group $group, Entry $entry, array $manualOrder = []): GroupStandings
    {
        $group->load(['teams', 'fixtures']);
        $predictions = $entry->groupPredictions()->get()->keyBy('fixture_id')->all();

        return new GroupStandings($group, $predictions, $manualOrder);
    }

    /**
     * @param  list<int>  $manualOrder
     * @return list<int>
     */
    private function orderedTeamIds(Group $group, Entry $entry, array $manualOrder = []): array
    {
        return array_map(
            fn ($standing): int => $standing->teamId,
            $this->standings($group, $entry, $manualOrder)->ordered(),
        );
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function sorted(array $ids): array
    {
        sort($ids);

        return $ids;
    }
}
