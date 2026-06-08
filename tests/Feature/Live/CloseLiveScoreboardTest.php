<?php

namespace Tests\Feature\Live;

use App\Enums\FixtureStatus;
use App\Enums\LiveStatus;
use App\Models\Fixture;
use App\Models\FixtureLiveState;
use App\Models\ScoreProposal;
use App\Services\Live\CloseLiveScoreboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CloseLiveScoreboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_closing_ends_the_scoreboard_without_proposing(): void
    {
        $fixture = Fixture::factory()->create(['status' => FixtureStatus::Live]);
        FixtureLiveState::factory()->for($fixture)->withScore(2, 1)->create();

        $state = app(CloseLiveScoreboard::class)->close($fixture);

        // The live board closes...
        $this->assertSame(LiveStatus::Ended, $state->status);
        $this->assertNotNull($state->ended_at);
        $this->assertSame(LiveStatus::Ended, $fixture->fresh()->liveState->status);

        // ...the fixture stays Live with no official result...
        $this->assertSame(FixtureStatus::Live, $fixture->fresh()->status);
        $this->assertNull($fixture->fresh()->home_goals);

        // ...and nothing is proposed — the complete final comes from the score feed (scores:fetch).
        $this->assertSame(0, ScoreProposal::count());
    }

    public function test_it_rejects_closing_a_fixture_that_is_not_live(): void
    {
        $fixture = Fixture::factory()->create(['status' => FixtureStatus::Scheduled]);

        try {
            app(CloseLiveScoreboard::class)->close($fixture);
            $this->fail('Expected an HttpException when the fixture is not live.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }
}
