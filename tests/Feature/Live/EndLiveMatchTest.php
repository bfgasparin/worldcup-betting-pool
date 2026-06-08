<?php

namespace Tests\Feature\Live;

use App\Enums\BatchStatus;
use App\Enums\FixtureStatus;
use App\Enums\LiveStatus;
use App\Enums\ProposalStatus;
use App\Models\Fixture;
use App\Models\FixtureLiveState;
use App\Models\ScoreBatch;
use App\Models\ScoreProposal;
use App\Models\Tournament;
use App\Services\Live\EndLiveMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EndLiveMatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_ending_creates_a_pending_proposal_from_the_live_score(): void
    {
        $tournament = Tournament::factory()->create();
        $fixture = Fixture::factory()->for($tournament)->create(['status' => FixtureStatus::Live]);
        FixtureLiveState::factory()->for($fixture)->withScore(2, 1)->create();

        $proposal = app(EndLiveMatch::class)->end($fixture);

        $this->assertSame(ProposalStatus::Pending, $proposal->status);
        $this->assertSame(2, $proposal->home_goals);
        $this->assertSame(1, $proposal->away_goals);
        $this->assertSame($fixture->home_team_id, $proposal->winner_team_id);
        $this->assertSame(BatchStatus::Open, $proposal->batch->status);
        $this->assertTrue($proposal->batch->tournament->is($tournament));

        // The live state closes...
        $this->assertSame(LiveStatus::Ended, $fixture->fresh()->liveState->status);
        $this->assertNotNull($fixture->fresh()->liveState->ended_at);

        // ...but the official fixture is untouched (stays Live, no official goals).
        $this->assertSame(FixtureStatus::Live, $fixture->fresh()->status);
        $this->assertNull($fixture->fresh()->home_goals);
        $this->assertNull($fixture->fresh()->away_goals);
    }

    public function test_a_draw_leaves_the_winner_unset(): void
    {
        $fixture = Fixture::factory()->create(['status' => FixtureStatus::Live]);
        FixtureLiveState::factory()->for($fixture)->withScore(1, 1)->create();

        $proposal = app(EndLiveMatch::class)->end($fixture);

        $this->assertNull($proposal->winner_team_id);
    }

    public function test_ending_reuses_the_tournaments_open_batch(): void
    {
        $tournament = Tournament::factory()->create();
        $existing = ScoreBatch::openFor($tournament);

        $fixture = Fixture::factory()->for($tournament)->create(['status' => FixtureStatus::Live]);
        FixtureLiveState::factory()->for($fixture)->withScore(0, 0)->create();

        $proposal = app(EndLiveMatch::class)->end($fixture);

        $this->assertTrue($proposal->batch->is($existing));
        $this->assertSame(1, $tournament->scoreBatches()->count());
    }

    public function test_it_rejects_ending_a_fixture_that_is_not_live(): void
    {
        $fixture = Fixture::factory()->create(['status' => FixtureStatus::Scheduled]);

        try {
            app(EndLiveMatch::class)->end($fixture);
            $this->fail('Expected an HttpException when the fixture is not live.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame(0, ScoreProposal::count());
    }
}
