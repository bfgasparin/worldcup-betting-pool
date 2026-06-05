<?php

namespace App\Services\Pools;

use App\Enums\PhaseKey;
use App\Enums\PhaseType;
use App\Enums\PredictionWindowStatus;
use App\Enums\TournamentStatus;
use App\Http\Controllers\PoolController;
use App\Models\Entry;
use App\Models\Pool;
use App\Services\Predictions\PredictionWindowResolver;
use App\Services\Predictions\TieResolutionState;

/**
 * The single source of truth for "does this player have outstanding work in an open prediction
 * window?" — consumed both by the always-shared sidebar ({@see JoinedPools}, lean boolean) and the
 * pool page ({@see PoolController::show()}, rich per-window summary).
 *
 * Attention spans every open window: an upfront pool's group-stage scores and the ties the player
 * must still break, and each open knockout round of a phased pool whose picks are unfinished. Bound
 * scoped in the container so the sidebar and the page share one instance per request and never
 * resolve the same bracket twice (results are memoised per pool + entry).
 */
class PredictionAttention
{
    /**
     * Memoised summaries keyed by "{poolId}:{entryId}", so the middleware-built sidebar and the
     * controller-built banner don't each pay for the (heavy) bracket/window resolution.
     *
     * @var array<string, AttentionSummary>
     */
    private array $cache = [];

    public function __construct(
        private readonly PredictionWindowResolver $windowResolver = new PredictionWindowResolver,
        private readonly TieResolutionState $tieResolution = new TieResolutionState,
    ) {}

    /**
     * Whether the pool wants the player's attention — the lean path for the sidebar dot.
     */
    public function needsAttention(Pool $pool, ?Entry $entry): bool
    {
        return $this->summary($pool, $entry)->needsAttention;
    }

    /**
     * The full breakdown of every open window with unfinished work — the rich path for the banner.
     */
    public function summary(Pool $pool, ?Entry $entry): AttentionSummary
    {
        $key = $pool->id.':'.($entry?->id ?? 'none');

        return $this->cache[$key] ??= $this->compute($pool, $entry);
    }

    private function compute(Pool $pool, ?Entry $entry): AttentionSummary
    {
        if ($entry === null) {
            return new AttentionSummary(false);
        }

        return $pool->usesPhasedPredictionWindows()
            ? $this->phasedSummary($pool, $entry)
            : $this->upfrontSummary($pool, $entry);
    }

    /**
     * Upfront pools have one window: the group-stage scores plus any ties the engine couldn't break.
     * The (expensive) tie resolution only runs once every group score is in — the only state where a
     * tie is the remaining work — so the sidebar stays cheap while the window fills up.
     */
    private function upfrontSummary(Pool $pool, Entry $entry): AttentionSummary
    {
        if (! $pool->acceptsPredictions()) {
            return new AttentionSummary(false);
        }

        $total = $this->groupFixtureCount($pool);
        $missing = max(0, $total - $this->groupPredictionCount($entry));

        $hasUnresolvedTies = $missing === 0 && $this->tieResolution->forEntry($entry)->blocked();

        if ($missing === 0 && ! $hasUnresolvedTies) {
            return new AttentionSummary(false);
        }

        return new AttentionSummary(true, [[
            'phase_key' => PhaseKey::Group->value,
            'label' => 'Group stage',
            'deadline' => $pool->predictionsLockAt()?->toIso8601String(),
            'missing_count' => $missing,
            'total_count' => $total,
            'has_unresolved_ties' => $hasUnresolvedTies,
        ]]);
    }

    /**
     * Phased pools open the group stage on the pool lock, then each knockout round as its real
     * participants land. Each currently-open window with unfinished picks contributes a line. Phased
     * pools predict the official bracket, so standings ties never block — only missing picks do.
     */
    private function phasedSummary(Pool $pool, Entry $entry): AttentionSummary
    {
        $windows = [];

        if ($pool->acceptsPredictions()) {
            $total = $this->groupFixtureCount($pool);
            $missing = max(0, $total - $this->groupPredictionCount($entry));

            if ($missing > 0) {
                $windows[] = [
                    'phase_key' => PhaseKey::Group->value,
                    'label' => 'Group stage',
                    'deadline' => $pool->predictionsLockAt()?->toIso8601String(),
                    'missing_count' => $missing,
                    'total_count' => $total,
                    'has_unresolved_ties' => false,
                ];
            }
        }

        // Knockout rounds can only be open once the group stage has produced official results; skip
        // the window resolution entirely while the tournament is still upcoming.
        if ($pool->tournament->status !== TournamentStatus::Upcoming) {
            $windows = [...$windows, ...$this->openKnockoutWindows($pool, $entry)];
        }

        return new AttentionSummary($windows !== [], $windows);
    }

    /**
     * The open knockout rounds of a phased pool that still have unfinished picks, each as a window
     * line. A pick is complete only when both goals are set (a blank/partial row persists with null
     * goals), so completeness is goals-based, not a row count.
     *
     * @return list<array{phase_key: string, label: string, deadline: ?string, missing_count: int, total_count: int, has_unresolved_ties: bool}>
     */
    private function openKnockoutWindows(Pool $pool, Entry $entry): array
    {
        $statuses = $this->windowResolver->windows($pool);
        $entry->loadMissing('knockoutPredictions');
        $predictions = $entry->knockoutPredictions->keyBy('fixture_id');

        $windows = [];

        foreach ($pool->tournament->phases as $phase) {
            if ($phase->type !== PhaseType::Knockout
                || ($statuses[$phase->key->value] ?? null) !== PredictionWindowStatus::Open) {
                continue;
            }

            $total = $phase->fixtures->count();
            $made = $phase->fixtures->filter(function ($fixture) use ($predictions): bool {
                $prediction = $predictions->get($fixture->id);

                return $prediction !== null && $prediction->home_goals !== null && $prediction->away_goals !== null;
            })->count();

            if ($made < $total) {
                $windows[] = [
                    'phase_key' => $phase->key->value,
                    'label' => $phase->name,
                    'deadline' => $this->windowResolver->lockAtFor($pool, $phase)?->toIso8601String(),
                    'missing_count' => $total - $made,
                    'total_count' => $total,
                    'has_unresolved_ties' => false,
                ];
            }
        }

        return $windows;
    }

    /**
     * The viewer's completed group-stage picks, preferring already-loaded data so the sidebar's one
     * list query and the pool page's eager loads aren't re-run.
     */
    private function groupPredictionCount(Entry $entry): int
    {
        $count = $entry->getAttribute('group_predictions_count');

        if ($count !== null) {
            return (int) $count;
        }

        return $entry->relationLoaded('groupPredictions')
            ? $entry->groupPredictions->count()
            : $entry->groupPredictions()->count();
    }

    private function groupFixtureCount(Pool $pool): int
    {
        $count = $pool->tournament->getAttribute('group_fixtures_count');

        if ($count !== null) {
            return (int) $count;
        }

        if ($pool->tournament->relationLoaded('groups')) {
            return $pool->tournament->groups->sum(
                fn ($group): int => $group->relationLoaded('fixtures') ? $group->fixtures->count() : $group->fixtures()->count(),
            );
        }

        return $pool->tournament->groupFixtures()->count();
    }
}
