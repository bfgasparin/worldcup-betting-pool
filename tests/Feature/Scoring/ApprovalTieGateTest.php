<?php

namespace Tests\Feature\Scoring;

use App\Enums\BatchStatus;
use App\Enums\OrderingScope;
use App\Enums\ProposalStatus;
use App\Models\Pool;
use App\Models\ScoreBatch;
use App\Models\ScoreProposal;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Predictions\TieResolutionState;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithOfficialResults;
use Tests\Concerns\InteractsWithPredictions;
use Tests\TestCase;

class ApprovalTieGateTest extends TestCase
{
    use InteractsWithOfficialResults;
    use InteractsWithPredictions;
    use RefreshDatabase;

    private Tournament $tournament;

    private Pool $pool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
        $this->pool = $this->tournament->pools()->where('slug', 'world-cup-2026-ffa')->firstOrFail();
    }

    public function test_approval_is_blocked_until_the_thirds_tie_is_ordered(): void
    {
        // Uniform seed-order results leave all twelve thirds tied across the qualifying cut.
        $batch = $this->openBatch();
        $this->proposeGroupResults($batch, $this->seedOrderScores());

        $this->actingAs($this->admin())
            ->post(route('manage.scores.approve', $this->tournament))
            ->assertSessionHasErrors('ties');

        $this->assertSame(BatchStatus::Open, $batch->fresh()->status);
        $this->assertNull($this->knockoutFixture($this->tournament, 'R32-1')->fresh()->home_team_id);

        // The admin orders the tied thirds through the endpoint, then approval goes through.
        $straddling = (new TieResolutionState)->forTournament($this->tournament, $batch)->thirds;

        $this->actingAs($this->admin())
            ->put(route('manage.scores.ordering', $this->tournament), [
                'scope' => OrderingScope::Thirds->value,
                'ordered_team_ids' => $straddling,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin())
            ->post(route('manage.scores.approve', $this->tournament))
            ->assertRedirect(route('manage.scores.review', $this->tournament));

        $this->assertSame(BatchStatus::Approved, $batch->fresh()->status);
        $this->assertNotNull($this->knockoutFixture($this->tournament, 'R32-1')->fresh()->home_team_id);
    }

    public function test_resolved_ties_leave_the_review_screen_after_approval(): void
    {
        $batch = $this->openBatch();
        $this->proposeGroupResults($batch, $this->seedOrderScores());
        $this->resolveProjectedTies($this->tournament, $batch); // admin orders the tied thirds

        // While the batch is open the tie section is shown for resolving.
        $this->actingAs($this->admin())
            ->get(route('manage.scores.review', $this->tournament))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('thirds_tie.resolved', true));

        $this->actingAs($this->admin())
            ->post(route('manage.scores.approve', $this->tournament))
            ->assertRedirect(route('manage.scores.review', $this->tournament));

        // Once approved there is no open batch, so the tie section is gone — like an approved match.
        $this->actingAs($this->admin())
            ->get(route('manage.scores.review', $this->tournament))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tied_groups', [])
                ->where('thirds_tie', null)
                ->etc());
    }

    public function test_a_knockout_review_batch_does_not_resurface_published_group_ties(): void
    {
        // Resolve + approve a group-stage thirds tie, publishing the group results and ordering.
        $batch = $this->openBatch();
        $this->proposeGroupResults($batch, $this->seedOrderScores());
        $this->resolveProjectedTies($this->tournament, $batch);
        $this->actingAs($this->admin())->post(route('manage.scores.approve', $this->tournament));

        // Now review a knockout match in a fresh batch — its proposals touch no group fixtures.
        $r32 = $this->knockoutFixture($this->tournament, 'R32-1')->fresh();
        $knockoutBatch = $this->openBatch();
        ScoreProposal::create([
            'score_batch_id' => $knockoutBatch->id,
            'fixture_id' => $r32->id,
            'home_goals' => 1,
            'away_goals' => 0,
            'winner_team_id' => $r32->home_team_id,
            'status' => ProposalStatus::Pending,
        ]);

        // The already-published group ties must not reappear on the review screen.
        $this->actingAs($this->admin())
            ->get(route('manage.scores.review', $this->tournament))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tied_groups', [])
                ->where('thirds_tie', null)
                ->etc());
    }

    public function test_a_within_group_tie_blocks_approval(): void
    {
        // Every match a goalless draw makes each group a four-way unresolved tie.
        $batch = $this->openBatch();
        $this->proposeGroupResults($batch, fn (int $home, int $away): array => [0, 0]);

        $this->actingAs($this->admin())
            ->post(route('manage.scores.approve', $this->tournament))
            ->assertSessionHasErrors('ties');

        // Ordering a single group is accepted even while the rest stay tied.
        $state = (new TieResolutionState)->forTournament($this->tournament, $batch);
        $groupA = array_merge(...$state->groupTies['A']);

        $this->actingAs($this->admin())
            ->put(route('manage.scores.ordering', $this->tournament), [
                'scope' => OrderingScope::WithinGroup->value,
                'group' => 'A',
                'ordered_team_ids' => $groupA,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tournament_group_orderings', [
            'tournament_id' => $this->tournament->id,
            'scope' => OrderingScope::WithinGroup->value,
        ]);
    }

    public function test_an_incomplete_group_stage_does_not_block_on_ties(): void
    {
        // Only a few groups proposed: the group stage is not complete, so no tie can be required.
        $batch = $this->openBatch();
        $this->proposeGroupResults($batch, $this->seedOrderScores(), ['A', 'B']);

        $this->actingAs($this->admin())
            ->post(route('manage.scores.approve', $this->tournament))
            ->assertSessionHasNoErrors('ties');
    }

    public function test_a_published_thirds_ordering_cannot_be_changed(): void
    {
        // Publish the whole group stage, with the straddling thirds tie ordered by the admin.
        $batch = $this->openBatch();
        $this->proposeGroupResults($batch, $this->seedOrderScores());
        $this->resolveProjectedTies($this->tournament, $batch);
        $this->actingAs($this->admin())->post(route('manage.scores.approve', $this->tournament));

        $ordering = $this->tournament->groupOrderings()
            ->where('scope', OrderingScope::Thirds)
            ->firstOrFail();
        $published = $ordering->ordered_team_ids;

        // The teams stay statistically tied forever, so the set still matches — but the order has
        // been projected onto the bracket players predict against, making it a one-way door.
        $this->actingAs($this->admin())
            ->put(route('manage.scores.ordering', $this->tournament), [
                'scope' => OrderingScope::Thirds->value,
                'ordered_team_ids' => array_reverse($published),
            ])
            ->assertSessionHasErrors('ordered_team_ids');

        $this->assertSame($published, $ordering->fresh()->ordered_team_ids);
    }

    public function test_a_published_group_ordering_cannot_be_changed_but_an_unpublished_group_still_can(): void
    {
        // Group A ends all square; its four-way tie is ordered, re-ordered while still under
        // review (allowed), then published by approving the batch.
        $batch = $this->openBatch();
        $this->proposeGroupResults($batch, fn (int $home, int $away): array => [0, 0], ['A']);

        $state = (new TieResolutionState)->forTournament($this->tournament, $batch);
        $groupA = array_merge(...$state->groupTies['A']);

        $this->actingAs($this->admin())
            ->put(route('manage.scores.ordering', $this->tournament), [
                'scope' => OrderingScope::WithinGroup->value,
                'group' => 'A',
                'ordered_team_ids' => $groupA,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin())
            ->put(route('manage.scores.ordering', $this->tournament), [
                'scope' => OrderingScope::WithinGroup->value,
                'group' => 'A',
                'ordered_team_ids' => array_reverse($groupA),
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin())->post(route('manage.scores.approve', $this->tournament));

        // Group A's results are official and its slots projected: the order is locked.
        $this->actingAs($this->admin())
            ->put(route('manage.scores.ordering', $this->tournament), [
                'scope' => OrderingScope::WithinGroup->value,
                'group' => 'A',
                'ordered_team_ids' => $groupA,
            ])
            ->assertSessionHasErrors('ordered_team_ids');

        // Group B is only proposed (not yet official), so its own tie still accepts an ordering.
        $nextBatch = $this->openBatch();
        $this->proposeGroupResults($nextBatch, fn (int $home, int $away): array => [0, 0], ['B']);

        $stateB = (new TieResolutionState)->forTournament($this->tournament, $nextBatch);
        $groupB = array_merge(...$stateB->groupTies['B']);

        $this->actingAs($this->admin())
            ->put(route('manage.scores.ordering', $this->tournament), [
                'scope' => OrderingScope::WithinGroup->value,
                'group' => 'B',
                'ordered_team_ids' => $groupB,
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_a_stale_ordering_submission_is_rejected(): void
    {
        $batch = $this->openBatch();
        $this->proposeGroupResults($batch, $this->seedOrderScores());

        // Submit a thirds order for a set that is not the current straddling tie.
        $this->actingAs($this->admin())
            ->put(route('manage.scores.ordering', $this->tournament), [
                'scope' => OrderingScope::Thirds->value,
                'ordered_team_ids' => [1, 2, 3],
            ])
            ->assertSessionHasErrors('ordered_team_ids');
    }

    private function openBatch(): ScoreBatch
    {
        return $this->tournament->scoreBatches()->firstOrCreate(['status' => BatchStatus::Open]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        config()->set('admin.emails', [$admin->email]);

        return $admin;
    }
}
