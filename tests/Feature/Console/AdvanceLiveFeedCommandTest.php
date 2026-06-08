<?php

namespace Tests\Feature\Console;

use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Tournament;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvanceLiveFeedCommandTest extends TestCase
{
    use RefreshDatabase;

    private Tournament $tournament;

    protected function setUp(): void
    {
        parent::setUp();

        config(['scoring.simulated_provider' => true]);

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
    }

    public function test_it_advances_a_named_tournament(): void
    {
        $fixture = $this->dueFixture();

        $this->artisan('live:advance', ['tournament' => $this->tournament->slug])->assertSuccessful();

        $this->assertSame(FixtureStatus::Live, $fixture->fresh()->status);
    }

    public function test_it_advances_every_tournament_by_default(): void
    {
        $fixture = $this->dueFixture();

        $this->artisan('live:advance')->assertSuccessful();

        $this->assertSame(FixtureStatus::Live, $fixture->fresh()->status);
    }

    public function test_it_warns_when_no_tournament_matches(): void
    {
        $this->artisan('live:advance', ['tournament' => 'no-such-slug'])->assertFailed();
    }

    private function dueFixture(): Fixture
    {
        $fixture = $this->tournament->groupFixtures()->where('match_number', 1)->firstOrFail();
        $fixture->update(['kicks_off_at' => now()]);

        return $fixture;
    }
}
