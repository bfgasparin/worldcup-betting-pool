<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\Fixture;
use App\Models\Pool;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Predictions\BracketResolver;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithPredictions;
use Tests\TestCase;

class PredictionControllerTest extends TestCase
{
    use InteractsWithPredictions;
    use RefreshDatabase;

    private Tournament $tournament;

    private Pool $pool;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
        $this->pool = $this->tournament->pools()->where('slug', 'world-cup-2026-ffa')->firstOrFail();
        $this->user = User::factory()->create();
    }

    public function test_guests_are_redirected_from_the_predict_page(): void
    {
        $this->get(route('pools.predict.edit', 'world-cup-2026-ffa'))->assertRedirect(route('login'));
    }

    public function test_visiting_predict_without_joining_redirects_to_the_pool(): void
    {
        $this->actingAs($this->user)
            ->get(route('pools.predict.edit', 'world-cup-2026-ffa'))
            ->assertRedirect(route('pools.show', 'world-cup-2026-ffa'));

        // Predictions now require joining first, so merely visiting creates no entry.
        $this->assertDatabaseCount('entries', 0);
    }

    public function test_visiting_predict_after_joining_renders_the_wizard(): void
    {
        Entry::factory()->for($this->pool)->for($this->user)->create();

        $this->actingAs($this->user)
            ->get(route('pools.predict.edit', 'world-cup-2026-ffa'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('pools/predict')
                ->where('pool.slug', 'world-cup-2026-ffa')
                ->where('pool.can_edit', true)
                // The predict page (and its sidebar) carries the pool identity to disambiguate siblings.
                ->where('pool.source', 'FF&A')
                ->where('pool.accent', 'pitch')
                ->where('pool.scoring_label', 'Upfront Bracket')
                ->has('groups', 12)
                ->has('groups.0.standings', 4)
                ->where('groups.0.teams.0.flag_url', '/flags/MEX.svg')
                ->has('groups.0.standings.0.form')
                ->has('bracket', 6)
                ->where('bracket.0.phase_key', 'round_of_32')
                ->has('bracket.0.fixtures', 16)
            );

        // The upfront bracket is cascaded onto the joined player's knockout rows on first visit.
        $entry = $this->entry();
        $this->assertNotNull($entry);
        $this->assertSame(32, $entry->knockoutPredictions()->count());
    }

    public function test_predict_group_fixtures_carry_their_matchday_key(): void
    {
        Entry::factory()->for($this->pool)->for($this->user)->create();

        $this->actingAs($this->user)
            ->get(route('pools.predict.edit', 'world-cup-2026-ffa'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                // The wizard marks each group-stage row with its matchday, like the pool page.
                ->where('groups.0.fixtures.0.matchday_key', 'group-1')
                // But the wizard has no view switcher, so it ships no matchday timeline.
                ->missing('matchdays')
            );
    }

    public function test_predict_wizard_exposes_resume_and_filter_inputs(): void
    {
        // The wizard's auto-open, per-step "N left" badges, and "needs prediction" filter are all
        // client-side, keyed off the pool's strategy (upfront vs phased), each group fixture's goals,
        // each knockout phase's window, and each pick's advancing team. Guard that contract.
        Entry::factory()->for($this->pool)->for($this->user)->create();

        $this->actingAs($this->user)
            ->get(route('pools.predict.edit', 'world-cup-2026-ffa'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pool.scoring_strategy', 'upfront-bracket')
                ->where('groups.0.fixtures.0.home_goals', null)
                ->where('groups.0.fixtures.0.away_goals', null)
                ->where('bracket.0.window', 'open')
                ->where('bracket.0.fixtures.0.advancing_team_id', null)
                ->etc()
            );
    }

    public function test_a_complete_group_still_surfaces_its_unresolved_tie(): void
    {
        // A four-way 0–0 group is complete yet level on every tiebreaker, so it carries an
        // unresolved tie cluster the player must order. The "needs prediction" filter keys off this
        // contract to keep the tie panel reachable once every fixture is scored.
        $entry = Entry::factory()->for($this->pool)->for($this->user)->create();
        $this->predictAllGroups(
            $entry,
            $this->tournament,
            fn (int $home, int $away): array => [0, 0],
            resolveTies: false,
        );

        $this->actingAs($this->user)
            ->get(route('pools.predict.edit', 'world-cup-2026-ffa'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                // Every fixture is scored…
                ->where('groups.0.fixtures.0.home_goals', 0)
                ->where('groups.0.fixtures.0.away_goals', 0)
                // …yet the group exposes an unresolved tie of more than one team.
                ->has('groups.0.tied_clusters', 1)
                ->where('groups.0.tied_clusters.0.resolved', false)
                ->has('groups.0.tied_clusters.0.team_ids', 4)
            );
    }

    public function test_predict_page_prefills_existing_predictions(): void
    {
        $entry = Entry::factory()->for($this->pool)->for($this->user)->create();
        $this->predictGroup($entry, $this->tournament, 'A', $this->seedOrderScores());

        $this->actingAs($this->user)
            ->get(route('pools.predict.edit', 'world-cup-2026-ffa'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('groups.0.fixtures.0.home_goals', 1)
                ->where('groups.0.fixtures.0.away_goals', 0)
            );
    }

    public function test_saving_group_predictions_persists_and_recomputes_the_round_of_32(): void
    {
        Entry::factory()->for($this->pool)->for($this->user)->create();
        $fixtures = $this->groupFixtures('A');
        $rule = $this->seedOrderScores();
        $positions = $this->tournament->groups()->where('name', 'A')->firstOrFail()
            ->teams()->get()->mapWithKeys(fn ($team) => [$team->id => $team->pivot->position]);

        $payload = ['predictions' => $fixtures->map(function (Fixture $fixture) use ($rule, $positions): array {
            [$home, $away] = $rule($positions[$fixture->home_team_id], $positions[$fixture->away_team_id]);

            return ['fixture_id' => $fixture->id, 'home_goals' => $home, 'away_goals' => $away];
        })->all()];

        $this->actingAs($this->user)
            ->put(route('pools.predict.group', 'world-cup-2026-ffa'), $payload)
            ->assertRedirect(route('pools.predict.edit', 'world-cup-2026-ffa'));

        $entry = $this->entry();
        $this->assertDatabaseHas('group_predictions', [
            'entry_id' => $entry->id,
            'fixture_id' => $fixtures->first()->id,
            'home_goals' => 1,
            'away_goals' => 0,
        ]);

        // Winner Group A (R32-7 / match 79 home) is now resolved to group A's first seed.
        $this->assertDatabaseHas('knockout_predictions', [
            'entry_id' => $entry->id,
            'fixture_id' => $this->knockoutFixture($this->tournament, 'R32-7')->id,
            'predicted_home_team_id' => $this->groupTeamId('A', 1),
        ]);
    }

    public function test_saving_knockout_predictions_persists_scores_and_derives_advancing(): void
    {
        $entry = Entry::factory()->for($this->pool)->for($this->user)->create();
        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores());
        (new BracketResolver)->persist($entry);

        $r32 = $this->knockoutFixture($this->tournament, 'R32-1');
        $homeTeamId = $entry->knockoutPredictions()->where('fixture_id', $r32->id)->value('predicted_home_team_id');

        // A decisive score sets who advances on its own — no manual pick is sent.
        $payload = ['predictions' => [[
            'fixture_id' => $r32->id,
            'home_goals' => 2,
            'away_goals' => 1,
        ]]];

        $this->actingAs($this->user)
            ->put(route('pools.predict.knockout', 'world-cup-2026-ffa'), $payload)
            ->assertRedirect(route('pools.predict.edit', 'world-cup-2026-ffa'));

        $this->assertDatabaseHas('knockout_predictions', [
            'entry_id' => $entry->id,
            'fixture_id' => $r32->id,
            'home_goals' => 2,
            'away_goals' => 1,
            'advancing_team_id' => $homeTeamId,
        ]);
    }

    public function test_a_decisive_score_overrides_a_contradictory_advancing_pick(): void
    {
        $entry = Entry::factory()->for($this->pool)->for($this->user)->create();
        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores());
        (new BracketResolver)->persist($entry);

        $r32 = $this->knockoutFixture($this->tournament, 'R32-1');
        $prediction = $entry->knockoutPredictions()->where('fixture_id', $r32->id)->firstOrFail();

        // Client claims the away team advances, but the away team lost 2-1.
        $payload = ['predictions' => [[
            'fixture_id' => $r32->id,
            'home_goals' => 2,
            'away_goals' => 1,
            'advancing_team_id' => $prediction->predicted_away_team_id,
        ]]];

        $this->actingAs($this->user)
            ->put(route('pools.predict.knockout', 'world-cup-2026-ffa'), $payload)
            ->assertRedirect(route('pools.predict.edit', 'world-cup-2026-ffa'));

        $this->assertDatabaseHas('knockout_predictions', [
            'entry_id' => $entry->id,
            'fixture_id' => $r32->id,
            'advancing_team_id' => $prediction->predicted_home_team_id,
        ]);
    }

    public function test_a_draw_persists_the_manual_advancing_pick(): void
    {
        $entry = Entry::factory()->for($this->pool)->for($this->user)->create();
        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores());
        (new BracketResolver)->persist($entry);

        $r32 = $this->knockoutFixture($this->tournament, 'R32-1');
        $awayTeamId = $entry->knockoutPredictions()->where('fixture_id', $r32->id)->value('predicted_away_team_id');

        $payload = ['predictions' => [[
            'fixture_id' => $r32->id,
            'home_goals' => 1,
            'away_goals' => 1,
            'advancing_team_id' => $awayTeamId,
        ]]];

        $this->actingAs($this->user)
            ->put(route('pools.predict.knockout', 'world-cup-2026-ffa'), $payload)
            ->assertRedirect(route('pools.predict.edit', 'world-cup-2026-ffa'));

        $this->assertDatabaseHas('knockout_predictions', [
            'entry_id' => $entry->id,
            'fixture_id' => $r32->id,
            'home_goals' => 1,
            'away_goals' => 1,
            'advancing_team_id' => $awayTeamId,
        ]);
    }

    public function test_a_draw_requires_a_manual_advancing_pick(): void
    {
        $entry = Entry::factory()->for($this->pool)->for($this->user)->create();
        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores());
        (new BracketResolver)->persist($entry);

        $r32 = $this->knockoutFixture($this->tournament, 'R32-1');

        $payload = ['predictions' => [[
            'fixture_id' => $r32->id,
            'home_goals' => 1,
            'away_goals' => 1,
        ]]];

        $this->actingAs($this->user)
            ->put(route('pools.predict.knockout', 'world-cup-2026-ffa'), $payload)
            ->assertSessionHasErrors('predictions.0.advancing_team_id');
    }

    public function test_a_drawn_advancing_team_must_be_one_of_the_resolved_teams(): void
    {
        $entry = Entry::factory()->for($this->pool)->for($this->user)->create();
        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores());
        (new BracketResolver)->persist($entry);

        $r32 = $this->knockoutFixture($this->tournament, 'R32-1'); // Runner-up Group A vs Runner-up Group B
        $notInMatch = $this->groupTeamId('B', 1); // a group winner, in neither slot

        $payload = ['predictions' => [[
            'fixture_id' => $r32->id,
            'home_goals' => 1,
            'away_goals' => 1,
            'advancing_team_id' => $notInMatch,
        ]]];

        $this->actingAs($this->user)
            ->put(route('pools.predict.knockout', 'world-cup-2026-ffa'), $payload)
            ->assertSessionHasErrors('predictions.0.advancing_team_id');
    }

    public function test_group_save_rejects_a_fixture_from_another_tournament(): void
    {
        Entry::factory()->for($this->pool)->for($this->user)->create();
        $foreignFixture = Fixture::factory()->create();

        $payload = ['predictions' => [[
            'fixture_id' => $foreignFixture->id,
            'home_goals' => 1,
            'away_goals' => 0,
        ]]];

        $this->actingAs($this->user)
            ->put(route('pools.predict.group', 'world-cup-2026-ffa'), $payload)
            ->assertSessionHasErrors('predictions.0.fixture_id');
    }

    public function test_group_save_rejects_goals_out_of_range(): void
    {
        Entry::factory()->for($this->pool)->for($this->user)->create();
        $fixture = $this->groupFixtures('A')->first();

        $payload = ['predictions' => [[
            'fixture_id' => $fixture->id,
            'home_goals' => 100,
            'away_goals' => 0,
        ]]];

        $this->actingAs($this->user)
            ->put(route('pools.predict.group', 'world-cup-2026-ffa'), $payload)
            ->assertSessionHasErrors('predictions.0.home_goals');
    }

    public function test_saving_is_forbidden_once_predictions_are_locked(): void
    {
        $this->pool->update(['predictions_lock_at' => now()->subDay()]);
        $fixture = $this->groupFixtures('A')->first();

        $payload = ['predictions' => [[
            'fixture_id' => $fixture->id,
            'home_goals' => 1,
            'away_goals' => 0,
        ]]];

        $this->actingAs($this->user)
            ->put(route('pools.predict.group', 'world-cup-2026-ffa'), $payload)
            ->assertForbidden();

        $this->assertDatabaseCount('entries', 0);
    }

    public function test_visiting_a_locked_pool_without_joining_redirects_to_the_pool(): void
    {
        $this->pool->update(['predictions_lock_at' => now()->subDay()]);

        $this->actingAs($this->user)
            ->get(route('pools.predict.edit', 'world-cup-2026-ffa'))
            ->assertRedirect(route('pools.show', 'world-cup-2026-ffa'));

        $this->assertDatabaseCount('entries', 0);
    }

    public function test_a_joined_player_sees_a_read_only_wizard_once_locked(): void
    {
        Entry::factory()->for($this->pool)->for($this->user)->create();
        $this->pool->update(['predictions_lock_at' => now()->subDay()]);

        $this->actingAs($this->user)
            ->get(route('pools.predict.edit', 'world-cup-2026-ffa'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('pools/predict')
                ->where('pool.can_edit', false)
            );
    }

    public function test_a_user_cannot_change_another_users_predictions(): void
    {
        Entry::factory()->for($this->pool)->for($this->user)->create();
        $other = User::factory()->create();
        $otherEntry = Entry::factory()->for($this->pool)->for($other)->create();
        $fixture = $this->groupFixtures('A')->first();
        $this->predictGroup($otherEntry, $this->tournament, 'A', $this->seedOrderScores());

        $payload = ['predictions' => [[
            'fixture_id' => $fixture->id,
            'home_goals' => 5,
            'away_goals' => 5,
        ]]];

        $this->actingAs($this->user)
            ->put(route('pools.predict.group', 'world-cup-2026-ffa'), $payload)
            ->assertRedirect();

        // The other user's prediction is untouched; the acting user gets their own entry.
        $this->assertDatabaseHas('group_predictions', [
            'entry_id' => $otherEntry->id,
            'fixture_id' => $fixture->id,
            'home_goals' => 1,
        ]);
        $this->assertDatabaseHas('group_predictions', [
            'entry_id' => $this->entry()->id,
            'fixture_id' => $fixture->id,
            'home_goals' => 5,
        ]);
        $this->assertNotSame($otherEntry->id, $this->entry()->id);
    }

    public function test_predict_page_reports_completion_false_when_incomplete(): void
    {
        Entry::factory()->for($this->pool)->for($this->user)->create();

        $this->actingAs($this->user)
            ->get(route('pools.predict.edit', 'world-cup-2026-ffa'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('pools/predict')
                ->where('completion.is_complete', false));
    }

    public function test_predict_page_reports_completion_true_when_upfront_fully_predicted(): void
    {
        $entry = Entry::factory()->for($this->pool)->for($this->user)->create();
        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores());
        $this->advanceAllHome($entry, new BracketResolver);

        $this->actingAs($this->user)
            ->get(route('pools.predict.edit', 'world-cup-2026-ffa'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('completion.is_complete', true)
                ->has('completion.open_windows', 1)
                ->where('completion.open_windows.0.phase_key', 'group')
                ->where('completion.open_windows.0.label', 'Your bracket'));
    }

    public function test_predict_page_reports_completion_false_once_locked(): void
    {
        $entry = Entry::factory()->for($this->pool)->for($this->user)->create();
        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores());
        $this->advanceAllHome($entry, new BracketResolver);
        $this->pool->update(['predictions_lock_at' => now()->subDay()]);

        $this->actingAs($this->user)
            ->get(route('pools.predict.edit', 'world-cup-2026-ffa'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('completion.is_complete', false)
                ->has('completion.open_windows', 0));
    }

    public function test_predict_page_reports_completion_true_for_a_complete_phased_group_window(): void
    {
        $phased = $this->tournament->pools()->where('slug', 'world-cup-2026-brothers')->firstOrFail();
        $entry = Entry::factory()->for($phased)->for($this->user)->create();
        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores());

        $this->actingAs($this->user)
            ->get(route('pools.predict.edit', 'world-cup-2026-brothers'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('completion.is_complete', true)
                ->where('completion.open_windows.0.phase_key', 'group')
                ->where('completion.open_windows.0.label', 'Group stage'));
    }

    private function entry(): ?Entry
    {
        return Entry::where('pool_id', $this->pool->id)
            ->where('user_id', $this->user->id)
            ->first();
    }

    /**
     * @return Collection<int, Fixture>
     */
    private function groupFixtures(string $groupName)
    {
        return $this->tournament->groups()->where('name', $groupName)->firstOrFail()
            ->fixtures()->orderBy('match_number')->get();
    }

    private function groupTeamId(string $groupName, int $position): int
    {
        return $this->tournament->groups()->where('name', $groupName)->firstOrFail()
            ->teams()->wherePivot('position', $position)->firstOrFail()->id;
    }
}
