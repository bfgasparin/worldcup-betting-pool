<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Pin the suite's "now" to a fixed instant just before the seeded World Cup 2026 tournament
     * (first group kickoff is 2026-06-11 19:00 UTC, so the derived group prediction/join window
     * locks at 18:00 UTC that day). The seeder uses the real event's fixed dates, so without this
     * every window-dependent test fails once the real wall clock passes them. Tests that need a
     * different moment freeze their own time after calling parent::setUp(); explicit lock overrides
     * (predictions_lock_at set relative to now()) keep working unchanged.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-01 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
