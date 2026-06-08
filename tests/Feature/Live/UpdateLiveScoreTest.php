<?php

namespace Tests\Feature\Live;

use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\FixtureLiveState;
use App\Services\Live\UpdateLiveScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class UpdateLiveScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_the_live_scoreline(): void
    {
        $fixture = Fixture::factory()->create(['status' => FixtureStatus::Live]);
        FixtureLiveState::factory()->for($fixture)->withScore(0, 0)->create();

        $state = app(UpdateLiveScore::class)->update($fixture, 2, 1);

        $this->assertSame(2, $state->home_goals);
        $this->assertSame(1, $state->away_goals);
        $this->assertSame(2, $fixture->fresh()->liveState->home_goals);

        // Never writes the official result.
        $this->assertNull($fixture->fresh()->home_goals);
        $this->assertNull($fixture->fresh()->away_goals);
    }

    public function test_a_score_change_advances_the_version_timestamp(): void
    {
        $fixture = Fixture::factory()->create(['status' => FixtureStatus::Live]);
        $state = FixtureLiveState::factory()->for($fixture)->withScore(0, 0)->create();
        $original = $state->fresh()->updated_at;

        $this->travel(5)->minutes();
        app(UpdateLiveScore::class)->update($fixture, 1, 0);

        $this->assertTrue($fixture->fresh()->liveState->updated_at->greaterThan($original));
    }

    public function test_it_rejects_updates_when_the_fixture_is_not_live(): void
    {
        $fixture = Fixture::factory()->create(['status' => FixtureStatus::Scheduled]);

        try {
            app(UpdateLiveScore::class)->update($fixture, 1, 0);
            $this->fail('Expected an HttpException when the fixture is not live.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_it_rejects_updates_once_the_match_has_ended(): void
    {
        $fixture = Fixture::factory()->create(['status' => FixtureStatus::Live]);
        FixtureLiveState::factory()->for($fixture)->ended()->create();

        $this->expectException(HttpException::class);

        app(UpdateLiveScore::class)->update($fixture, 1, 0);
    }
}
