<?php

namespace App\Console\Commands;

use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Tournament;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fixtures:tick {tournament? : Tournament slug (defaults to every tournament)}')]
#[Description('Advance fixtures whose kickoff has passed from scheduled to live.')]
class TickFixtures extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = Fixture::query()
            ->where('status', FixtureStatus::Scheduled)
            ->whereNotNull('kicks_off_at')
            ->where('kicks_off_at', '<=', now());

        if (($slug = $this->argument('tournament')) !== null) {
            $tournament = Tournament::where('slug', $slug)->first();

            if ($tournament === null) {
                $this->components->warn('No matching tournament found.');

                return self::FAILURE;
            }

            $query->where('tournament_id', $tournament->id);
        }

        $started = $query->update(['status' => FixtureStatus::Live]);

        $this->components->info($started > 0
            ? "Marked {$started} fixture(s) live."
            : 'No fixtures to start.');

        return self::SUCCESS;
    }
}
