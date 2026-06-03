<?php

namespace Tests\Unit\Models;

use App\Models\Fixture;
use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentTest extends TestCase
{
    use RefreshDatabase;

    public function test_venue_timezones_returns_distinct_venues_mapped_to_their_timezone(): void
    {
        $tournament = Tournament::factory()->create();

        Fixture::factory()->for($tournament)->create(['venue' => 'Atlanta Stadium', 'venue_timezone' => 'America/New_York']);
        Fixture::factory()->for($tournament)->create(['venue' => 'Atlanta Stadium', 'venue_timezone' => 'America/New_York']);
        Fixture::factory()->for($tournament)->create(['venue' => 'Los Angeles Stadium', 'venue_timezone' => 'America/Los_Angeles']);
        Fixture::factory()->for($tournament)->create(['venue' => null, 'venue_timezone' => null]);

        // A venue belonging to another tournament must not leak in.
        Fixture::factory()->create(['venue' => 'Toronto Stadium', 'venue_timezone' => 'America/Toronto']);

        $this->assertSame([
            'Atlanta Stadium' => 'America/New_York',
            'Los Angeles Stadium' => 'America/Los_Angeles',
        ], $tournament->venueTimezones());
    }
}
