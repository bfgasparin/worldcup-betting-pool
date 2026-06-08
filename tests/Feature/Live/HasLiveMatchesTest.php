<?php

namespace Tests\Feature\Live;

use App\Enums\FixtureStatus;
use App\Enums\LiveStatus;
use App\Models\Entry;
use App\Models\Fixture;
use App\Models\FixtureLiveState;
use App\Models\Pool;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Live\HasLiveMatches;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasLiveMatchesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_true_for_a_member_of_a_pool_with_a_live_fixture(): void
    {
        $user = User::factory()->create();
        $tournament = Tournament::factory()->create();
        $pool = Pool::factory()->for($tournament)->create();
        Entry::factory()->create(['pool_id' => $pool->id, 'user_id' => $user->id]);

        $fixture = Fixture::factory()->for($tournament)->create(['status' => FixtureStatus::Live]);
        FixtureLiveState::factory()->for($fixture)->create(['status' => LiveStatus::Live]);

        $this->assertTrue(app(HasLiveMatches::class)->forUser($user));
    }

    public function test_it_is_false_when_the_joined_tournament_has_no_live_fixture(): void
    {
        $user = User::factory()->create();
        $tournament = Tournament::factory()->create();
        $pool = Pool::factory()->for($tournament)->create();
        Entry::factory()->create(['pool_id' => $pool->id, 'user_id' => $user->id]);

        Fixture::factory()->for($tournament)->create(['status' => FixtureStatus::Scheduled]);

        $this->assertFalse(app(HasLiveMatches::class)->forUser($user));
    }

    public function test_it_is_false_for_a_user_who_has_not_joined_the_live_tournament(): void
    {
        $tournament = Tournament::factory()->create();
        $pool = Pool::factory()->for($tournament)->create();
        Entry::factory()->create(['pool_id' => $pool->id, 'user_id' => User::factory()->create()->id]);

        $fixture = Fixture::factory()->for($tournament)->create(['status' => FixtureStatus::Live]);
        FixtureLiveState::factory()->for($fixture)->create(['status' => LiveStatus::Live]);

        $outsider = User::factory()->create();
        $this->assertFalse(app(HasLiveMatches::class)->forUser($outsider));
    }

    public function test_it_is_false_for_a_guest(): void
    {
        $this->assertFalse(app(HasLiveMatches::class)->forUser(null));
    }

    public function test_it_is_shared_with_inertia(): void
    {
        $user = User::factory()->create();
        $tournament = Tournament::factory()->create();
        $pool = Pool::factory()->for($tournament)->create();
        Entry::factory()->create(['pool_id' => $pool->id, 'user_id' => $user->id]);
        $fixture = Fixture::factory()->for($tournament)->create(['status' => FixtureStatus::Live]);
        FixtureLiveState::factory()->for($fixture)->create(['status' => LiveStatus::Live]);

        $this->actingAs($user)
            ->get(route('pools.index'))
            ->assertInertia(fn ($page) => $page->where('hasLiveMatches', true));
    }
}
