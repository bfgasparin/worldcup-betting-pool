<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Guards the scheduler defined in bootstrap/app.php.
 *
 * Going live and entering results are admin-driven via the Live Center, and the only score source
 * today is the local SimulatedScoreProvider, so PRODUCTION schedules nothing — that keeps Laravel
 * Cloud hibernated (scale-to-zero). The score sync is therefore scheduled only outside production,
 * where it runs frequently so the simulated provider can advance the flow against the dev clock.
 *
 * The production guard (`! app()->isProduction()`) lives in bootstrap/app.php and can't be flipped
 * after the console boots within one test process, so it isn't asserted here directly — these
 * assertions cover the non-production (testing) shape the guard produces.
 */
class ScheduleConfigurationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // withSchedule() in bootstrap/app.php registers its events via Artisan::starting, which
        // only fires once the console application boots — a plain feature test never does that.
        // Running an Artisan command boots it and populates the Schedule singleton with our events.
        Artisan::call('schedule:list');
    }

    public function test_fixtures_tick_is_no_longer_scheduled(): void
    {
        // Going live is admin-driven and status syncs at every mutation, so the reconciler is gone.
        $this->assertSame([], $this->events('fixtures:tick'));
    }

    public function test_scores_fetch_runs_frequently_outside_production(): void
    {
        $events = $this->events('scores:fetch');

        $this->assertCount(1, $events, 'Expected scores:fetch to be scheduled outside production.');
        $this->assertSame('* * * * *', $events[0]->expression);
    }

    public function test_live_advance_runs_frequently_outside_production(): void
    {
        $events = $this->events('live:advance');

        $this->assertCount(1, $events, 'Expected live:advance to be scheduled outside production.');
        $this->assertSame('* * * * *', $events[0]->expression);
    }

    /**
     * @return list<Event>
     */
    private function events(string $command): array
    {
        return array_values(array_filter(
            $this->app->make(Schedule::class)->events(),
            fn (Event $event): bool => str_contains((string) $event->command, $command),
        ));
    }
}
