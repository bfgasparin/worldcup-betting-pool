<?php

namespace Tests\Feature\Manage;

use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ManageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(WorldCup2026Seeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        config()->set('admin.emails', [$admin->email]);

        return $admin;
    }

    public function test_admin_sees_the_tournaments_list(): void
    {
        $this->actingAs($this->admin())
            ->get(route('manage.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('manage/index')
                ->has('tournaments', 1)
                ->where('tournaments.0.slug', 'world-cup-2026'));
    }

    public function test_non_admins_cannot_access_the_admin_area(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('manage.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->get(route('manage.scores.review', Tournament::firstOrFail()))
            ->assertForbidden();
    }

    public function test_an_admin_without_a_pool_can_review_and_schedule(): void
    {
        // The admin has joined no pool — tournament management must never require pool membership.
        $tournament = Tournament::firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('manage.scores.review', $tournament))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('manage/scores'));

        $this->actingAs($this->admin())
            ->get(route('manage.schedule.index', $tournament))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('manage/schedule'));
    }
}
