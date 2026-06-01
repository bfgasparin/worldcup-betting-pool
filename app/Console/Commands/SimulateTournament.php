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
use App\Support\DeterministicScores;
use App\Support\DevClock;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('tournament:simulate
    {slug=world-cup-2026 : The tournament slug}
    {--players=8 : How many demo players to fabricate}
    {--me=test@example.com : Also give this user an entry so you appear on the board}
    {--through=final : Play official results through this phase (group|round_of_32|round_of_16|quarter_finals|semi_finals|third_place|final)}
    {--until= : Play official results for matches ended on or before this datetime (UTC), e.g. "2026-06-18 22:00" — like --through but date-granular, so you can land mid-phase. Also advances the dev clock (local)}
    {--seed= : Optional seed string to generate a different but reproducible world}
    {--predict-only : Set up players + predictions only; leave fixtures scheduled with no official results}
    {--reset : Clear official results + scoring for the tournament before simulating}')]
#[Description('Locally simulate a closed, fully-scored tournament: demo players + predictions, official results, computed board.')]
class SimulateTournament extends Command
{
    private DeterministicScores $scores;

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

        $until = ($untilOption = $this->option('until')) !== null
            ? CarbonImmutable::parse($untilOption, 'UTC')
            : null;

        $this->scores = new DeterministicScores((string) ($this->option('seed') ?? ''));

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

        $predictOnly = (bool) $this->option('predict-only');

        $this->closePredictions($tournament, $through, $predictOnly || $until !== null);

        if (! $predictOnly) {
            $this->playResults($tournament, $through, $until, $projector, $engine, $snapshotter);
        }

        if ($until !== null) {
            $this->advanceTo($tournament, $until);
        }

        $this->summarise($tournament, $through, $predictOnly, $until);

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

    private function closePredictions(Tournament $tournament, PhaseKey $through, bool $predictOnly = false): void
    {
        $tournament->update([
            'predictions_lock_at' => now()->subDay(),
            'status' => (! $predictOnly && $through === PhaseKey::Final)
                ? TournamentStatus::Completed
                : TournamentStatus::InProgress,
        ]);
    }

    /**
     * Play official results, re-projecting and re-scoring after each phase so the leaderboard
     * movement reflects each round. Bounded either by phase (--through) or, when $until is given,
     * by match end time — filling only matches finished by that datetime, which can land mid-phase.
     */
    private function playResults(Tournament $tournament, PhaseKey $through, ?CarbonImmutable $until, OfficialBracketProjector $projector, ScoreEngine $engine, RankSnapshotter $snapshotter): void
    {
        $throughOrder = (int) $tournament->phases()->where('key', $through->value)->value('sort_order');

        foreach ($tournament->phases()->orderBy('sort_order')->get() as $phase) {
            // A date cutoff spans every phase; a phase cutoff stops at the requested phase.
            if ($until === null && $phase->sort_order > $throughOrder) {
                break;
            }

            $phase->key === PhaseKey::Group
                ? $this->fillGroupResults($tournament, $until)
                : $this->fillKnockoutResults($phase, $until);

            $projector->project($tournament);
            $engine->recompute($tournament);
            $snapshotter->snapshot($tournament);

            $this->components->task("Played {$phase->name}");
        }
    }

    private function fillGroupResults(Tournament $tournament, ?CarbonImmutable $until = null): void
    {
        foreach ($tournament->groups()->with(['teams', 'fixtures'])->get() as $group) {
            $positions = $group->teams->mapWithKeys(
                fn ($team): array => [$team->id => (int) $team->pivot->position],
            );

            foreach ($group->fixtures as $fixture) {
                if (! $this->endedBy($fixture, $until)) {
                    continue;
                }

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

    private function fillKnockoutResults(Phase $phase, ?CarbonImmutable $until = null): void
    {
        $fixtures = $phase->fixtures()
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->get();

        foreach ($fixtures as $fixture) {
            if (! $this->endedBy($fixture, $until)) {
                continue;
            }

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

    /**
     * Whether a fixture's match has finished by the given cutoff (null cutoff = always).
     */
    private function endedBy(Fixture $fixture, ?CarbonImmutable $until): bool
    {
        if ($until === null) {
            return true;
        }

        return $fixture->kicks_off_at !== null
            && $until->gte($fixture->kicks_off_at->addMinutes((int) config('scoring.match_duration_minutes')));
    }

    /**
     * Move the simulated world to the cutoff date: bring matches that have kicked off but aren't
     * finished to live (so the "ended" gate opens as the clock reaches them), and set the local
     * dev clock so web requests and the scheduler share the same simulated "now".
     */
    private function advanceTo(Tournament $tournament, CarbonImmutable $until): void
    {
        $tournament->fixtures()
            ->where('status', FixtureStatus::Scheduled)
            ->whereNotNull('kicks_off_at')
            ->where('kicks_off_at', '<=', $until)
            ->update(['status' => FixtureStatus::Live]);

        if ($this->getLaravel()->environment('local')) {
            DevClock::travelTo($until);
        }
    }

    private function summarise(Tournament $tournament, PhaseKey $through, bool $predictOnly = false, ?CarbonImmutable $until = null): void
    {
        if ($predictOnly) {
            $players = $tournament->entries()->count();
            $this->newLine();
            $this->components->info("Set up {$players} players with predictions for {$tournament->name} — no results filled.");

            if ($until !== null) {
                $this->components->info("Clock moved to {$until->toDayDateTimeString()} UTC; matches finished by then are awaiting scores. Run `php artisan scores:fetch` (set SCORING_SIMULATED_PROVIDER=true) or use the review screen to enter them.");
            } else {
                $this->components->info('Advance time and let results land: `php artisan dev:clock --travel="2026-06-11 12:00"`, then `fixtures:tick` + `scores:fetch` as the clock moves (set SCORING_SIMULATED_PROVIDER=true to have the fetch propose).');
            }

            $this->components->info("Log in at /login as {$this->option('me')} — the 6-digit code is written to the log (`php artisan pail`).");

            return;
        }

        $top = $tournament->entries()
            ->with('user')
            ->orderByRaw('total_points IS NULL, total_points DESC, id')
            ->limit(5)
            ->get();

        $this->newLine();
        $this->components->info($until !== null
            ? "Simulated {$tournament->name} up to {$until->toDayDateTimeString()} UTC."
            : "Simulated {$tournament->name} through the {$through->value} phase.");

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

    private function biasedGoals(float $noise, int $position): int
    {
        return $this->scores->biasedGoals($noise, $position);
    }

    private function noise(int|string ...$parts): float
    {
        return $this->scores->noise(...$parts);
    }
}
