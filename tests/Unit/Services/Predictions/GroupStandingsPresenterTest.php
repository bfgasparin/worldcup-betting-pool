<?php

namespace Tests\Unit\Services\Predictions;

use App\Models\Entry;
use App\Models\Fixture;
use App\Models\Group;
use App\Models\GroupPrediction;
use App\Models\Phase;
use App\Models\Pool;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\Predictions\GroupStandings;
use App\Services\Predictions\GroupStandingsPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupStandingsPresenterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Position-based round-robin pairings, matching the seeder.
     *
     * @var list<array{int, int}>
     */
    private const PAIRINGS = [[1, 2], [3, 4], [1, 3], [4, 2], [4, 1], [2, 3]];

    public function test_maps_ordered_standings_into_frontend_rows(): void
    {
        [$group, $entry, $teams] = $this->makeGroup();

        // Team 1 wins all three of its matches; the rest draw, so team 1 tops the group on 9pts.
        $this->predict($entry, $group, [[1, 0], [0, 0], [1, 0], [0, 0], [0, 1], [0, 0]]);

        $rows = GroupStandingsPresenter::rows(
            $this->standings($group, $entry),
            $group->teams->keyBy('id'),
        );

        $this->assertCount(4, $rows);

        $top = $rows[0];
        $this->assertSame(1, $top['rank']);
        $this->assertSame($teams[1]->id, $top['team']['id']);
        $this->assertSame(3, $top['played']);
        $this->assertSame(3, $top['won']);
        $this->assertSame(9, $top['points']);
        $this->assertSame(3, $top['goal_difference']);
        $this->assertSame(['W', 'W', 'W'], $top['form']);

        // The team ref carries exactly the fields the frontend renders.
        $this->assertSame(
            ['id', 'name', 'code', 'is_placeholder', 'flag_url'],
            array_keys($top['team']),
        );

        // Every row carries the full standings shape.
        $this->assertSame(
            ['rank', 'team', 'played', 'won', 'drawn', 'lost', 'goals_for', 'goals_against', 'goal_difference', 'points', 'form'],
            array_keys($rows[3]),
        );
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

        return [$group, Entry::factory()->for(Pool::factory()->for($tournament))->create(), $teams];
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
}
