<?php

namespace Tests\Feature\Scoring;

use App\Enums\ProposalStatus;
use App\Models\Fixture;
use App\Models\Team;
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

    public function test_a_decisive_knockout_derives_the_advancing_team_from_the_score(): void
    {
        [$home, $away] = $this->twoTeams();
        $fixture = $this->endedKnockout($home, $away);

        $this->actingAs($this->admin())
            ->patch(route('games.scores.proposal', [$this->tournament, $fixture]), [
                'home_goals' => 2,
                'away_goals' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('score_proposals', [
            'fixture_id' => $fixture->id,
            'winner_team_id' => $home->id,
        ]);
    }

    public function test_a_decisive_knockout_overrides_a_contradictory_winner(): void
    {
        [$home, $away] = $this->twoTeams();
        $fixture = $this->endedKnockout($home, $away);

        $this->actingAs($this->admin())
            ->patch(route('games.scores.proposal', [$this->tournament, $fixture]), [
                'home_goals' => 2,
                'away_goals' => 1,
                'winner_team_id' => $away->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('score_proposals', [
            'fixture_id' => $fixture->id,
            'winner_team_id' => $home->id,
        ]);
    }

    public function test_a_drawn_knockout_persists_the_chosen_winner(): void
    {
        [$home, $away] = $this->twoTeams();
        $fixture = $this->endedKnockout($home, $away);

        $this->actingAs($this->admin())
            ->patch(route('games.scores.proposal', [$this->tournament, $fixture]), [
                'home_goals' => 1,
                'away_goals' => 1,
                'winner_team_id' => $away->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('score_proposals', [
            'fixture_id' => $fixture->id,
            'winner_team_id' => $away->id,
        ]);
    }

    public function test_a_drawn_knockout_requires_a_winner(): void
    {
        [$home, $away] = $this->twoTeams();
        $fixture = $this->endedKnockout($home, $away);

        $this->actingAs($this->admin())
            ->from(route('games.scores.review', $this->tournament))
            ->patch(route('games.scores.proposal', [$this->tournament, $fixture]), [
                'home_goals' => 1,
                'away_goals' => 1,
            ])
            ->assertSessionHasErrors('winner_team_id');

        $this->assertDatabaseCount('score_proposals', 0);
    }

    public function test_a_drawn_knockout_winner_must_be_one_of_the_two_teams(): void
    {
        $teams = $this->threeTeams();
        $fixture = $this->endedKnockout($teams[0], $teams[1]);

        $this->actingAs($this->admin())
            ->from(route('games.scores.review', $this->tournament))
            ->patch(route('games.scores.proposal', [$this->tournament, $fixture]), [
                'home_goals' => 1,
                'away_goals' => 1,
                'winner_team_id' => $teams[2]->id,
            ])
            ->assertSessionHasErrors('winner_team_id');

        $this->assertDatabaseCount('score_proposals', 0);
    }

    public function test_a_group_fixture_never_stores_a_winner(): void
    {
        $fixture = $this->markEnded($this->firstGroupFixture());

        $this->actingAs($this->admin())
            ->patch(route('games.scores.proposal', [$this->tournament, $fixture]), [
                'home_goals' => 2,
                'away_goals' => 1,
                'winner_team_id' => $fixture->home_team_id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('score_proposals', [
            'fixture_id' => $fixture->id,
            'winner_team_id' => null,
        ]);
    }

    public function test_an_incomplete_knockout_score_stores_no_winner(): void
    {
        [$home, $away] = $this->twoTeams();
        $fixture = $this->endedKnockout($home, $away);

        $this->actingAs($this->admin())
            ->patch(route('games.scores.proposal', [$this->tournament, $fixture]), [
                'home_goals' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('score_proposals', [
            'fixture_id' => $fixture->id,
            'home_goals' => 1,
            'winner_team_id' => null,
        ]);
    }

    public function test_an_unresolved_knockout_does_not_store_a_winner(): void
    {
        // A knockout can be over before its participants are projected; a pick can't be trusted
        // (or validated) yet, so none is stored — the approval gate still backstops a null winner.
        $home = $this->twoTeams()[0];
        $fixture = $this->markEnded($this->firstKnockoutFixture());

        $this->actingAs($this->admin())
            ->patch(route('games.scores.proposal', [$this->tournament, $fixture]), [
                'home_goals' => 1,
                'away_goals' => 1,
                'winner_team_id' => $home->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('score_proposals', [
            'fixture_id' => $fixture->id,
            'winner_team_id' => null,
        ]);
    }

    private function firstGroupFixture(): Fixture
    {
        return $this->tournament->groupFixtures()->orderBy('match_number')->firstOrFail();
    }

    private function firstKnockoutFixture(): Fixture
    {
        return $this->tournament->knockoutFixtures()->orderBy('match_number')->firstOrFail();
    }

    /**
     * An ended knockout fixture with two concrete participants, as the bracket projector would
     * leave it once the prior round is in.
     */
    private function endedKnockout(Team $home, Team $away): Fixture
    {
        $fixture = $this->firstKnockoutFixture();
        $fixture->update(['home_team_id' => $home->id, 'away_team_id' => $away->id]);

        return $this->markEnded($fixture);
    }

    /**
     * @return array{Team, Team}
     */
    private function twoTeams(): array
    {
        $teams = $this->threeTeams();

        return [$teams[0], $teams[1]];
    }

    /**
     * @return array{Team, Team, Team}
     */
    private function threeTeams(): array
    {
        $teams = Team::query()->orderBy('id')->take(3)->get();

        return [$teams[0], $teams[1], $teams[2]];
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        config()->set('admin.emails', [$admin->email]);

        return $admin;
    }
}
