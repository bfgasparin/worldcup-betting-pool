<?php

namespace App\Console\Commands;

use App\Contracts\ScoreProvider;
use App\Enums\BatchStatus;
use App\Models\Fixture;
use App\Models\ScoreBatch;
use App\Models\ScoreProposal;
use App\Models\Tournament;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('scores:fetch {tournament? : Tournament slug (defaults to every tournament)}')]
#[Description('Fetch official match scores from the configured provider into a pending review batch.')]
class FetchScores extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ScoreProvider $provider): int
    {
        $tournaments = $this->resolveTournaments();

        if ($tournaments->isEmpty()) {
            $this->components->warn('No matching tournament found.');

            return self::FAILURE;
        }

        foreach ($tournaments as $tournament) {
            $this->fetchFor($tournament, $provider);
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

    private function fetchFor(Tournament $tournament, ScoreProvider $provider): void
    {
        // Only matches still missing an official score are candidates for a proposal.
        $candidates = $tournament->fixtures()
            ->whereNull('home_goals')
            ->get()
            ->keyBy('match_number');

        $created = 0;
        $batch = null;

        foreach ($provider->fetch($tournament) as $proposed) {
            $fixture = $candidates->get($proposed->matchNumber);

            if (! $fixture instanceof Fixture) {
                continue;
            }

            $batch ??= $this->openBatch($tournament);

            ScoreProposal::updateOrCreate(
                ['score_batch_id' => $batch->id, 'fixture_id' => $fixture->id],
                [
                    'home_goals' => $proposed->homeGoals,
                    'away_goals' => $proposed->awayGoals,
                    'winner_team_id' => $proposed->winnerTeamId,
                    'home_penalties' => $proposed->homePenalties,
                    'away_penalties' => $proposed->awayPenalties,
                ],
            );

            $created++;
        }

        $this->components->info($created > 0
            ? "Fetched {$created} score(s) for {$tournament->name} into a pending review batch."
            : "No new scores for {$tournament->name}.");
    }

    private function openBatch(Tournament $tournament): ScoreBatch
    {
        return ScoreBatch::firstOrCreate(
            ['tournament_id' => $tournament->id, 'status' => BatchStatus::Open],
            ['source' => 'fetch', 'fetched_at' => now()],
        );
    }
}
