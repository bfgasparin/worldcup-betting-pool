<?php

namespace App\Services\Scoring;

use App\Enums\BatchStatus;
use App\Enums\FixtureStatus;
use App\Enums\ProposalStatus;
use App\Models\ScoreBatch;
use App\Models\ScoreProposal;
use App\Models\User;
use App\Services\Predictions\OfficialBracketProjector;
use Illuminate\Support\Facades\DB;

/**
 * Applies an approved batch of proposed scores, end to end and atomically: it writes each
 * (non-rejected) proposal onto its fixture and re-projects the official bracket from the new
 * results. The results are shared by every game played over the tournament, so it then cascades
 * to each game — recomputing its entries' points and snapshotting its leaderboard ranks so
 * movement arrows have a baseline. Running it again after a correction simply re-applies and
 * recomputes.
 */
class ApproveScoreBatch
{
    public function __construct(
        private readonly OfficialBracketProjector $projector = new OfficialBracketProjector,
        private readonly ScoreEngine $engine = new ScoreEngine,
        private readonly RankSnapshotter $snapshotter = new RankSnapshotter,
        private readonly LeaderboardNotifier $notifier = new LeaderboardNotifier,
    ) {}

    public function approve(ScoreBatch $batch, User $approver): void
    {
        $tournament = $batch->tournament;
        $games = $tournament->games()->get();

        DB::transaction(function () use ($batch, $tournament, $games, $approver): void {
            $proposals = $batch->proposals()
                ->where('status', '!=', ProposalStatus::Rejected)
                ->with('fixture')
                ->get();

            foreach ($proposals as $proposal) {
                $this->applyToFixture($proposal);
                $proposal->update(['status' => ProposalStatus::Applied]);
            }

            $batch->update([
                'status' => BatchStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $approver->id,
            ]);

            // Fixtures now carry the official results; cascade the bracket once, then re-score
            // and rank each game played over this tournament.
            $this->projector->project($tournament);

            foreach ($games as $game) {
                $this->engine->recompute($game);
                $this->snapshotter->snapshot($game);
            }
        });

        // Ranks are committed; email each game's players about milestones and significant moves.
        // Kept outside the transaction so a later game's failure can't fire and then roll back.
        foreach ($games as $game) {
            $this->notifier->notify($game);
        }
    }

    private function applyToFixture(ScoreProposal $proposal): void
    {
        $proposal->fixture->update([
            'home_goals' => $proposal->home_goals,
            'away_goals' => $proposal->away_goals,
            'winner_team_id' => $proposal->winner_team_id,
            'home_penalties' => $proposal->home_penalties,
            'away_penalties' => $proposal->away_penalties,
            'status' => FixtureStatus::Finished,
        ]);
    }
}
