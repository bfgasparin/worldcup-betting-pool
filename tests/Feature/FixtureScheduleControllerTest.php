<?php

namespace Tests\Feature;

use App\Models\Pool;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class FixtureScheduleControllerTest extends TestCase
{
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

    private function admin(): User
    {
        $admin = User::factory()->create();
        config()->set('admin.emails', [$admin->email]);

        return $admin;
    }

    public function test_an_admin_sees_every_fixture_with_venues_and_lock_flags(): void
    {
        $earliest = $this->tournament->groupFixtures()
            ->whereNotNull('kicks_off_at')
            ->orderBy('kicks_off_at')
            ->orderBy('id')
            ->firstOrFail();

        $expectedFixtures = $this->tournament->fixtures()->count();
        $expectedVenues = count($this->tournament->venueTimezones());

        $this->actingAs($this->admin())
            ->get(route('pools.schedule.index', $this->pool))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('pools/schedule/index')
                ->where('pool.slug', $this->pool->slug)
                ->has('rows', $expectedFixtures)
                ->has('venues', $expectedVenues)
                ->where('rows', fn (Collection $rows): bool => $rows->firstWhere('id', $earliest->id)['governs_prediction_lock'] === true)
            );
    }

    public function test_a_non_admin_cannot_manage_the_schedule(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('pools.schedule.index', $this->pool))
            ->assertForbidden();
    }
}
