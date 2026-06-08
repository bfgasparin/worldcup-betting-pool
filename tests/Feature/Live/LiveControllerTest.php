<?php

namespace Tests\Feature\Live;

use App\Enums\FixtureStatus;
use App\Enums\LiveStatus;
use App\Models\Entry;
use App\Models\Fixture;
use App\Models\FixtureLiveState;
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
