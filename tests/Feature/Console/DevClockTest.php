<?php

namespace Tests\Feature\Console;

use App\Support\DevClock;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class DevClockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DevClock::reset();
    }

    public function test_the_offset_round_trips(): void
    {
        $this->assertFalse(DevClock::isActive());

        DevClock::setOffsetSeconds(3600);

        $this->assertSame(3600, DevClock::offsetSeconds());
        $this->assertTrue(DevClock::isActive());

        DevClock::reset();

        $this->assertSame(0, DevClock::offsetSeconds());
        $this->assertFalse(DevClock::isActive());
    }

    public function test_advance_adds_to_the_offset(): void
    {
        DevClock::setOffsetSeconds(100);
        DevClock::advance(50);

        $this->assertSame(150, DevClock::offsetSeconds());
    }

    public function test_travel_to_stores_the_offset_from_real_now(): void
    {
        $now = CarbonImmutable::parse('2026-06-11 12:00:00', 'UTC');
        $this->travelTo($now);

        $target = CarbonImmutable::parse('2026-06-11 15:00:00', 'UTC');
        DevClock::travelTo($target);

        $this->assertSame(3 * 3600, DevClock::offsetSeconds());
    }

    public function test_the_command_refuses_to_run_outside_local(): void
    {
        // The test suite runs in the "testing" environment.
        $this->artisan('dev:clock', ['--advance' => '2 hours'])->assertFailed();

        $this->assertSame(0, DevClock::offsetSeconds());
    }

    public function test_the_command_advances_the_clock_in_local(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');

        $this->artisan('dev:clock', ['--advance' => '2 hours'])->assertSuccessful();

        $this->assertSame(2 * 3600, DevClock::offsetSeconds());
    }
}
