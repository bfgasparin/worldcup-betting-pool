<?php

namespace App\Console\Commands;

use App\Support\DevClock as Clock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('dev:clock
    {--travel= : Jump the simulated clock to a moment, e.g. "2026-06-11 23:00" (parsed as UTC)}
    {--advance= : Move the simulated clock forward by an interval, e.g. "3 hours"}
    {--reset : Return to real time}
    {--show : Show the current simulated clock (default when no other option is given)}')]
#[Description('Fast-forward the application clock on local for end-to-end testing of time-based flows.')]
class DevClock extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->getLaravel()->environment('local')) {
            $this->components->error('dev:clock only runs in the local environment.');

            return self::FAILURE;
        }

        if ($this->option('reset')) {
            Clock::reset();
            $this->components->info('Clock reset to real time.');

            return self::SUCCESS;
        }

        if (($travel = $this->option('travel')) !== null) {
            Clock::travelTo(CarbonImmutable::parse($travel, 'UTC'));
        } elseif (($advance = $this->option('advance')) !== null) {
            $interval = CarbonInterval::make($advance);

            if ($interval === null) {
                $this->components->error("Could not understand the interval [{$advance}]. Try e.g. \"3 hours\".");

                return self::FAILURE;
            }

            Clock::advance((int) $interval->totalSeconds);
        }

        $this->report();

        return self::SUCCESS;
    }

    private function report(): void
    {
        $offset = Clock::offsetSeconds();

        // Read the true wall clock via time(), independent of any setTestNow already applied this
        // process (this command may have just changed the offset, leaving "now()" stale).
        $realNow = CarbonImmutable::createFromTimestamp(time(), 'UTC');

        $this->table(['', ''], [
            ['Real now', $realNow->toDayDateTimeString().' UTC'],
            ['Simulated now', $realNow->addSeconds($offset)->toDayDateTimeString().' UTC'],
            ['Offset', $offset === 0 ? 'none (real time)' : CarbonInterval::seconds($offset)->cascade()->forHumans()],
        ]);

        if ($offset !== 0) {
            $this->components->warn('Drive the schedule with `php artisan schedule:run` (one-shot) or run commands directly; `schedule:work` boots once and freezes the simulated clock.');
        }
    }
}
