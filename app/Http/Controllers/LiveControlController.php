<?php

namespace App\Http\Controllers;

use App\Enums\FixtureStatus;
use App\Http\Requests\Live\LiveScoreRequest;
use App\Models\Fixture;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\Live\EndLiveMatch;
use App\Services\Live\GoLive;
use App\Services\Live\UpdateLiveScore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The admin live console: mark eligible fixtures live, keep their scoreline during the match, and
 * end a match to hand its final score to the existing score-proposal/approval pipeline. Gated by
 * the `manage-tournament` ability (applied on the route group).
 */
class LiveControlController extends Controller
{
    public function __construct(
        private readonly GoLive $goLive = new GoLive,
        private readonly UpdateLiveScore $updateLiveScore = new UpdateLiveScore,
        private readonly EndLiveMatch $endLiveMatch = new EndLiveMatch,
    ) {}

    public function index(Tournament $tournament): Response
    {
        $fixtures = $tournament->fixtures()
            ->with(['liveState', 'homeTeam', 'awayTeam', 'phase'])
            ->orderBy('kicks_off_at')
            ->get()
            // Every live-relevant fixture: eligible to go live now, already live/ended, or an
            // upcoming scheduled match with known teams (so the admin can browse and search ahead).
            // Empty placeholder knockout slots (no teams) are excluded — they can't go live.
            ->filter(fn (Fixture $fixture): bool => $fixture->canGoLive()
                || $fixture->liveState !== null
                || ($fixture->status === FixtureStatus::Scheduled
                    && $fixture->home_team_id !== null
                    && $fixture->away_team_id !== null))
            ->map(fn (Fixture $fixture): array => [
                'id' => $fixture->id,
                'home_team' => $this->teamRef($fixture->homeTeam),
                'away_team' => $this->teamRef($fixture->awayTeam),
                'home_label' => $fixture->home_placeholder_label,
                'away_label' => $fixture->away_placeholder_label,
                'kicks_off_at' => $fixture->kicks_off_at?->toIso8601String(),
                'is_knockout' => $fixture->isKnockout(),
                'can_go_live' => $fixture->canGoLive(),
                'live_status' => $fixture->liveState?->status->value,
                'live_home_goals' => $fixture->liveState?->home_goals,
                'live_away_goals' => $fixture->liveState?->away_goals,
            ])
            ->values()
            ->all();

        return Inertia::render('manage/live', [
            'tournament' => [
                'name' => $tournament->name,
                'slug' => $tournament->slug,
            ],
            'fixtures' => $fixtures,
        ]);
    }

    public function goLive(Request $request, Tournament $tournament, Fixture $fixture): RedirectResponse
    {
        $this->assertBelongs($tournament, $fixture);

        $this->goLive->mark($fixture);

        return back();
    }

    public function updateScore(LiveScoreRequest $request, Tournament $tournament, Fixture $fixture): RedirectResponse
    {
        $this->assertBelongs($tournament, $fixture);

        $this->updateLiveScore->update($fixture, $request->homeGoals(), $request->awayGoals());

        return back();
    }

    public function endMatch(Request $request, Tournament $tournament, Fixture $fixture): RedirectResponse
    {
        $this->assertBelongs($tournament, $fixture);

        $this->endLiveMatch->end($fixture);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Match ended — final score sent for approval.')]);

        return back();
    }

    private function assertBelongs(Tournament $tournament, Fixture $fixture): void
    {
        abort_unless($fixture->tournament_id === $tournament->id, 404);
    }

    /**
     * @return array{id: int, name: string, code: ?string, is_placeholder: bool, flag_url: string}|null
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
            'is_placeholder' => false,
            'flag_url' => $team->flag_url,
        ];
    }
}
