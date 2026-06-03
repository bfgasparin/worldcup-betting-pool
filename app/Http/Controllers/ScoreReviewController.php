<?php

namespace App\Http\Controllers;

use App\Enums\BatchStatus;
use App\Enums\OrderingScope;
use App\Enums\ProposalStatus;
use App\Http\Requests\Tournaments\ApproveScoreBatchRequest;
use App\Http\Requests\Tournaments\UpdateGroupOrderingRequest;
use App\Http\Requests\Tournaments\UpdateScoreProposalRequest;
use App\Models\Fixture;
use App\Models\Game;
use App\Models\ScoreBatch;
use App\Models\ScoreProposal;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\Predictions\GroupStandingsPresenter;
use App\Services\Predictions\OfficialBracketProjector;
use App\Services\Predictions\TieResolutionState;
use App\Services\Predictions\TieState;
use App\Services\Scoring\ApproveScoreBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
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

        // The tie section is scoped to the GROUPS this batch is actually reviewing. So it surfaces
        // while group results are pending (and disappears once approved, like a match), and a later
        // knockout-only batch never re-surfaces group ties that were resolved and published earlier.
        $reviewedGroups = $this->groupsUnderReview($tournament, $proposals);

        $tiedGroups = [];
        $thirdsTie = null;

        if ($reviewedGroups !== []) {
            $tieState = (new TieResolutionState)->forTournament($tournament, $batch);
            $teamsById = $tournament->groups()->with('teams')->get()->flatMap->teams->keyBy('id');
            $tiedGroups = array_values(array_filter(
                $this->mapTiedGroups($tieState, $teamsById),
                fn (array $group): bool => in_array($group['name'], $reviewedGroups, true),
            ));
            $thirdsTie = $this->mapThirdsTie($tieState, $teamsById);
        }

        return Inertia::render('games/scores/review', [
            'game' => [
                'slug' => $game->slug,
                'name' => $game->name,
            ],
            'tied_groups' => $tiedGroups,
            'thirds_tie' => $thirdsTie,
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

    /**
     * Save the admin's manual ordering of an unresolved tie in the official results (a within-group
     * cluster or the thirds cut), then re-project so a now-resolved tie fills its bracket slots and
     * can open phased prediction windows.
     */
    public function updateOrdering(UpdateGroupOrderingRequest $request, Game $game, OfficialBracketProjector $projector): RedirectResponse
    {
        $tournament = $game->tournament;
        $scope = OrderingScope::from($request->string('scope')->value());
        $ordered = array_map('intval', $request->input('ordered_team_ids'));
        $tied = $ordered;
        sort($tied);

        $groupId = $scope === OrderingScope::WithinGroup
            ? $tournament->groups()->where('name', $request->input('group'))->value('id')
            : null;

        $tournament->groupOrderings()->updateOrCreate(
            ['group_id' => $groupId, 'scope' => $scope],
            ['tied_team_ids' => $tied, 'ordered_team_ids' => $ordered],
        );

        $projector->project($tournament);

        return back();
    }

    /**
     * The names of groups the open batch is actually reviewing — those with a non-rejected proposal
     * for one of their fixtures. Empty for a knockout-only (or empty) batch, so published group ties
     * don't re-surface.
     *
     * @param  Collection<int, ScoreProposal>  $proposals  keyed by fixture id
     * @return list<string>
     */
    private function groupsUnderReview(Tournament $tournament, Collection $proposals): array
    {
        if ($proposals->isEmpty()) {
            return [];
        }

        $names = [];

        foreach ($tournament->groups()->with('fixtures')->get() as $group) {
            foreach ($group->fixtures as $fixture) {
                $proposal = $proposals->get($fixture->id);

                if ($proposal !== null && $proposal->status !== ProposalStatus::Rejected) {
                    $names[] = $group->name;

                    break;
                }
            }
        }

        return $names;
    }

    /**
     * The complete groups whose official standings are tied, with their ranked rows and the tied
     * clusters the admin must drag into order.
     *
     * @param  Collection<int, Team>  $teamsById
     * @return list<array<string, mixed>>
     */
    private function mapTiedGroups(TieState $state, Collection $teamsById): array
    {
        $tied = [];

        foreach ($state->groupTies as $groupName => $clusters) {
            $standings = $state->standings[$groupName];

            $tied[] = [
                'name' => $groupName,
                'standings' => GroupStandingsPresenter::rows($standings, $teamsById),
                'tied' => array_map(fn (array $cluster): array => [
                    'team_ids' => array_values($cluster['teamIds']),
                    'resolved' => $cluster['resolved'],
                ], $standings->tieClustersWithStatus()),
            ];
        }

        return $tied;
    }

    /**
     * The third-placed teams whose tie straddles the qualifying cut, with whether an ordering has
     * already resolved them, or null when there is no such tie.
     *
     * @param  Collection<int, Team>  $teamsById
     * @return array{teams: list<array<string, mixed>>, resolved: bool}|null
     */
    private function mapThirdsTie(TieState $state, Collection $teamsById): ?array
    {
        if ($state->thirds === []) {
            return null;
        }

        return [
            'teams' => array_map(fn (int $teamId): ?array => $this->teamRef($teamsById->get($teamId)), $state->thirds),
            'resolved' => $state->thirdsResolved,
        ];
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
