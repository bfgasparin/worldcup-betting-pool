<?php

namespace Tests\Feature\Scoring;

use App\Enums\BatchStatus;
use App\Enums\FixtureStatus;
use App\Enums\LeaderboardCategory;
use App\Enums\PhaseType;
use App\Enums\ProposalStatus;
use App\Models\Entry;
use App\Models\Game;
use App\Models\ScoreBatch;
use App\Models\ScoreProposal;
use App\Models\Tournament;
use App\Models\User;
use App\Notifications\TopOfLeaderboardNotification;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithOfficialResults;
use Tests\Concerns\InteractsWithPredictions;
use Tests\TestCase;

class ApproveScoreBatchTest extends TestCase
{
    use InteractsWithOfficialResults;
    use InteractsWithPredictions;
    use RefreshDatabase;

    private Tournament $tournament;

    private Game $game;

    private Entry $entry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
        $this->game = $this->tournament->games()->where('slug', 'world-cup-2026-ffa')->firstOrFail();
        $this->entry = Entry::factory()->for($this->game)->for(User::factory())->create();
        $this->predictAllGroups($this->entry, $this->tournament, $this->seedOrderScores());
    }

    public function test_approving_a_batch_writes_scores_projects_the_bracket_scores_and_ranks(): void
    {
        $batch = $this->openBatch();
        $this->proposeAllGroupResults($batch);
        $this->resolveProjectedTies($this->tournament, $batch);

        // Approval keeps the admin on the review screen (re-rendered to its post-approval state).
        $this->actingAs($this->admin())
            ->post(route('games.scores.approve', $this->game))
            ->assertRedirect(route('games.scores.review', $this->game));

        // Official scores are written onto the fixtures.
        $firstGroupFixture = $this->tournament->groupFixtures()->orderBy('match_number')->first();
        $this->assertSame(FixtureStatus::Finished, $firstGroupFixture->fresh()->status);
        $this->assertNotNull($firstGroupFixture->fresh()->home_goals);

        // The official bracket is projected (R32 participants now known).
        $r32 = $this->knockoutFixture($this->tournament, 'R32-1');
        $this->assertNotNull($r32->fresh()->home_team_id);

        // Points and ranks are computed for the entry.
        $this->assertNotNull($this->entry->fresh()->total_points);
        $this->assertSame(1, $this->entry->fresh()->rank);

        // Every leaderboard now has a ranked standing for the entry, and the Overall board's rank
        // mirrors the entry's rank.
        $this->assertSame(3, $this->entry->standings()->count());
        $overall = $this->standingFor($this->entry, LeaderboardCategory::Overall);
        $this->assertSame(1, $overall->rank);
        $this->assertSame($this->entry->fresh()->total_points, $overall->value);
        $this->assertSame(1, $this->standingFor($this->entry, LeaderboardCategory::MatchWinners)->rank);

        // The batch and its proposals are marked applied.
        $this->assertSame(BatchStatus::Approved, $batch->fresh()->status);
        $this->assertSame(ProposalStatus::Applied, $batch->proposals()->first()->fresh()->status);
    }

    public function test_approving_a_batch_notifies_the_new_leader(): void
    {
        Notification::fake();

        $batch = $this->openBatch();
        $this->proposeAllGroupResults($batch);
        $this->resolveProjectedTies($this->tournament, $batch);

        $this->actingAs($this->admin())
            ->post(route('games.scores.approve', $this->game));

        // The only entry becomes #1, so its owner receives the milestone email.
        Notification::assertSentTo($this->entry->user, TopOfLeaderboardNotification::class);
    }

    public function test_re_approving_after_a_correction_recomputes_cleanly(): void
    {
        $batch = $this->openBatch();
        $this->proposeAllGroupResults($batch);
        $this->resolveProjectedTies($this->tournament, $batch);

        $this->actingAs($this->admin())->post(route('games.scores.approve', $this->game));
        $firstTotal = $this->entry->fresh()->total_points;

        // Correct one group result in a new batch and re-approve.
        $fixture = $this->tournament->groupFixtures()->orderBy('match_number')->first();
        $newBatch = $this->openBatch();
        ScoreProposal::create([
            'score_batch_id' => $newBatch->id,
            'fixture_id' => $fixture->id,
            'home_goals' => 5,
            'away_goals' => 5,
            'status' => ProposalStatus::Pending,
        ]);
        $this->resolveProjectedTies($this->tournament, $newBatch);

        $this->actingAs($this->admin())->post(route('games.scores.approve', $this->game));

        $this->assertSame(5, $fixture->fresh()->home_goals);
        // The total is recomputed (not doubled): it changes by at most one fixture's points.
        $this->assertNotSame($firstTotal, $this->entry->fresh()->total_points);
        $this->assertLessThanOrEqual(20, abs($firstTotal - $this->entry->fresh()->total_points));
    }

    public function test_a_knockout_proposal_without_a_winner_is_rejected(): void
    {
        $batch = $this->openBatch();
        $knockout = $this->tournament->fixtures()
            ->whereRelation('phase', 'type', PhaseType::Knockout->value)
            ->orderBy('match_number')
            ->first();

        ScoreProposal::create([
            'score_batch_id' => $batch->id,
            'fixture_id' => $knockout->id,
            'home_goals' => 1,
            'away_goals' => 0,
            'winner_team_id' => null,
            'status' => ProposalStatus::Pending,
        ]);

        $this->actingAs($this->admin())
            ->post(route('games.scores.approve', $this->game))
            ->assertSessionHasErrors('proposals');

        $this->assertNotSame(FixtureStatus::Finished, $knockout->fresh()->status);
        $this->assertSame(BatchStatus::Open, $batch->fresh()->status);
    }

    public function test_a_non_admin_cannot_approve(): void
    {
        $this->proposeAllGroupResults($this->openBatch());

        $this->actingAs(User::factory()->create())
            ->post(route('games.scores.approve', $this->game))
            ->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->post(route('games.scores.approve', $this->game))
            ->assertRedirect(route('login'));
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        config()->set('admin.emails', [$admin->email]);

        return $admin;
    }

    private function openBatch(): ScoreBatch
    {
        return $this->tournament->scoreBatches()->firstOrCreate(['status' => BatchStatus::Open]);
    }

    private function proposeAllGroupResults(ScoreBatch $batch): void
    {
        $rule = $this->seedOrderScores();

        foreach ($this->tournament->groups()->with('teams')->get() as $group) {
            $positions = $group->teams->mapWithKeys(fn ($team) => [$team->id => $team->pivot->position]);

            foreach ($group->fixtures()->get() as $fixture) {
                [$home, $away] = $rule($positions[$fixture->home_team_id], $positions[$fixture->away_team_id]);

                ScoreProposal::create([
                    'score_batch_id' => $batch->id,
                    'fixture_id' => $fixture->id,
                    'home_goals' => $home,
                    'away_goals' => $away,
                    'winner_team_id' => $home > $away ? $fixture->home_team_id : $fixture->away_team_id,
                    'status' => ProposalStatus::Pending,
                ]);
            }
        }
    }
}
