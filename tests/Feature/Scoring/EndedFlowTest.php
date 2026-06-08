<?php

namespace Tests\Feature\Scoring;

use App\Enums\BatchStatus;
use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Pool;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Live\GoLive;
use Carbon\CarbonImmutable;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOfficialResults;
use Tests\TestCase;

/**
 * The whole mid-phase flow, driven deterministically with time travel rather than the dev clock:
 * predictions made, the clock reaches mid group stage, the cron proposes scores for the matches
 * that have ended, and an admin approves them into official results that score the board.
 */
class EndedFlowTest extends TestCase
{
    use InteractsWithOfficialResults;
    use RefreshDatabase;

    private Tournament $tournament;

    private Pool $pool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
        $this->pool = $this->tournament->pools()->where('slug', 'world-cup-2026-ffa')->firstOrFail();

        // Let the scheduled fetch propose plausible scores, as it would on local.
        config()->set('scoring.simulated_provider', true);
    }

    public function test_ended_matches_flow_from_fetch_to_approved_results(): void
    {
        // Players predict; no results yet, every fixture still scheduled.
        $this->artisan('tournament:simulate', ['--players' => 4, '--predict-only' => true])->assertSuccessful();

        // The clock reaches the middle of the group stage; an admin (or automated process) marks the
        // kicked-off matches live — going live is admin-driven now, so this uses GoLive::force.
        $this->travelTo(CarbonImmutable::parse('2026-06-13 23:59:00', 'UTC'));

        $goLive = app(GoLive::class);
        $this->tournament->fixtures()
            ->where('status', FixtureStatus::Scheduled)
            ->whereNotNull('kicks_off_at')
            ->where('kicks_off_at', '<=', now())
            ->get()
            ->each(fn (Fixture $fixture) => $goLive->force($fixture));

        $endedCount = $this->tournament->fixtures()->ended()->whereNull('home_goals')->count();
        $this->assertGreaterThan(0, $endedCount);

        // The cron proposes scores — only for the matches that have ended.
        $this->artisan('scores:fetch', ['tournament' => $this->tournament->slug])->assertSuccessful();

        $batch = $this->tournament->scoreBatches()->where('status', BatchStatus::Open)->firstOrFail();
        $this->assertSame($endedCount, $batch->proposals()->count());

        // An admin approves the batch, publishing official results and scoring the board.
        $admin = User::factory()->create();
        config()->set('admin.emails', [$admin->email]);

        $this->actingAs($admin)
            ->post(route('pools.scores.approve', $this->pool))
            ->assertRedirect();

        $this->assertSame(
            $endedCount,
            $this->tournament->fixtures()->where('status', FixtureStatus::Finished)->count(),
        );
        $this->assertGreaterThan(0, $this->pool->entries()->whereNotNull('total_points')->count());
    }
}
