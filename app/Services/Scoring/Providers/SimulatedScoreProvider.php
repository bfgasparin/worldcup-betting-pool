<?php

namespace App\Services\Scoring\Providers;

use App\Contracts\ScoreProvider;
use App\Models\Fixture;
use App\Models\Tournament;
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
            $proposed = $fixture->isKnockout()
                ? $this->knockoutScore($fixture, $scores)
                : $this->groupScore($fixture, $scores);

            if ($proposed !== null) {
                yield $proposed;
            }
        }
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
