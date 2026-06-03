<?php

namespace App\Console\Commands;

use App\Enums\FixtureStatus;
use App\Enums\TournamentStatus;
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
        $tournament = null;

        if (($slug = $this->argument('tournament')) !== null) {
            $tournament = Tournament::where('slug', $slug)->first();

            if ($tournament === null) {
                $this->components->warn('No matching tournament found.');

                return self::FAILURE;
            }
        }

        $started = Fixture::query()
            ->where('status', FixtureStatus::Scheduled)
            ->whereNotNull('kicks_off_at')
            ->where('kicks_off_at', '<=', now())
            ->when($tournament, fn ($query) => $query->where('tournament_id', $tournament->id))
            ->update(['status' => FixtureStatus::Live]);

        if ($started > 0) {
            $this->syncTournamentStatuses($tournament);
        }

        $this->components->info($started > 0
            ? "Marked {$started} fixture(s) live."
            : 'No fixtures to start.');

        return self::SUCCESS;
    }

    /**
     * Bring the lifecycle status of the affected tournament(s) in line with the freshly-started
     * fixtures (e.g. Upcoming → InProgress). Scoped to the targeted tournament when a slug was
     * given, otherwise every tournament that has not already completed.
     */
    private function syncTournamentStatuses(?Tournament $tournament): void
    {
        Tournament::query()
            ->when($tournament, fn ($query) => $query->whereKey($tournament->id))
            ->where('status', '!=', TournamentStatus::Completed)
            ->get()
            ->each
            ->syncStatus();
    }
}
