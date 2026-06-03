<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
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
        // SCALE-TO-ZERO WINDOW — hardcoded to World Cup 2026 (11 Jun – 19 Jul 2026), the only
        // live game for now. The window lives in the CRON EXPRESSION (not ->between()) on
        // purpose: Laravel Cloud reads `schedule:list` (i.e. the cron expression) to decide when
        // to wake the app, so a cron-encoded window lets the app/DB hibernate outside game hours.
        // ->between() is only a post-boot filter — it would leave the cron at "* * * * *" and the
        // app would still wake every minute, defeating scale-to-zero and costing us money.
        //
        // `*/20 15-23,0-8 * 6,7 *` = every 20 min, 15:00–08:40 UTC, June & July only.
        //   • WC2026 kickoffs span 16:00–04:59 UTC (North-American kickoffs wrap past UTC midnight).
        //   • +150 min (config('scoring.match_duration_minutes')) → last match ends ~07:29 UTC.
        //   • 15:00 start / 08:40 end is the safety margin. Hibernates daily 09:00–14:59 UTC and
        //     entirely outside June/July. App timezone is UTC, so the cron is evaluated in UTC.
        //
        // TODO: revisit when the platform hosts more games/tournaments. Other tournaments with a
        // different schedule (or running outside June/July) will NOT have fixtures ticked or
        // scores fetched until this window is widened — or, better, derived from the fixtures
        // table. Covered by tests/Feature/Console/ScheduleConfigurationTest.php.
        $window = '*/20 15-23,0-8 * 6,7 *';

        // Move fixtures from scheduled to live as their kickoff passes, so the "match has
        // ended" gate (live + past full time) can open for score entry.
        $schedule->command('fixtures:tick')->cron($window)->withoutOverlapping();

        // Pull fresh match scores into a pending review batch. Real results only exist once a
        // match has ended, so this is a cheap no-op outside live windows.
        $schedule->command('scores:fetch')->cron($window)->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state', 'timezone']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
