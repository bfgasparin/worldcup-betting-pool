<?php

namespace Tests\Unit\Services\Predictions;

use App\Models\Entry;
use App\Models\Fixture;
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

    public function test_breaks_ties_by_group_seed_position_when_everything_equal(): void
    {
        [$group, $entry, $teams] = $this->makeGroup();

        // Every match a goalless draw: identical records and head-to-head -> seed order.
        $this->predict($entry, $group, [[0, 0], [0, 0], [0, 0], [0, 0], [0, 0], [0, 0]]);

        $ordered = $this->orderedTeamIds($group, $entry);

        $this->assertSame(
            [$teams[1]->id, $teams[2]->id, $teams[3]->id, $teams[4]->id],
            $ordered,
        );
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

        return [$group, Entry::factory()->for($tournament)->create(), $teams];
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

    private function standings(Group $group, Entry $entry): GroupStandings
    {
        $group->load(['teams', 'fixtures']);
        $predictions = $entry->groupPredictions()->get()->keyBy('fixture_id')->all();

        return new GroupStandings($group, $predictions);
    }

    /**
     * @return list<int>
     */
    private function orderedTeamIds(Group $group, Entry $entry): array
    {
        return array_map(
            fn ($standing): int => $standing->teamId,
            $this->standings($group, $entry)->ordered(),
        );
    }
}
