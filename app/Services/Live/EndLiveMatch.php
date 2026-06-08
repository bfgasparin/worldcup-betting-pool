<?php

namespace App\Services\Live;

use App\Enums\LiveStatus;
use App\Enums\ProposalStatus;
use App\Models\Fixture;
use App\Models\FixtureLiveState;
use App\Models\ScoreBatch;
use App\Models\ScoreProposal;
use App\Services\Scoring\ApproveScoreBatch;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Ends a live match: it hands the final live scoreline to the existing score-proposal pipeline as
 * a pending {@see ScoreProposal} in the tournament's open batch (where it surfaces on the admin
 * review screen exactly like any other proposed result) and closes the live scoreboard. It
 * deliberately leaves the official fixture untouched — the result only becomes official once an
 * admin approves the batch through {@see ApproveScoreBatch}.
 */
class EndLiveMatch
{
    /**
     * @throws HttpException 422 when the fixture is not live.
     */
    public function end(Fixture $fixture): ScoreProposal
    {
        $state = $fixture->liveState;

        abort_unless($state?->isLive() === true, 422, 'This fixture is not live.');

        return DB::transaction(function () use ($fixture, $state): ScoreProposal {
            $batch = ScoreBatch::openFor($fixture->tournament, 'live');

            $proposal = ScoreProposal::updateOrCreate(
                ['score_batch_id' => $batch->id, 'fixture_id' => $fixture->id],
                [
                    'home_goals' => $state->home_goals,
                    'away_goals' => $state->away_goals,
                    'winner_team_id' => $this->winnerTeamId($fixture, $state),
                    'status' => ProposalStatus::Pending,
                ],
            );

            $state->update([
                'status' => LiveStatus::Ended,
                'ended_at' => now(),
            ]);

            return $proposal;
        });
    }

    /**
     * The winning team implied by the live scoreline, or null on a draw or an unscored match. A
     * knockout drawn in regulation is handed off with no winner; the admin sets the winner and
     * penalties on the review screen before approving.
     */
    private function winnerTeamId(Fixture $fixture, FixtureLiveState $state): ?int
    {
        if ($state->home_goals === null || $state->away_goals === null || $state->home_goals === $state->away_goals) {
            return null;
        }

        return $state->home_goals > $state->away_goals
            ? $fixture->home_team_id
            : $fixture->away_team_id;
    }
}
