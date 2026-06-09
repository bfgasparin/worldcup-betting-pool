<?php

use App\Http\Middleware\EnsureOnboarded;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // PRODUCTION SCHEDULES NOTHING — on purpose. Going live and entering results are
        // admin-driven via the Live Center, and the only score source today is the local
        // SimulatedScoreProvider (ManualScoreProvider is a no-op in production). An empty
        // production schedule means Laravel Cloud keeps the app/DB hibernated (scale-to-zero):
        // it reads `schedule:list` to decide when to wake, so registering nothing avoids waking
        // the app for work it wouldn't do, and the cost that comes with it.
        //
        // REVISIT when a real results provider replaces the simulated one — that's the point at
        // which a production schedule (e.g. a fixtures-derived window) becomes worth its wake-ups.
        // Covered by tests/Feature/Console/ScheduleConfigurationTest.php.
        if (! app()->isProduction()) {
            // Non-production only: drive the simulated live feed off the dev clock. It runs
            // frequently so a local simulation advances match by match as the clock moves —
            // live:advance takes due fixtures live, ticks their live scores, and closes ended
            // boards; scores:fetch proposes the complete finals of ended fixtures for admin review.
            $schedule->command('live:advance')->everyMinute()->withoutOverlapping();
            $schedule->command('scores:fetch')->everyMinute()->withoutOverlapping();
        }
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state', 'timezone']);

        $middleware->web(append: [
            HandleAppearance::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            EnsureOnboarded::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
