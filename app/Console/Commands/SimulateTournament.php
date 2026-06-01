<?php

namespace App\Console\Commands;

use App\Enums\EntryStatus;
use App\Enums\FixtureStatus;
use App\Enums\PhaseKey;
use App\Enums\TournamentStatus;
use App\Models\Entry;
use App\Models\Fixture;
use App\Models\GroupPrediction;
use App\Models\Phase;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Predictions\BracketResolver;
use App\Services\Predictions\OfficialBracketProjector;
use App\Services\Scoring\RankSnapshotter;
use App\Services\Scoring\ScoreEngine;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('tournament:simulate
    {slug=world-cup-2026 : The tournament slug}
    {--players=8 : How many demo players to fabricate}
    {--me=test@example.com : Also give this user an entry so you appear on the board}
    {--through=final : Play official results through this phase (group|round_of_32|round_of_16|quarter_finals|semi_finals|third_place|final)}
    {--seed= : Optional seed string to generate a different but reproducible world}
    {--reset : Clear official results + scoring for the tournament before simulating}')]
#[Description('Locally simulate a closed, fully-scored tournament: demo players + predictions, official results, computed board.')]
class SimulateTournament extends Command
{
    private string $seedPrefix = '';

    public function handle(
        BracketResolver $resolver,
        OfficialBracketProjector $projector,
        ScoreEngine $engine,
        RankSnapshotter $snapshotter,
    ): int {
        $tournament = Tournament::where('slug', $this->argument('slug'))->first();

        if ($tournament === null) {
            $this->components->error("Tournament [{$this->argument('slug')}] not found. Run `php artisan db:seed` first.");

            return self::FAILURE;
        }

        $through = PhaseKey::tryFrom((string) $this->option('through'));

        if ($through === null) {
            $this->components->error("Unknown phase [{$this->option('through')}]. Use one of: ".implode(', ', array_map(fn (PhaseKey $key): string => $key->value, PhaseKey::cases())));

            return self::FAILURE;
        }

        $this->seedPrefix = (string) ($this->option('seed') ?? '');

        if ($this->option('reset')) {
            $this->reset($tournament);
            $this->components->info('Cleared previous results and scoring.');
        }

        $entries = $this->ensurePlayers($tournament);
        $this->components->info("{$entries->count()} players ready; generating predictions…");

        foreach ($entries as [$entry, $seedIndex]) {
            // Respect predictions already made in the UI (e.g. your own); only fill empties.
            if ($entry->groupPredictions()->exists()) {
                continue;
            }

            $this->generateGroupPredictions($tournament, $entry, $seedIndex);
            $this->generateKnockoutPredictions($tournament, $entry, $seedIndex, $resolver);
        }

        $this->closePredictions($tournament, $through);
        $this->playResults($tournament, $through, $projector, $engine, $snapshotter);

        $this->summarise($tournament, $through);

        return self::SUCCESS;
    }

    /**
     * Reset the tournament to an unplayed state: clear official results, projected knockout
     * participants, computed totals/ranks, and any score batches.
     */
    private function reset(Tournament $tournament): void
    {
        $tournament->fixtures()->update([
            'home_goals' => null,
            'away_goals' => null,
            'winner_team_id' => null,
            'home_penalties' => null,
            'away_penalties' => null,
            'status' => FixtureStatus::Scheduled->value,
        ]);

        Fixture::whereIn('id', $tournament->knockoutFixtures()->pluck('id'))
            ->update(['home_team_id' => null, 'away_team_id' => null]);

        $tournament->entries()->update([
            'total_points' => null,
            'rank' => null,
            'previous_rank' => null,
        ]);

        $tournament->scoreBatches()->delete();
    }

    /**
     * Ensure demo players and the --me user each have a submitted entry.
     *
     * @return Collection<int, array{0: Entry, 1: int}> entry + its prediction seed index
     */
    private function ensurePlayers(Tournament $tournament): Collection
    {
        $entries = collect();

        for ($i = 1; $i <= (int) $this->option('players'); $i++) {
            $user = User::updateOrCreate(
                ['email' => "sim-player-{$i}@ffa.test"],
                ['name' => "Player {$i}", 'email_verified_at' => now()],
            );

            $entries->push([$this->ensureEntry($tournament, $user), $i]);
        }

        $meEmail = (string) $this->option('me');

        if ($meEmail !== '') {
            $me = User::firstWhere('email', $meEmail) ?? User::updateOrCreate(
                ['email' => $meEmail],
                ['name' => 'You', 'email_verified_at' => now()],
            );

            $entries->push([$this->ensureEntry($tournament, $me), 0]);
        }

        return $entries;
    }

    private function ensureEntry(Tournament $tournament, User $user): Entry
    {
        return $tournament->entries()->firstOrCreate(
            ['user_id' => $user->id],
            ['status' => EntryStatus::Submitted, 'submitted_at' => now()],
        );
    }

    private function generateGroupPredictions(Tournament $tournament, Entry $entry, int $seedIndex): void
    {
        foreach ($tournament->groups()->with('fixtures')->get() as $group) {
            foreach ($group->fixtures as $fixture) {
                GroupPrediction::updateOrCreate(
                    ['entry_id' => $entry->id, 'fixture_id' => $fixture->id],
                    [
                        'home_goals' => $this->goals($seedIndex, $fixture->match_number, 'ph'),
                        'away_goals' => $this->goals($seedIndex, $fixture->match_number, 'pa'),
                    ],
                );
            }
        }
    }

    /**
     * Fill the entry's knockout picks round by round, re-persisting so each round's advancing
     * picks resolve the next round's participants (the app-side analogue of advanceAllHome).
     */
    private function generateKnockoutPredictions(Tournament $tournament, Entry $entry, int $seedIndex, BracketResolver $resolver): void
    {
        $matchNumbers = $tournament->knockoutFixtures()->pluck('match_number', 'id');

        $resolver->persist($entry);

        for ($round = 0; $round < 6; $round++) {
            $entry->load('knockoutPredictions');
            $progressed = false;

            foreach ($entry->knockoutPredictions as $prediction) {
                if ($prediction->predicted_home_team_id === null
                    || $prediction->predicted_away_team_id === null
                    || $prediction->advancing_team_id !== null) {
                    continue;
                }

                $matchNumber = (int) $matchNumbers[$prediction->fixture_id];
                $pickHome = $this->noise($seedIndex, $matchNumber, 'kp') < 0.5;
                $winnerGoals = 1 + (int) floor($this->noise($seedIndex, $matchNumber, 'kwg') * 3);
                $loserGoals = (int) floor($this->noise($seedIndex, $matchNumber, 'klg') * $winnerGoals);

                $prediction->update([
                    'advancing_team_id' => $pickHome
                        ? $prediction->predicted_home_team_id
                        : $prediction->predicted_away_team_id,
                    'home_goals' => $pickHome ? $winnerGoals : $loserGoals,
                    'away_goals' => $pickHome ? $loserGoals : $winnerGoals,
                ]);

                $progressed = true;
            }

            $resolver->persist($entry);

            if (! $progressed) {
                break;
            }
        }
    }

    private function closePredictions(Tournament $tournament, PhaseKey $through): void
    {
        $tournament->update([
            'predictions_lock_at' => now()->subDay(),
            'status' => $through === PhaseKey::Final
                ? TournamentStatus::Completed
                : TournamentStatus::InProgress,
        ]);
    }

    /**
     * Play official results phase by phase up to the requested phase, re-projecting and
     * re-scoring after each so the leaderboard movement reflects each round.
     */
    private function playResults(Tournament $tournament, PhaseKey $through, OfficialBracketProjector $projector, ScoreEngine $engine, RankSnapshotter $snapshotter): void
    {
        $throughOrder = (int) $tournament->phases()->where('key', $through->value)->value('sort_order');

        foreach ($tournament->phases()->orderBy('sort_order')->get() as $phase) {
            if ($phase->sort_order > $throughOrder) {
                break;
            }

            $phase->key === PhaseKey::Group
                ? $this->fillGroupResults($tournament)
                : $this->fillKnockoutResults($phase);

            $projector->project($tournament);
            $engine->recompute($tournament);
            $snapshotter->snapshot($tournament);

            $this->components->task("Played {$phase->name}");
        }
    }

    private function fillGroupResults(Tournament $tournament): void
    {
        foreach ($tournament->groups()->with(['teams', 'fixtures'])->get() as $group) {
            $positions = $group->teams->mapWithKeys(
                fn ($team): array => [$team->id => (int) $team->pivot->position],
            );

            foreach ($group->fixtures as $fixture) {
                $home = $this->biasedGoals($this->noise($fixture->match_number, 'oh'), $positions[$fixture->home_team_id]);
                $away = $this->biasedGoals($this->noise($fixture->match_number, 'oa'), $positions[$fixture->away_team_id]);

                $fixture->update([
                    'home_goals' => $home,
                    'away_goals' => $away,
                    'winner_team_id' => $home === $away
                        ? null
                        : ($home > $away ? $fixture->home_team_id : $fixture->away_team_id),
                    'status' => FixtureStatus::Finished,
                ]);
            }
        }
    }

    private function fillKnockoutResults(Phase $phase): void
    {
        $fixtures = $phase->fixtures()
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->get();

        foreach ($fixtures as $fixture) {
            $matchNumber = $fixture->match_number;
            $homeAdvances = $this->noise($matchNumber, 'kw') < 0.5;
            $winnerId = $homeAdvances ? $fixture->home_team_id : $fixture->away_team_id;

            $attributes = [
                'winner_team_id' => $winnerId,
                'status' => FixtureStatus::Finished,
                'home_penalties' => null,
                'away_penalties' => null,
            ];

            if ($this->noise($matchNumber, 'pen') < 0.2) {
                // Level after regulation — decided on penalties.
                $level = (int) floor($this->noise($matchNumber, 'dg') * 3);
                $winnerPens = 4 + (int) floor($this->noise($matchNumber, 'wp') * 2);
                $loserPens = 2 + (int) floor($this->noise($matchNumber, 'lp') * 2);

                $attributes['home_goals'] = $level;
                $attributes['away_goals'] = $level;
                $attributes['home_penalties'] = $homeAdvances ? $winnerPens : $loserPens;
                $attributes['away_penalties'] = $homeAdvances ? $loserPens : $winnerPens;
            } else {
                $winnerGoals = 1 + (int) floor($this->noise($matchNumber, 'wg') * 3);
                $loserGoals = (int) floor($this->noise($matchNumber, 'lg') * $winnerGoals);

                $attributes['home_goals'] = $homeAdvances ? $winnerGoals : $loserGoals;
                $attributes['away_goals'] = $homeAdvances ? $loserGoals : $winnerGoals;
            }

            $fixture->update($attributes);
        }
    }

    private function summarise(Tournament $tournament, PhaseKey $through): void
    {
        $top = $tournament->entries()
            ->with('user')
            ->orderByRaw('total_points IS NULL, total_points DESC, id')
            ->limit(5)
            ->get();

        $this->newLine();
        $this->components->info("Simulated {$tournament->name} through the {$through->value} phase.");

        $this->table(
            ['Rank', 'Player', 'Points', 'Move'],
            $top->map(fn (Entry $entry): array => [
                $entry->rank ?? '—',
                $entry->user->name ?? 'Player',
                $entry->total_points ?? '—',
                $this->movementArrow($entry),
            ])->all(),
        );

        $this->components->info('View it: run `composer dev`, then open the tournament page (settled cards + points) and the pool table (rank arrows).');
        $this->components->info("Log in at /login as {$this->option('me')} — the 6-digit code is written to the log (`php artisan pail`).");
    }

    private function movementArrow(Entry $entry): string
    {
        if ($entry->rank === null || $entry->previous_rank === null) {
            return $entry->rank === null ? '—' : 'new';
        }

        return match (true) {
            $entry->rank < $entry->previous_rank => '▲',
            $entry->rank > $entry->previous_rank => '▼',
            default => '–',
        };
    }

    private function goals(int|string ...$seedParts): int
    {
        return (int) floor($this->noise(...$seedParts) * 4); // 0–3
    }

    /**
     * Nudge goals (0–3) by group seed: a better-seeded team (lower position) skews higher.
     */
    private function biasedGoals(float $noise, int $position): int
    {
        $strength = (4 - $position) * 0.4; // 0 (4th seed) … 1.2 (top seed)

        return (int) max(0, min(3, round($noise * 3 + $strength - 0.6)));
    }

    /**
     * A deterministic value in [0, 1) from the seed parts (so a given run is reproducible).
     */
    private function noise(int|string ...$parts): float
    {
        $hash = crc32($this->seedPrefix.':'.implode(':', $parts));

        return ($hash % 100000) / 100000;
    }
}
