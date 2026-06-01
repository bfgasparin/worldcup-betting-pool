<?php

namespace Tests\Unit\Services\Predictions;

use App\Models\Team;
use App\Models\Tournament;
use App\Services\Predictions\OfficialBracketProjector;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOfficialResults;
use Tests\Concerns\InteractsWithPredictions;
use Tests\TestCase;

class OfficialBracketProjectorTest extends TestCase
{
    use InteractsWithOfficialResults;
    use InteractsWithPredictions;
    use RefreshDatabase;

    private Tournament $tournament;

    private OfficialBracketProjector $projector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
        $this->projector = new OfficialBracketProjector;
    }

    public function test_projects_round_of_32_participants_from_official_group_results(): void
    {
        $this->recordOfficialGroupResults($this->tournament, $this->seedOrderScores());

        $this->projector->project($this->tournament);

        $r32_1 = $this->knockoutFixture($this->tournament, 'R32-1')->fresh(); // M73
        $r32_7 = $this->knockoutFixture($this->tournament, 'R32-7')->fresh(); // M79

        // Mirrors BracketResolverTest: seed order means winner == position 1, runner-up == 2,
        // and with all thirds tied group H's third is slotted into M79.
        $this->assertSame($this->groupTeam('A', 2)->id, $r32_1->home_team_id);
        $this->assertSame($this->groupTeam('B', 2)->id, $r32_1->away_team_id);
        $this->assertSame($this->groupTeam('A', 1)->id, $r32_7->home_team_id);
        $this->assertSame($this->groupTeam('H', 3)->id, $r32_7->away_team_id);
    }

    public function test_round_of_32_stays_unresolved_until_every_group_is_finished(): void
    {
        // Only group A has official results recorded.
        $this->recordOfficialGroupResults($this->tournament, $this->seedOrderScores(), ['A']);

        $this->projector->project($this->tournament);

        $r32_7 = $this->knockoutFixture($this->tournament, 'R32-7')->fresh(); // M79: Winner A vs 3rd …
        $r32_2 = $this->knockoutFixture($this->tournament, 'R32-2')->fresh(); // M74: Winner E vs 3rd …

        $this->assertSame($this->groupTeam('A', 1)->id, $r32_7->home_team_id); // group A is complete
        $this->assertNull($r32_7->away_team_id);  // the best-third slot needs every group
        $this->assertNull($r32_2->home_team_id);  // group E has no results yet
    }

    public function test_cascades_to_the_final_and_third_place_with_home_teams_advancing(): void
    {
        $this->recordOfficialGroupResults($this->tournament, $this->seedOrderScores());

        $this->advanceOfficialHome($this->tournament, $this->projector);

        $final = $this->knockoutFixture($this->tournament, 'F')->fresh();
        $thirdPlace = $this->knockoutFixture($this->tournament, 'TP')->fresh();

        // Same bracket geometry as the prediction engine: SF-1 home resolves to the Group E
        // winner, SF-2 home to the Group C winner; the SF losers are the away (Group K runner-up
        // and Group J winner) sides.
        $this->assertSame($this->groupTeam('E', 1)->id, $final->home_team_id);
        $this->assertSame($this->groupTeam('C', 1)->id, $final->away_team_id);
        $this->assertSame($this->groupTeam('E', 1)->id, $final->winner_team_id); // champion
        $this->assertSame($this->groupTeam('K', 2)->id, $thirdPlace->home_team_id);
        $this->assertSame($this->groupTeam('J', 1)->id, $thirdPlace->away_team_id);
    }

    public function test_correcting_an_upstream_result_re_cascades_downstream(): void
    {
        $this->recordOfficialGroupResults($this->tournament, $this->seedOrderScores());
        $this->advanceOfficialHome($this->tournament, $this->projector);

        $r16_4 = $this->knockoutFixture($this->tournament, 'R16-4'); // fed by M79 (Group A winner's slot)
        $this->assertSame($this->groupTeam('A', 1)->id, $r16_4->fresh()->home_team_id);

        // Flip M79's winner to the away side; re-projecting must replace the downstream team.
        $m79 = $this->knockoutFixture($this->tournament, 'R32-7');
        $m79->update(['winner_team_id' => $m79->away_team_id]);

        $this->projector->project($this->tournament);

        $this->assertSame($m79->fresh()->away_team_id, $r16_4->fresh()->home_team_id);
    }

    private function groupTeam(string $groupName, int $position): Team
    {
        $group = $this->tournament->groups()->where('name', $groupName)->firstOrFail();

        return $group->teams()->wherePivot('position', $position)->firstOrFail();
    }
}
