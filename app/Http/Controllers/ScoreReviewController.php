<?php

namespace App\Http\Controllers;

use App\Enums\BatchStatus;
use App\Enums\ProposalStatus;
use App\Http\Requests\Tournaments\ApproveScoreBatchRequest;
use App\Http\Requests\Tournaments\UpdateScoreProposalRequest;
use App\Models\Fixture;
use App\Models\Game;
use App\Models\ScoreBatch;
use App\Models\ScoreProposal;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\Scoring\ApproveScoreBatch;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ScoreReviewController extends Controller
{
    /**
     * The admin screen for reviewing, editing and approving a batch of proposed official scores.
     */
    public function review(Game $game): Response
    {
        $tournament = $game->tournament;
        $batch = $this->currentOpenBatch($tournament);
        $proposals = $batch?->proposals()->get()->keyBy('fixture_id') ?? collect();

        $fixtures = $tournament->fixtures()
            ->with(['phase', 'homeTeam', 'awayTeam'])
            ->orderBy('match_number')
            ->get()
            // Ended matches still awaiting an official result, plus any already proposed in this
            // batch — a score can only be entered once a match is over.
            ->filter(fn (Fixture $fixture): bool => ($fixture->hasEnded() && $fixture->home_goals === null) || $proposals->has($fixture->id));

        return Inertia::render('games/scores/review', [
            'game' => [
                'slug' => $game->slug,
                'name' => $game->name,
            ],
            'rows' => $fixtures->map(function (Fixture $fixture) use ($proposals): array {
                /** @var ScoreProposal|null $proposal */
                $proposal = $proposals->get($fixture->id);

                return [
                    'fixture_id' => $fixture->id,
                    'match_number' => $fixture->match_number,
                    'phase' => $fixture->phase->name,
                    'is_knockout' => $fixture->isKnockout(),
                    'status' => $fixture->status->value,
                    'has_ended' => $fixture->hasEnded(),
                    'kicks_off_at' => $fixture->kicks_off_at?->toIso8601String(),
                    'home' => $this->teamRef($fixture->homeTeam),
                    'away' => $this->teamRef($fixture->awayTeam),
                    'home_label' => $fixture->homeTeam?->name ?? $fixture->home_placeholder_label,
                    'away_label' => $fixture->awayTeam?->name ?? $fixture->away_placeholder_label,
                    'proposal' => $proposal === null ? null : [
                        'home_goals' => $proposal->home_goals,
                        'away_goals' => $proposal->away_goals,
                        'winner_team_id' => $proposal->winner_team_id,
                        'home_penalties' => $proposal->home_penalties,
                        'away_penalties' => $proposal->away_penalties,
                        'status' => $proposal->status->value,
                    ],
                ];
            })->values()->all(),
        ]);
    }

    /**
     * Create or update the proposed score for one fixture in the tournament's open batch.
     */
    public function updateProposal(UpdateScoreProposalRequest $request, Game $game, Fixture $fixture): RedirectResponse
    {
        abort_unless($fixture->tournament_id === $game->tournament->id, 404);

        $batch = $this->ensureOpenBatch($game->tournament);
        $validated = $request->validated();

        ScoreProposal::updateOrCreate(
            ['score_batch_id' => $batch->id, 'fixture_id' => $fixture->id],
            [
                'home_goals' => $validated['home_goals'] ?? null,
                'away_goals' => $validated['away_goals'] ?? null,
                'winner_team_id' => $request->winnerTeamIdFor(),
                'home_penalties' => $validated['home_penalties'] ?? null,
                'away_penalties' => $validated['away_penalties'] ?? null,
                'status' => ($validated['rejected'] ?? false) ? ProposalStatus::Rejected : ProposalStatus::Edited,
            ],
        );

        return back();
    }

    /**
     * Approve the open batch: write the scores, project the bracket, score everyone and rank.
     */
    public function approve(ApproveScoreBatchRequest $request, Game $game, ApproveScoreBatch $action): RedirectResponse
    {
        $action->approve($request->openBatch(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Results approved and points updated.')]);

        return to_route('games.show', $game);
    }

    private function currentOpenBatch(Tournament $tournament): ?ScoreBatch
    {
        return $tournament->scoreBatches()
            ->where('status', BatchStatus::Open)
            ->latest('id')
            ->first();
    }

    private function ensureOpenBatch(Tournament $tournament): ScoreBatch
    {
        return $tournament->scoreBatches()->firstOrCreate(
            ['status' => BatchStatus::Open],
            ['source' => 'manual', 'fetched_at' => now()],
        );
    }

    /**
     * @return array{id: int, name: string, code: ?string, flag_url: string}|null
     */
    private function teamRef(?Team $team): ?array
    {
        if ($team === null) {
            return null;
        }

        return [
            'id' => $team->id,
            'name' => $team->name,
            'code' => $team->code,
            'flag_url' => $team->flag_url,
        ];
    }
}
