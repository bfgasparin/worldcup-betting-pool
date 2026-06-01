<?php

namespace App\Support;

use App\Providers\AppServiceProvider;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

/**
 * A persisted offset that lets local development fast-forward the application's notion of "now".
 *
 * The offset (in seconds, relative to real time) lives in the cache so every process — web
 * requests, the scheduler and artisan commands — shares one simulated clock. It is applied at
 * boot in {@see AppServiceProvider} (local only); storing an offset rather than a
 * frozen instant means the simulated clock keeps advancing with real time between processes.
 */
final class DevClock
{
    public const CACHE_KEY = 'dev-clock.offset_seconds';

    /**
     * The configured offset from real time, in seconds (0 when the clock is real).
     */
    public static function offsetSeconds(): int
    {
        return (int) Cache::get(self::CACHE_KEY, 0);
    }

    public static function setOffsetSeconds(int $seconds): void
    {
        Cache::forever(self::CACHE_KEY, $seconds);
    }

    /**
     * Whether the simulated clock currently differs from real time.
     */
    public static function isActive(): bool
    {
        return self::offsetSeconds() !== 0;
    }

    /**
     * Jump the simulated clock to a target moment, keeping it advancing from there.
     */
    public static function travelTo(CarbonInterface $target): void
    {
        self::setOffsetSeconds($target->getTimestamp() - self::realNow()->getTimestamp());
    }

    /**
     * Move the simulated clock forward (or back, with a negative value) by some seconds.
     */
    public static function advance(int $seconds): void
    {
        self::setOffsetSeconds(self::offsetSeconds() + $seconds);
    }

    public static function reset(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Real wall-clock time, recovered even when the offset is already applied to "now". Used to
     * derive a fresh offset in {@see travelTo()}, where "now()" still reflects the current offset.
     */
    public static function realNow(): CarbonInterface
    {
        return Date::now()->subSeconds(self::offsetSeconds());
    }
}
