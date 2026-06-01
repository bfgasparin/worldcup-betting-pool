<?php

namespace Tests\Feature\Scoring;

use App\Enums\ProposalStatus;
use App\Models\Fixture;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithOfficialResults;
use Tests\TestCase;

class ScoreReviewControllerTest extends TestCase
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

    public function test_an_admin_sees_only_ended_matches_to_review(): void
    {
        $ended = $this->markEnded($this->firstGroupFixture());

        $this->actingAs($this->admin())
            ->get(route('games.scores.review', $this->tournament))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('games/scores/review')
                ->where('game.slug', $this->tournament->slug)
                ->has('rows', 1)
                ->where('rows.0.fixture_id', $ended->id)
                ->where('rows.0.has_ended', true)
            );
    }

    public function test_a_non_admin_cannot_review(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('games.scores.review', $this->tournament))
            ->assertForbidden();
    }

    public function test_updating_a_proposal_upserts_it_in_the_open_batch(): void
    {
        $fixture = $this->markEnded($this->firstGroupFixture());

        $this->actingAs($this->admin())
            ->patch(route('games.scores.proposal', [$this->tournament, $fixture]), [
                'home_goals' => 2,
                'away_goals' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('score_proposals', [
            'fixture_id' => $fixture->id,
            'home_goals' => 2,
            'away_goals' => 1,
            'status' => ProposalStatus::Edited->value,
        ]);
    }

    public function test_a_proposal_can_be_rejected(): void
    {
        $fixture = $this->markEnded($this->firstGroupFixture());

        $this->actingAs($this->admin())
            ->patch(route('games.scores.proposal', [$this->tournament, $fixture]), [
                'home_goals' => 2,
                'away_goals' => 1,
                'rejected' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('score_proposals', [
            'fixture_id' => $fixture->id,
            'status' => ProposalStatus::Rejected->value,
        ]);
    }

    public function test_it_rejects_a_proposal_for_a_match_that_has_not_ended(): void
    {
        // The fixture keeps its seeded (future) kickoff and scheduled status — it is not over.
        $fixture = $this->firstGroupFixture();

        $this->actingAs($this->admin())
            ->from(route('games.scores.review', $this->tournament))
            ->patch(route('games.scores.proposal', [$this->tournament, $fixture]), [
                'home_goals' => 2,
                'away_goals' => 1,
            ])
            ->assertSessionHasErrors('home_goals');

        $this->assertDatabaseCount('score_proposals', 0);
    }

    private function firstGroupFixture(): Fixture
    {
        return $this->tournament->groupFixtures()->orderBy('match_number')->firstOrFail();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        config()->set('admin.emails', [$admin->email]);

        return $admin;
    }
}
