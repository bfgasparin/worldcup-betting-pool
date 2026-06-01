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
 * (non-rejected) proposal onto its fixture, re-projects the official bracket from the new
 * results, recomputes every entry's points, and snapshots the leaderboard ranks so movement
 * arrows have a baseline. Running it again after a correction simply re-applies and recomputes.
 */
class ApproveScoreBatch
{
    public function __construct(
        private readonly OfficialBracketProjector $projector = new OfficialBracketProjector,
        private readonly ScoreEngine $engine = new ScoreEngine,
        private readonly RankSnapshotter $snapshotter = new RankSnapshotter,
    ) {}

    public function approve(ScoreBatch $batch, User $approver): void
    {
        $tournament = $batch->tournament;

        DB::transaction(function () use ($batch, $tournament, $approver): void {
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

            // Fixtures now carry the official results; cascade the bracket, re-score and rank.
            $this->projector->project($tournament);
            $this->engine->recompute($tournament);
            $this->snapshotter->snapshot($tournament);
        });
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
