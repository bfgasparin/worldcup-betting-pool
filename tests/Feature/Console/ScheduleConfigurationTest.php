<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Guards the scale-to-zero scheduling window defined in bootstrap/app.php.
 *
 * The window is hardcoded to World Cup 2026 and lives in the cron expression (not ->between())
 * so Laravel Cloud can hibernate the app outside game hours. If that window changes, these
 * assertions should change with it deliberately. See bootstrap/app.php for the rationale.
 */
class ScheduleConfigurationTest extends TestCase
{
    private const WINDOW = '*/20 15-23,0-8 * 6,7 *';

    protected function setUp(): void
    {
        parent::setUp();

        // withSchedule() in bootstrap/app.php registers its events via Artisan::starting, which
        // only fires once the console application boots — a plain feature test never does that.
        // Running an Artisan command boots it and populates the Schedule singleton with our events.
        Artisan::call('schedule:list');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function scheduledCommands(): array
    {
        return [
            'fixtures:tick' => ['fixtures:tick'],
            'scores:fetch' => ['scores:fetch'],
        ];
    }

    #[DataProvider('scheduledCommands')]
    public function test_command_uses_the_world_cup_window_cron_expression(string $command): void
    {
        $this->assertSame(self::WINDOW, $this->event($command)->expression);
    }

    #[DataProvider('scheduledCommands')]
    public function test_command_is_due_during_game_hours_in_tournament_months(string $command): void
    {
        // Evening fixtures (kickoffs run 16:00–04:59 UTC) and the post-midnight wrap.
        Carbon::setTestNow('2026-06-15 19:00:00');
        $this->assertTrue($this->event($command)->isDue($this->app));

        Carbon::setTestNow('2026-06-15 03:00:00');
        $this->assertTrue($this->event($command)->isDue($this->app));
    }

    #[DataProvider('scheduledCommands')]
    public function test_command_is_not_due_during_the_daily_quiet_window(string $command): void
    {
        // 05:00–14:59 UTC has zero kickoffs, so the app should be free to hibernate.
        Carbon::setTestNow('2026-06-15 12:00:00');
        $this->assertFalse($this->event($command)->isDue($this->app));
    }

    #[DataProvider('scheduledCommands')]
    public function test_command_is_not_due_outside_the_tournament_months(string $command): void
    {
        Carbon::setTestNow('2026-09-15 19:00:00');
        $this->assertFalse($this->event($command)->isDue($this->app));
    }

    #[DataProvider('scheduledCommands')]
    public function test_command_window_boundaries(string $command): void
    {
        // Last run of the window is 08:40 UTC (covers the latest match end at ~07:29 + margin).
        Carbon::setTestNow('2026-06-15 08:40:00');
        $this->assertTrue($this->event($command)->isDue($this->app));

        Carbon::setTestNow('2026-06-15 09:00:00');
        $this->assertFalse($this->event($command)->isDue($this->app));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function event(string $command): Event
    {
        $events = array_values(array_filter(
            $this->app->make(Schedule::class)->events(),
            fn (Event $event): bool => str_contains((string) $event->command, $command),
        ));

        $this->assertCount(1, $events, "Expected exactly one scheduled '{$command}' command.");

        return $events[0];
    }
}
