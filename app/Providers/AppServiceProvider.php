<?php

namespace App\Providers;

use App\Contracts\ScoreProvider;
use App\Models\User;
use App\Services\Scoring\Providers\ManualScoreProvider;
use App\Services\Scoring\Providers\SimulatedScoreProvider;
use App\Support\DevClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The default score source enters nothing automatically; swap this binding for a real
        // results-API provider once one is available. On local, an opt-in simulated provider
        // proposes plausible scores for ended fixtures so the full flow can be exercised.
        $this->app->bind(ScoreProvider::class, fn (): ScoreProvider => config('scoring.simulated_provider')
            ? new SimulatedScoreProvider
            : new ManualScoreProvider);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->applyDevClock();

        Gate::define('manage-tournament', fn (User $user): bool => $user->isAdmin());
    }

    /**
     * On local, shift the application clock by the persisted dev-clock offset so web requests,
     * the scheduler and artisan commands share one simulated "now" (see {@see DevClock}). Never
     * applied outside local, so production and the test suite run on real time.
     */
    protected function applyDevClock(): void
    {
        if (! $this->app->environment('local')) {
            return;
        }

        try {
            $offset = DevClock::offsetSeconds();
        } catch (\Throwable) {
            // The cache store may not be ready yet (e.g. before migrations on a fresh install).
            // The dev clock is only a convenience, so it must never break the boot.
            return;
        }

        if ($offset !== 0) {
            Date::setTestNow(CarbonImmutable::now()->addSeconds($offset));
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
