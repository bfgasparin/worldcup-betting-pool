<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\Fixture;
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

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
        $this->user = User::factory()->create();
    }

    public function test_guests_are_redirected_from_the_predict_page(): void
    {
        $this->get(route('games.predict.edit', 'world-cup-2026'))->assertRedirect(route('login'));
    }

    public function test_visiting_predict_creates_a_draft_entry_and_renders_the_wizard(): void
    {
        $this->actingAs($this->user)
            ->get(route('games.predict.edit', 'world-cup-2026'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('games/predict')
                ->where('game.slug', 'world-cup-2026')
                ->where('game.can_edit', true)
                ->has('groups', 12)
                ->has('groups.0.standings', 4)
                ->where('groups.0.teams.0.flag_url', '/flags/MEX.svg')
                ->has('groups.0.standings.0.form')
                ->has('bracket', 6)
                ->where('bracket.0.phase_key', 'round_of_32')
                ->has('bracket.0.fixtures', 16)
            );

        $entry = $this->entry();
        $this->assertNotNull($entry);
        $this->assertSame(32, $entry->knockoutPredictions()->count());
    }

    public function test_predict_page_prefills_existing_predictions(): void
    {
        $entry = Entry::factory()->for($this->tournament)->for($this->user)->create();
        $this->predictGroup($entry, $this->tournament, 'A', $this->seedOrderScores());

        $this->actingAs($this->user)
            ->get(route('games.predict.edit', 'world-cup-2026'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('groups.0.fixtures.0.home_goals', 1)
                ->where('groups.0.fixtures.0.away_goals', 0)
            );
    }

    public function test_saving_group_predictions_persists_and_recomputes_the_round_of_32(): void
    {
        $fixtures = $this->groupFixtures('A');
        $scores = $this->seedOrderScores();

        $payload = ['predictions' => $fixtures->map(fn (Fixture $fixture, int $index): array => [
            'fixture_id' => $fixture->id,
            'home_goals' => $scores[$index][0],
            'away_goals' => $scores[$index][1],
        ])->all()];

        $this->actingAs($this->user)
            ->put(route('games.predict.group', 'world-cup-2026'), $payload)
            ->assertRedirect(route('games.predict.edit', 'world-cup-2026'));

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

    public function test_saving_knockout_predictions_persists_advancing_and_scores(): void
    {
        $entry = Entry::factory()->for($this->tournament)->for($this->user)->create();
        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores());
        (new BracketResolver)->persist($entry);

        $r32 = $this->knockoutFixture($this->tournament, 'R32-1');
        $homeTeamId = $entry->knockoutPredictions()->where('fixture_id', $r32->id)->value('predicted_home_team_id');

        $payload = ['predictions' => [[
            'fixture_id' => $r32->id,
            'home_goals' => 2,
            'away_goals' => 1,
            'advancing_team_id' => $homeTeamId,
        ]]];

        $this->actingAs($this->user)
            ->put(route('games.predict.knockout', 'world-cup-2026'), $payload)
            ->assertRedirect(route('games.predict.edit', 'world-cup-2026'));

        $this->assertDatabaseHas('knockout_predictions', [
            'entry_id' => $entry->id,
            'fixture_id' => $r32->id,
            'home_goals' => 2,
            'away_goals' => 1,
            'advancing_team_id' => $homeTeamId,
        ]);
    }

    public function test_advancing_team_must_be_one_of_the_resolved_teams(): void
    {
        $entry = Entry::factory()->for($this->tournament)->for($this->user)->create();
        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores());
        (new BracketResolver)->persist($entry);

        $r32 = $this->knockoutFixture($this->tournament, 'R32-1'); // Runner-up Group A vs Runner-up Group B
        $notInMatch = $this->groupTeamId('B', 1); // a group winner, in neither slot

        $payload = ['predictions' => [[
            'fixture_id' => $r32->id,
            'home_goals' => 1,
            'away_goals' => 0,
            'advancing_team_id' => $notInMatch,
        ]]];

        $this->actingAs($this->user)
            ->put(route('games.predict.knockout', 'world-cup-2026'), $payload)
            ->assertSessionHasErrors('predictions.0.advancing_team_id');
    }

    public function test_group_save_rejects_a_fixture_from_another_tournament(): void
    {
        $foreignFixture = Fixture::factory()->create();

        $payload = ['predictions' => [[
            'fixture_id' => $foreignFixture->id,
            'home_goals' => 1,
            'away_goals' => 0,
        ]]];

        $this->actingAs($this->user)
            ->put(route('games.predict.group', 'world-cup-2026'), $payload)
            ->assertSessionHasErrors('predictions.0.fixture_id');
    }

    public function test_group_save_rejects_goals_out_of_range(): void
    {
        $fixture = $this->groupFixtures('A')->first();

        $payload = ['predictions' => [[
            'fixture_id' => $fixture->id,
            'home_goals' => 100,
            'away_goals' => 0,
        ]]];

        $this->actingAs($this->user)
            ->put(route('games.predict.group', 'world-cup-2026'), $payload)
            ->assertSessionHasErrors('predictions.0.home_goals');
    }

    public function test_saving_is_forbidden_once_predictions_are_locked(): void
    {
        $this->tournament->update(['predictions_lock_at' => now()->subDay()]);
        $fixture = $this->groupFixtures('A')->first();

        $payload = ['predictions' => [[
            'fixture_id' => $fixture->id,
            'home_goals' => 1,
            'away_goals' => 0,
        ]]];

        $this->actingAs($this->user)
            ->put(route('games.predict.group', 'world-cup-2026'), $payload)
            ->assertForbidden();

        $this->assertDatabaseCount('entries', 0);
    }

    public function test_locked_tournament_renders_a_read_only_wizard(): void
    {
        $this->tournament->update(['predictions_lock_at' => now()->subDay()]);

        $this->actingAs($this->user)
            ->get(route('games.predict.edit', 'world-cup-2026'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('games/predict')
                ->where('game.can_edit', false)
            );

        $this->assertDatabaseCount('entries', 0);
    }

    public function test_a_user_cannot_change_another_users_predictions(): void
    {
        $other = User::factory()->create();
        $otherEntry = Entry::factory()->for($this->tournament)->for($other)->create();
        $fixture = $this->groupFixtures('A')->first();
        $this->predictGroup($otherEntry, $this->tournament, 'A', $this->seedOrderScores());

        $payload = ['predictions' => [[
            'fixture_id' => $fixture->id,
            'home_goals' => 5,
            'away_goals' => 5,
        ]]];

        $this->actingAs($this->user)
            ->put(route('games.predict.group', 'world-cup-2026'), $payload)
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

    private function entry(): ?Entry
    {
        return Entry::where('tournament_id', $this->tournament->id)
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
