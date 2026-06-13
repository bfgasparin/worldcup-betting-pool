<?php

namespace Tests\Feature\Scoring;

use App\Enums\FixtureStatus;
use App\Enums\OrderingScope;
use App\Enums\ProposalStatus;
use App\Models\Fixture;
use App\Models\FixtureLiveState;
use App\Models\Pool;
use App\Models\ScoreBatch;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Predictions\TieResolutionState;
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

    private Pool $pool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
        $this->pool = $this->tournament->pools()->where('slug', 'world-cup-2026-ffa')->firstOrFail();
    }

    public function test_an_admin_sees_only_ended_matches_to_review(): void
    {
        $ended = $this->markEnded($this->firstGroupFixture());

        $this->actingAs($this->admin())
            ->get(route('manage.scores.review', $this->tournament))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('manage/scores')
                // Review is tournament-scoped now (pool-agnostic admin area).
                ->where('tournament.slug', $this->tournament->slug)
                ->where('tournament.name', $this->tournament->name)
                ->has('rows', 1)
                ->where('rows.0.fixture_id', $ended->id)
                ->where('rows.0.has_ended', true)
            );
    }

    public function test_a_non_admin_cannot_review(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('manage.scores.review', $this->tournament))
            ->assertForbidden();
    }

    public function test_updating_a_proposal_upserts_it_in_the_open_batch(): void
    {
        $fixture = $this->markEnded($this->firstGroupFixture());

        $this->actingAs($this->admin())
            ->patch(route('manage.scores.proposal', [$this->tournament, $fixture]), [
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
            ->patch(route('manage.scores.proposal', [$this->tournament, $fixture]), [
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
            ->from(route('manage.scores.review', $this->tournament))
            ->patch(route('manage.scores.proposal', [$this->tournament, $fixture]), [
                'home_goals' => 2,
                'away_goals' => 1,
            ])
            ->assertSessionHasErrors('home_goals');

        $this->assertDatabaseCount('score_proposals', 0);
    }

    public function test_a_score_can_be_saved_once_the_admin_ends_the_live_match_before_full_time(): void
    {
        // Reproduces the report: the admin ended the live match in Live Control well before the
        // 150-minute mark, so the live scoreboard is Ended even though kickoff was minutes ago.
        // The ended event is the source of truth, so the score saves rather than being rejected.
        $fixture = $this->endedLiveBeforeFullTime($this->firstGroupFixture());

        $this->actingAs($this->admin())
            ->from(route('manage.scores.review', $this->tournament))
            ->patch(route('manage.scores.proposal', [$this->tournament, $fixture]), [
                'home_goals' => 3,
                'away_goals' => 1,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('score_proposals', [
            'fixture_id' => $fixture->id,
            'home_goals' => 3,
            'away_goals' => 1,
            'status' => ProposalStatus::Edited->value,
        ]);
    }

    public function test_a_live_match_ended_before_full_time_shows_as_ended_on_review(): void
    {
        $fixture = $this->endedLiveBeforeFullTime($this->firstGroupFixture());

        $this->actingAs($this->admin())
            ->get(route('manage.scores.review', $this->tournament))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('manage/scores')
                ->has('rows', 1)
                ->where('rows.0.fixture_id', $fixture->id)
                ->where('rows.0.has_ended', true)
            );
    }

    public function test_a_decisive_knockout_derives_the_advancing_team_from_the_score(): void
    {
        [$home, $away] = $this->twoTeams();
        $fixture = $this->endedKnockout($home, $away);

        $this->actingAs($this->admin())
            ->patch(route('manage.scores.proposal', [$this->tournament, $fixture]), [
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
            ->patch(route('manage.scores.proposal', [$this->tournament, $fixture]), [
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
            ->patch(route('manage.scores.proposal', [$this->tournament, $fixture]), [
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
            ->from(route('manage.scores.review', $this->tournament))
            ->patch(route('manage.scores.proposal', [$this->tournament, $fixture]), [
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
            ->from(route('manage.scores.review', $this->tournament))
            ->patch(route('manage.scores.proposal', [$this->tournament, $fixture]), [
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
            ->patch(route('manage.scores.proposal', [$this->tournament, $fixture]), [
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
            ->patch(route('manage.scores.proposal', [$this->tournament, $fixture]), [
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
            ->patch(route('manage.scores.proposal', [$this->tournament, $fixture]), [
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

    /**
     * A fixture the admin took live and ended only minutes after kickoff — the live scoreboard is
     * Ended while the clock is still far short of full time.
     */
    private function endedLiveBeforeFullTime(Fixture $fixture): Fixture
    {
        $fixture->update([
            'status' => FixtureStatus::Live,
            'kicks_off_at' => now()->subMinutes(10),
        ]);
        FixtureLiveState::factory()->for($fixture)->ended()->withScore(0, 0)->create();

        return $fixture;
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

    public function test_ordering_one_official_group_tie_keeps_a_second_tie_in_the_same_group_resolved(): void
    {
        // Clean official winners everywhere except group A, whose results sit in the open review
        // batch (orderings happen during review — once published the order is locked) and hold two
        // independent ties: positions 1 & 2 level on 7pts and positions 3 & 4 level on 1pt.
        $others = $this->tournament->groups()->where('name', '!=', 'A')->pluck('name')->all();
        $this->recordOfficialGroupResults($this->tournament, fn (int $h, int $a): array => $h < $a ? [1, 0] : [0, 1], onlyGroups: $others, resolveTies: false);

        $batch = ScoreBatch::openFor($this->tournament);
        $this->proposeGroupResults($batch, $this->twoClusterScores(), ['A']);

        [$first, $second, $third, $fourth] = $this->groupTeamIdsByPosition('A');

        $this->assertCount(2, (new TieResolutionState)->forTournament($this->tournament, $batch)->groupTies['A'] ?? []);

        $this->confirmOrdering([$second, $first]);

        $afterFirst = (new TieResolutionState)->forTournament($this->tournament->fresh(), $batch);
        $this->assertFalse($afterFirst->groupsResolved);
        $this->assertSame($second, $afterFirst->standings['A']->winner());
        $this->assertNull($afterFirst->standings['A']->thirdStanding());

        // Ordering the 3rd/4th tie must NOT wipe the just-saved 1st/2nd ordering.
        $this->confirmOrdering([$fourth, $third]);

        $afterSecond = (new TieResolutionState)->forTournament($this->tournament->fresh(), $batch);
        $this->assertTrue($afterSecond->groupsResolved, 'Both group ties should be resolved.');
        $this->assertSame($second, $afterSecond->standings['A']->winner(), 'The first tie must stay resolved.');
        $this->assertSame($fourth, $afterSecond->standings['A']->thirdStanding()->teamId);

        $row = $this->tournament->groupOrderings()->where('scope', OrderingScope::WithinGroup->value)->sole();
        $this->assertEqualsCanonicalizing([$first, $second, $third, $fourth], $row->ordered_team_ids);
    }

    /**
     * PUT a within-group official ordering for group A as an admin.
     *
     * @param  list<int>  $orderedTeamIds
     */
    private function confirmOrdering(array $orderedTeamIds): void
    {
        $this->actingAs($this->admin())
            ->put(route('manage.scores.ordering', $this->tournament), [
                'scope' => OrderingScope::WithinGroup->value,
                'group' => 'A',
                'ordered_team_ids' => $orderedTeamIds,
            ])
            ->assertRedirect();
    }

    /**
     * The group's team ids in seed-position order [pos1, pos2, pos3, pos4].
     *
     * @return list<int>
     */
    private function groupTeamIdsByPosition(string $groupName): array
    {
        return $this->tournament->groups()->where('name', $groupName)->firstOrFail()
            ->teams()->orderByPivot('position')->pluck('teams.id')->all();
    }

    /**
     * A position-pair result rule leaving two independent unbreakable ties in a group: positions
     * 1 & 2 draw and each beat 3 & 4 (level on 7pts), 3 & 4 draw and lose the rest (level on 1pt).
     * Orientation-independent, so it does not assume the seeder's home/away order.
     *
     * @return callable(int, int): array{int, int}
     */
    private function twoClusterScores(): callable
    {
        return function (int $homePosition, int $awayPosition): array {
            $pair = [$homePosition, $awayPosition];
            sort($pair);

            if ($pair === [1, 2] || $pair === [3, 4]) {
                return [0, 0];
            }

            return $homePosition === $pair[0] ? [1, 0] : [0, 1];
        };
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
