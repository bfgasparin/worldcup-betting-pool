<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Services\Live\LiveFeed;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('live:advance {tournament? : Tournament slug (defaults to every tournament)}')]
#[Description('Advance the live feed: take due fixtures live, tick their live scores, and close ended boards.')]
class AdvanceLiveFeed extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(LiveFeed $feed): int
    {
        $tournaments = $this->resolveTournaments();

        if ($tournaments->isEmpty()) {
            $this->components->warn('No matching tournament found.');

            return self::FAILURE;
        }

        foreach ($tournaments as $tournament) {
            $feed->advance($tournament);
            $this->components->info("Advanced the live feed for {$tournament->name}.");
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Tournament>
     */
    private function resolveTournaments(): Collection
    {
        $slug = $this->argument('tournament');

        return $slug === null
            ? Tournament::all()
            : Tournament::where('slug', $slug)->get();
    }
}
