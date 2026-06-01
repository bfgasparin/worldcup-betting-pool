<?php

namespace Tests\Feature\Scoring;

use App\Models\Fixture;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\Scoring\Providers\SimulatedScoreProvider;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOfficialResults;
use Tests\TestCase;

class SimulatedScoreProviderTest extends TestCase
{
    use InteractsWithOfficialResults;
    use RefreshDatabase;

    private Tournament $tournament;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
    }

    public function test_it_proposes_nothing_when_no_match_has_ended(): void
    {
        $scores = collect((new SimulatedScoreProvider)->fetch($this->tournament));

        $this->assertCount(0, $scores);
    }

    public function test_it_proposes_only_for_ended_group_fixtures(): void
    {
        $this->markEnded($this->groupFixture(1));
        $this->markEnded($this->groupFixture(2));

        $scores = collect((new SimulatedScoreProvider)->fetch($this->tournament));

        $this->assertEqualsCanonicalizing([1, 2], $scores->pluck('matchNumber')->all());
        $scores->each(function ($score): void {
            $this->assertGreaterThanOrEqual(0, $score->homeGoals);
            $this->assertLessThanOrEqual(3, $score->homeGoals);
            $this->assertGreaterThanOrEqual(0, $score->awayGoals);
            $this->assertLessThanOrEqual(3, $score->awayGoals);
        });
    }

    public function test_it_proposes_a_winner_for_an_ended_knockout_with_resolved_teams(): void
    {
        [$home, $away] = Team::query()->take(2)->get()->all();

        $fixture = $this->tournament->knockoutFixtures()->orderBy('match_number')->firstOrFail();
        $fixture->update(['home_team_id' => $home->id, 'away_team_id' => $away->id]);
        $this->markEnded($fixture);

        $scores = collect((new SimulatedScoreProvider)->fetch($this->tournament));
        $proposed = $scores->firstWhere('matchNumber', $fixture->match_number);

        $this->assertNotNull($proposed);
        $this->assertContains($proposed->winnerTeamId, [$home->id, $away->id]);
    }

    public function test_it_skips_an_ended_knockout_without_resolved_teams(): void
    {
        $fixture = $this->tournament->knockoutFixtures()->orderBy('match_number')->firstOrFail();
        $this->assertNull($fixture->home_team_id);
        $this->markEnded($fixture);

        $scores = collect((new SimulatedScoreProvider)->fetch($this->tournament));

        $this->assertNull($scores->firstWhere('matchNumber', $fixture->match_number));
    }

    public function test_it_is_deterministic(): void
    {
        $this->markEnded($this->groupFixture(1));

        $first = collect((new SimulatedScoreProvider)->fetch($this->tournament))->firstWhere('matchNumber', 1);
        $second = collect((new SimulatedScoreProvider)->fetch($this->tournament))->firstWhere('matchNumber', 1);

        $this->assertSame($first->homeGoals, $second->homeGoals);
        $this->assertSame($first->awayGoals, $second->awayGoals);
    }

    private function groupFixture(int $matchNumber): Fixture
    {
        return $this->tournament->groupFixtures()->where('match_number', $matchNumber)->firstOrFail();
    }
}
