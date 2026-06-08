<?php

namespace App\Http\Controllers;

use App\Enums\FixtureStatus;
use App\Enums\LeaderboardCategory;
use App\Enums\LiveStatus;
use App\Models\Fixture;
use App\Models\Pool;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\Scoring\LiveProjection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The player-facing Live Center: a landing that routes to the tournament being played live, and a
 * per-tournament view of the live scores plus the projected leaderboards for the pools the viewer
 * has joined. All projection data is read-only — the official leaderboard is never touched.
 */
class LiveController extends Controller
{
    public function __construct(private readonly LiveProjection $projection = new LiveProjection) {}

    /**
     * Auto-open the single live tournament; show the picker only when several are live at once.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $userId = $request->user()->id;

        $tournaments = Tournament::query()
            ->whereHas('pools.entries', fn ($query) => $query->where('user_id', $userId))
            ->whereHas('fixtures', fn ($query) => $query->hasLiveState())
            ->withCount(['fixtures as live_match_count' => fn ($query) => $query->hasLiveState()])
            ->orderBy('name')
            ->get();

        if ($tournaments->count() === 1) {
            return redirect()->route('live.show', $tournaments->first());
        }

        return Inertia::render('live/index', [
            'tournaments' => $tournaments->map(fn (Tournament $tournament): array => [
                'name' => $tournament->name,
                'slug' => $tournament->slug,
                'live_match_count' => $tournament->live_match_count,
            ])->values(),
        ]);
    }

    public function show(Request $request, Tournament $tournament): Response
    {
        $userId = $request->user()->id;

        $pools = $tournament->pools()
            ->whereHas('entries', fn ($query) => $query->where('user_id', $userId))
            ->orderBy('id')
            ->get();

        abort_if($pools->isEmpty(), 403);

        return Inertia::render('live/show', [
            'tournament' => [
                'name' => $tournament->name,
                'slug' => $tournament->slug,
            ],
            'boards' => $this->boardDescriptors(),
            'poll_interval_ms' => (int) config('scoring.live_poll_interval_ms'),
            'pools' => $pools->map(fn (Pool $pool): array => [
                'slug' => $pool->slug,
                'name' => $pool->name,
                'source' => $pool->source,
                'accent' => $pool->accent?->value,
                'scoring_strategy' => $pool->scoring_strategy->value,
                'is_paid' => (float) $pool->entry_price > 0,
                'currency' => $pool->currency,
                'boards' => $this->projection->cachedFor($pool)->boards,
            ])->values(),
            // The live-changing data sits under its own key so the client can partial-reload just
            // this (and the pools' boards) on a poll without re-rendering the static chrome.
            'liveFixtures' => $this->liveFixtures($tournament),
        ]);
    }

    /**
     * The tournament's currently-live fixtures (live or ended-awaiting-approval), with their live
     * scoreline. Finished (approved) fixtures drop out — their result is official now.
     *
     * @return list<array<string, mixed>>
     */
    private function liveFixtures(Tournament $tournament): array
    {
        return $tournament->fixtures()
            ->where('status', FixtureStatus::Live)
            ->whereHas('liveState')
            ->with(['liveState', 'homeTeam', 'awayTeam', 'phase'])
            ->orderBy('kicks_off_at')
            ->get()
            ->filter(fn (Fixture $fixture): bool => in_array($fixture->liveState->status, [LiveStatus::Live, LiveStatus::Ended], true))
            ->map(fn (Fixture $fixture): array => [
                'id' => $fixture->id,
                'home_team' => $this->teamRef($fixture->homeTeam),
                'away_team' => $this->teamRef($fixture->awayTeam),
                'home_label' => $fixture->home_placeholder_label,
                'away_label' => $fixture->away_placeholder_label,
                'home_goals' => $fixture->liveState->home_goals,
                'away_goals' => $fixture->liveState->away_goals,
                'status' => $fixture->liveState->status->value,
                'is_knockout' => $fixture->isKnockout(),
                'kicks_off_at' => $fixture->kicks_off_at?->toIso8601String(),
                'started_at' => $fixture->liveState->started_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * The labels for each projected board, shared across pools (the per-pool rows live under each
     * pool's `boards`).
     *
     * @return list<array<string, mixed>>
     */
    private function boardDescriptors(): array
    {
        return array_map(fn (LeaderboardCategory $category): array => [
            'key' => $category->value,
            'label' => $category->label(),
            'description' => $category->description(),
            'primary_stat_label' => $category->primaryStatLabel(),
            'secondary_stat_label' => $category->secondaryStatLabel(),
            'awards_prizes' => $category->awardsPrizes(),
        ], LeaderboardCategory::ordered());
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
