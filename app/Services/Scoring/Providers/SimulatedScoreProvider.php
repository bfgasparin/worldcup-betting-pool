<?php

namespace App\Services\Scoring\Providers;

use App\Contracts\ScoreProvider;
use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Tournament;
use App\Services\Scoring\LiveScore;
use App\Services\Scoring\ProposedScore;
use App\Support\DeterministicScores;

/**
 * A local-only stand-in for a real results API: it proposes deterministic, plausible scores for
 * fixtures that have already ended but still lack an official result. It deliberately mirrors the
 * `tournament:simulate` result logic and honours the same "ended" gate the fetch command uses, so
 * it can never offer a score for a match that is not over. Bound in place of {@see
 * ManualScoreProvider} on local via config('scoring.simulated_provider').
 */
class SimulatedScoreProvider implements ScoreProvider
{
    public function fetch(Tournament $tournament): iterable
    {
        $scores = new DeterministicScores;

        $fixtures = $tournament->fixtures()
            ->ended()
            ->whereNull('home_goals')
            ->with(['phase', 'group.teams'])
            ->get();

        foreach ($fixtures as $fixture) {
            $proposed = $this->regulationScore($fixture, $scores);

            if ($proposed !== null) {
                yield $proposed;
            }
        }
    }

    /**
     * The live scoreline of every in-play fixture (gone live, no official result yet), revealed
     * progressively toward the same regulation result {@see fetch()} will settle, so the live board
     * converges to the proposed final. Penalties/winner are never shown live — they belong to the
     * final, settled through the proposal/approval pipeline.
     */
    public function live(Tournament $tournament): iterable
    {
        $scores = new DeterministicScores;
        $duration = (int) config('scoring.match_duration_minutes') * 60;

        $fixtures = $tournament->fixtures()
            ->where('status', FixtureStatus::Live)
            ->whereNull('home_goals')
            ->whereNotNull('kicks_off_at')
            ->with(['phase', 'group.teams'])
            ->get();

        foreach ($fixtures as $fixture) {
            $target = $this->regulationScore($fixture, $scores);

            if ($target === null) {
                // A knockout whose participants aren't projected yet — nothing to reveal.
                continue;
            }

            $elapsed = now()->getTimestamp() - $fixture->kicks_off_at->getTimestamp();
            $fraction = $duration === 0 ? 1.0 : max(0.0, min(1.0, $elapsed / $duration));

            yield new LiveScore(
                matchNumber: $fixture->match_number,
                homeGoals: $this->goalsRevealed($scores, $fixture->match_number, 'h', $target->homeGoals, $fraction),
                awayGoals: $this->goalsRevealed($scores, $fixture->match_number, 'a', $target->awayGoals, $fraction),
            );
        }
    }

    /**
     * Route a fixture to its deterministic regulation result — the single source shared by the
     * {@see fetch()} final and the {@see live()} target, so the two can never diverge.
     */
    private function regulationScore(Fixture $fixture, DeterministicScores $scores): ?ProposedScore
    {
        return $fixture->isKnockout()
            ? $this->knockoutScore($fixture, $scores)
            : $this->groupScore($fixture, $scores);
    }

    /**
     * How many of a side's regulation goals have been scored by elapsed fraction `$fraction`. Each
     * goal gets a stable threshold in [0.05, 0.95): the floor keeps a goal off the board at the
     * whistle, and the ceiling guarantees every goal is revealed by full time (f=1) — so the count
     * is deterministic, monotonic, and lands exactly on the regulation total.
     */
    private function goalsRevealed(DeterministicScores $scores, int $matchNumber, string $side, int $targetGoals, float $fraction): int
    {
        $revealed = 0;

        for ($i = 0; $i < $targetGoals; $i++) {
            $threshold = $scores->noise($matchNumber, $side, 'g', $i) * 0.9 + 0.05;

            if ($fraction >= $threshold) {
                $revealed++;
            }
        }

        return $revealed;
    }

    private function groupScore(Fixture $fixture, DeterministicScores $scores): ProposedScore
    {
        $positions = $fixture->group->teams->mapWithKeys(
            fn ($team): array => [$team->id => (int) $team->pivot->position],
        );

        $home = $scores->biasedGoals($scores->noise($fixture->match_number, 'oh'), $positions[$fixture->home_team_id]);
        $away = $scores->biasedGoals($scores->noise($fixture->match_number, 'oa'), $positions[$fixture->away_team_id]);

        return new ProposedScore(
            matchNumber: $fixture->match_number,
            homeGoals: $home,
            awayGoals: $away,
            winnerTeamId: $home === $away
                ? null
                : ($home > $away ? $fixture->home_team_id : $fixture->away_team_id),
        );
    }

    /**
     * A knockout result, only once both participants are known (an un-projected slot is skipped).
     */
    private function knockoutScore(Fixture $fixture, DeterministicScores $scores): ?ProposedScore
    {
        if ($fixture->home_team_id === null || $fixture->away_team_id === null) {
            return null;
        }

        $matchNumber = $fixture->match_number;
        $homeAdvances = $scores->noise($matchNumber, 'kw') < 0.5;
        $winnerId = $homeAdvances ? $fixture->home_team_id : $fixture->away_team_id;

        if ($scores->noise($matchNumber, 'pen') < 0.2) {
            // Level after regulation — decided on penalties.
            $level = (int) floor($scores->noise($matchNumber, 'dg') * 3);
            $winnerPens = 4 + (int) floor($scores->noise($matchNumber, 'wp') * 2);
            $loserPens = 2 + (int) floor($scores->noise($matchNumber, 'lp') * 2);

            return new ProposedScore(
                matchNumber: $matchNumber,
                homeGoals: $level,
                awayGoals: $level,
                winnerTeamId: $winnerId,
                homePenalties: $homeAdvances ? $winnerPens : $loserPens,
                awayPenalties: $homeAdvances ? $loserPens : $winnerPens,
            );
        }

        $winnerGoals = 1 + (int) floor($scores->noise($matchNumber, 'wg') * 3);
        $loserGoals = (int) floor($scores->noise($matchNumber, 'lg') * $winnerGoals);

        return new ProposedScore(
            matchNumber: $matchNumber,
            homeGoals: $homeAdvances ? $winnerGoals : $loserGoals,
            awayGoals: $homeAdvances ? $loserGoals : $winnerGoals,
            winnerTeamId: $winnerId,
        );
    }
}
