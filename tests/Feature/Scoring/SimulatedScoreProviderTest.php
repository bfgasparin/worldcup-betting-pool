<?php

namespace Tests\Feature\Scoring;

use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\Scoring\Providers\SimulatedScoreProvider;
use Carbon\CarbonInterface;
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

    public function test_live_reports_nothing_when_no_fixture_is_live(): void
    {
        $live = collect((new SimulatedScoreProvider)->live($this->tournament));

        $this->assertCount(0, $live);
    }

    public function test_live_reveals_no_goals_at_kickoff(): void
    {
        $this->goLiveAt($this->groupFixture(1), now());

        $score = collect((new SimulatedScoreProvider)->live($this->tournament))->firstWhere('matchNumber', 1);

        $this->assertSame(0, $score->homeGoals);
        $this->assertSame(0, $score->awayGoals);
    }

    public function test_live_is_monotonic_as_the_clock_advances(): void
    {
        $fixture = $this->goLiveAt($this->groupFixture(1), now()->subMinutes(38)); // ~25%

        $early = collect((new SimulatedScoreProvider)->live($this->tournament))->firstWhere('matchNumber', 1);

        $fixture->update(['kicks_off_at' => now()->subMinutes(113)]); // ~75%
        $late = collect((new SimulatedScoreProvider)->live($this->tournament))->firstWhere('matchNumber', 1);

        $this->assertGreaterThanOrEqual($early->homeGoals, $late->homeGoals);
        $this->assertGreaterThanOrEqual($early->awayGoals, $late->awayGoals);
    }

    public function test_live_converges_to_the_proposed_final_for_a_group_fixture(): void
    {
        $this->markEnded($this->groupFixture(1)); // live, past full time -> f clamps to 1

        $provider = new SimulatedScoreProvider;
        $live = collect($provider->live($this->tournament))->firstWhere('matchNumber', 1);
        $final = collect($provider->fetch($this->tournament))->firstWhere('matchNumber', 1);

        $this->assertSame($final->homeGoals, $live->homeGoals);
        $this->assertSame($final->awayGoals, $live->awayGoals);
    }

    public function test_live_converges_to_the_regulation_final_for_a_resolved_knockout(): void
    {
        [$home, $away] = Team::query()->take(2)->get()->all();

        $fixture = $this->tournament->knockoutFixtures()->orderBy('match_number')->firstOrFail();
        $fixture->update(['home_team_id' => $home->id, 'away_team_id' => $away->id]);
        $this->markEnded($fixture);

        $provider = new SimulatedScoreProvider;
        $live = collect($provider->live($this->tournament))->firstWhere('matchNumber', $fixture->match_number);
        $final = collect($provider->fetch($this->tournament))->firstWhere('matchNumber', $fixture->match_number);

        // The live board shows regulation goals (a penalty-draw converges to a level scoreline); the
        // winner and penalties live on the final, not the live board.
        $this->assertSame($final->homeGoals, $live->homeGoals);
        $this->assertSame($final->awayGoals, $live->awayGoals);
    }

    public function test_live_skips_a_knockout_without_resolved_teams(): void
    {
        $fixture = $this->tournament->knockoutFixtures()->orderBy('match_number')->firstOrFail();
        $this->assertNull($fixture->home_team_id);
        $this->markEnded($fixture); // live, but participants unknown

        $live = collect((new SimulatedScoreProvider)->live($this->tournament));

        $this->assertNull($live->firstWhere('matchNumber', $fixture->match_number));
    }

    public function test_live_is_deterministic(): void
    {
        $this->goLiveAt($this->groupFixture(1), now()->subMinutes(75));

        $first = collect((new SimulatedScoreProvider)->live($this->tournament))->firstWhere('matchNumber', 1);
        $second = collect((new SimulatedScoreProvider)->live($this->tournament))->firstWhere('matchNumber', 1);

        $this->assertSame($first->homeGoals, $second->homeGoals);
        $this->assertSame($first->awayGoals, $second->awayGoals);
    }

    private function groupFixture(int $matchNumber): Fixture
    {
        return $this->tournament->groupFixtures()->where('match_number', $matchNumber)->firstOrFail();
    }

    /** Put a fixture live with a given kickoff (its elapsed time drives the live progression). */
    private function goLiveAt(Fixture $fixture, CarbonInterface $kickoff): Fixture
    {
        $fixture->update(['status' => FixtureStatus::Live, 'kicks_off_at' => $kickoff]);

        return $fixture->refresh();
    }
}
