<?php

namespace Tests\Feature\Live;

use App\Enums\FixtureStatus;
use App\Enums\LiveStatus;
use App\Models\Entry;
use App\Models\Fixture;
use App\Models\FixtureLiveState;
use App\Models\Group;
use App\Models\GroupPrediction;
use App\Models\Pool;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The live pages aren't in the built Vite manifest yet; stub Vite so the initial Inertia
        // render resolves and we can assert the prop contract.
        $this->withoutVite();
    }

    public function test_index_auto_opens_the_only_live_tournament(): void
    {
        $user = User::factory()->create();
        $tournament = $this->liveTournamentJoinedBy($user);

        $this->actingAs($user)
            ->get(route('live.index'))
            ->assertRedirect(route('live.show', $tournament));
    }

    public function test_index_shows_the_picker_when_several_tournaments_are_live(): void
    {
        $user = User::factory()->create();
        $this->liveTournamentJoinedBy($user);
        $this->liveTournamentJoinedBy($user);

        $this->actingAs($user)
            ->get(route('live.index'))
            ->assertInertia(fn ($page) => $page->component('live/index', false)->has('tournaments', 2));
    }

    public function test_index_is_empty_when_nothing_is_live(): void
    {
        $user = User::factory()->create();
        $tournament = Tournament::factory()->create();
        $pool = Pool::factory()->for($tournament)->create();
        Entry::factory()->create(['pool_id' => $pool->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('live.index'))
            ->assertInertia(fn ($page) => $page->component('live/index', false)->has('tournaments', 0));
    }

    public function test_show_is_forbidden_for_a_non_member(): void
    {
        $tournament = $this->liveTournamentJoinedBy(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->get(route('live.show', $tournament))
            ->assertForbidden();
    }

    public function test_show_returns_live_fixtures_and_projected_boards(): void
    {
        $user = User::factory()->create();
        $tournament = Tournament::factory()->create();
        $pool = Pool::factory()->for($tournament)->phasedBracket()->create();
        $entry = Entry::factory()->create(['pool_id' => $pool->id, 'user_id' => $user->id]);

        $fixture = Fixture::factory()->for($tournament)->create(['status' => FixtureStatus::Live]);
        GroupPrediction::create(['entry_id' => $entry->id, 'fixture_id' => $fixture->id, 'home_goals' => 1, 'away_goals' => 0]);
        FixtureLiveState::factory()->for($fixture)->create(['status' => LiveStatus::Live, 'home_goals' => 1, 'away_goals' => 0]);

        $this->actingAs($user)
            ->get(route('live.show', $tournament))
            ->assertInertia(fn ($page) => $page
                ->component('live/show', false)
                ->where('tournament.slug', $tournament->slug)
                ->has('pools', 1)
                ->has('pools.0.boards.overall', 1)
                ->has('pools.0.boards.overall.0.live_gain')
                ->has('liveFixtures', 1));
    }

    public function test_show_exposes_per_fixture_picks_scoped_to_the_pool_for_live_fixtures_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $tournament = Tournament::factory()->create();
        $pool = Pool::factory()->for($tournament)->phasedBracket()->create();

        $mine = Entry::factory()->create(['pool_id' => $pool->id, 'user_id' => $user->id]);
        $theirs = Entry::factory()->create(['pool_id' => $pool->id, 'user_id' => $other->id]);

        // Anchor both fixtures to a group OF THIS tournament so the projection reaches them — the
        // FixtureFactory's default group belongs to its own fresh tournament.
        $group = Group::factory()->for($tournament)->create();
        $live = Fixture::factory()->for($tournament)->for($group)->create(['status' => FixtureStatus::Live]);
        $scheduled = Fixture::factory()->for($tournament)->for($group)->create(['status' => FixtureStatus::Scheduled]);

        GroupPrediction::create(['entry_id' => $mine->id, 'fixture_id' => $live->id, 'home_goals' => 2, 'away_goals' => 0]);
        GroupPrediction::create(['entry_id' => $theirs->id, 'fixture_id' => $live->id, 'home_goals' => 1, 'away_goals' => 1]);
        // A prediction on a not-live fixture must never leak through the picks map.
        GroupPrediction::create(['entry_id' => $theirs->id, 'fixture_id' => $scheduled->id, 'home_goals' => 5, 'away_goals' => 5]);

        FixtureLiveState::factory()->for($live)->create(['status' => LiveStatus::Live, 'home_goals' => 2, 'away_goals' => 0]);

        $this->actingAs($user)
            ->get(route('live.show', $tournament))
            ->assertInertia(fn ($page) => $page
                ->component('live/show', false)
                ->has("pools.0.fixture_picks.{$live->id}", 2)
                ->missing("pools.0.fixture_picks.{$scheduled->id}")
                ->where("pools.0.fixture_picks.{$live->id}", fn ($picks) => collect($picks)->contains(
                    fn ($pick) => $pick['entry_id'] === $mine->id
                        && $pick['home_goals'] === 2
                        && $pick['away_goals'] === 0
                        && array_key_exists('points', $pick),
                )));
    }

    private function liveTournamentJoinedBy(User $user): Tournament
    {
        $tournament = Tournament::factory()->create();
        $pool = Pool::factory()->for($tournament)->create();
        Entry::factory()->create(['pool_id' => $pool->id, 'user_id' => $user->id]);

        $fixture = Fixture::factory()->for($tournament)->create(['status' => FixtureStatus::Live]);
        FixtureLiveState::factory()->for($fixture)->create(['status' => LiveStatus::Live]);

        return $tournament;
    }
}
