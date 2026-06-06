<?php

namespace App\Services\Scoring;

use App\Enums\LeaderboardCategory;
use App\Models\Entry;
use App\Models\Fixture;
use App\Models\Pool;
use Illuminate\Support\Collection;

/**
 * Builds the leaderboard page for one selected matchday. The current matchday reads the live
 * standings the {@see ScoreEngine} already maintains; a past matchday is reconstructed from the
 * stored per-fixture breakdowns, so the table shows how each board stood at that matchday's end and
 * the cards show what each player earned within it. Every board is expressed in its own metric.
 */
class MatchdayLeaderboard
{
    public function __construct(
        private readonly MatchdayCatalog $catalog = new MatchdayCatalog,
        private readonly ScoreEngine $scoreEngine = new ScoreEngine,
        private readonly ScoringRulesFactory $rulesFactory = new ScoringRulesFactory,
    ) {}

    public function build(Pool $pool, int $userId, ?string $requestedMatchday): MatchdayLeaderboardView
    {
        $tournament = $pool->tournament;
        $matchdays = $this->catalog->forTournament($tournament);

        /** @var Collection<int, Fixture> $fixturesById */
        $fixturesById = $tournament->fixtures()->with('phase')->get()->keyBy('id');

        $statuses = array_map(fn (Matchday $md): string => $this->statusFor($md, $fixturesById), $matchdays);
        $currentIndex = $this->currentIndex($statuses);
        $selectedIndex = $this->selectedIndex($matchdays, $statuses, $currentIndex, $requestedMatchday);
        $selectedIsCurrent = $selectedIndex === $currentIndex;

        $descriptors = [];
        foreach ($matchdays as $i => $matchday) {
            $descriptors[] = $this->descriptor($matchday, $statuses[$i], $i === $currentIndex, $fixturesById);
        }

        $entries = $pool->entries()
            ->with(['user', 'groupPredictions', 'knockoutPredictions', 'standings'])
            ->orderBy('id')
            ->get();

        $rules = $this->rulesFactory->make($pool->scoring_strategy);
        $config = ScoringConfig::fromPool($pool);
        $fixtures = $fixturesById->all();

        /** @var array<int, array<int, PredictionBreakdown>> $breakdownsByEntry */
        $breakdownsByEntry = [];
        foreach ($entries as $entry) {
            $breakdownsByEntry[$entry->id] = $this->scoreEngine->breakdownsByFixture($entry, $fixtures, $rules, $config);
        }

        $cumulativeIds = $this->fixtureIdsThrough($matchdays, $selectedIndex);
        $previousIds = $selectedIndex > 0 ? $this->fixtureIdsThrough($matchdays, $selectedIndex - 1) : [];
        $singleIds = $matchdays[$selectedIndex]->fixtureIds;

        $boards = array_map(
            fn (LeaderboardCategory $category): array => $this->board(
                $category,
                $entries,
                $userId,
                $selectedIsCurrent,
                $breakdownsByEntry,
                $cumulativeIds,
                $previousIds,
                $singleIds,
                $selectedIndex,
            ),
            LeaderboardCategory::ordered(),
        );

        return new MatchdayLeaderboardView(
            matchdays: $descriptors,
            selectedKey: $matchdays[$selectedIndex]->key,
            selectedIsCurrent: $selectedIsCurrent,
            boards: $boards,
            participants: $entries->count(),
        );
    }

    /**
     * `complete` once every fixture has an official result, `upcoming` while none do, otherwise
     * `in_progress`.
     *
     * @param  Collection<int, Fixture>  $fixturesById
     */
    private function statusFor(Matchday $matchday, Collection $fixturesById): string
    {
        $withResult = 0;
        foreach ($matchday->fixtureIds as $id) {
            $fixture = $fixturesById->get($id);
            if ($fixture !== null && $fixture->home_goals !== null && $fixture->away_goals !== null) {
                $withResult++;
            }
        }

        return match (true) {
            $withResult === 0 => 'upcoming',
            $withResult === count($matchday->fixtureIds) => 'complete',
            default => 'in_progress',
        };
    }

    /**
     * The current matchday is the latest one that has started; before any result lands it is the
     * first matchday (the page's landing stop).
     *
     * @param  list<string>  $statuses
     */
    private function currentIndex(array $statuses): int
    {
        $current = 0;
        foreach ($statuses as $i => $status) {
            if ($status !== 'upcoming') {
                $current = $i;
            }
        }

        return $current;
    }

    /**
     * The requested matchday when it exists and has started; otherwise the current one (you can
     * only travel to matchdays that have begun).
     *
     * @param  list<Matchday>  $matchdays
     * @param  list<string>  $statuses
     */
    private function selectedIndex(array $matchdays, array $statuses, int $currentIndex, ?string $requested): int
    {
        if ($requested === null) {
            return $currentIndex;
        }

        foreach ($matchdays as $i => $matchday) {
            if ($matchday->key === $requested) {
                return $statuses[$i] !== 'upcoming' ? $i : $currentIndex;
            }
        }

        return $currentIndex;
    }

    /**
     * @param  Collection<int, Fixture>  $fixturesById
     * @return array<string, mixed>
     */
    private function descriptor(Matchday $matchday, string $status, bool $isCurrent, Collection $fixturesById): array
    {
        $kickoffs = collect($matchday->fixtureIds)
            ->map(fn (int $id) => $fixturesById->get($id)?->kicks_off_at)
            ->filter()
            ->sort()
            ->values();

        return [
            'key' => $matchday->key,
            'label' => $matchday->label,
            'short_label' => $matchday->shortLabel,
            'kind' => $matchday->kind,
            'status' => $status,
            'is_current' => $isCurrent,
            'starts_at' => $kickoffs->first()?->toIso8601String(),
            'ends_at' => $kickoffs->last()?->toIso8601String(),
        ];
    }

    /**
     * Every fixture id through (and including) the matchday at `$index`.
     *
     * @param  list<Matchday>  $matchdays
     * @return list<int>
     */
    private function fixtureIdsThrough(array $matchdays, int $index): array
    {
        $ids = [];
        for ($i = 0; $i <= $index; $i++) {
            $ids = array_merge($ids, $matchdays[$i]->fixtureIds);
        }

        return $ids;
    }

    /**
     * @param  Collection<int, Entry>  $entries
     * @param  array<int, array<int, PredictionBreakdown>>  $breakdownsByEntry
     * @param  list<int>  $cumulativeIds
     * @param  list<int>  $previousIds
     * @param  list<int>  $singleIds
     * @return array<string, mixed>
     */
    private function board(
        LeaderboardCategory $category,
        Collection $entries,
        int $userId,
        bool $selectedIsCurrent,
        array $breakdownsByEntry,
        array $cumulativeIds,
        array $previousIds,
        array $singleIds,
        int $selectedIndex,
    ): array {
        if ($selectedIsCurrent) {
            $rows = $this->liveRows($category, $entries, $userId);
            $hasScores = $entries->contains(fn (Entry $entry): bool => $entry->total_points !== null);
        } else {
            [$rows, $hasScores] = $this->reconstructedRows(
                $category, $entries, $userId, $breakdownsByEntry, $cumulativeIds, $previousIds, $selectedIndex,
            );
        }

        return [
            'key' => $category->value,
            'label' => $category->label(),
            'description' => $category->description(),
            'primary_stat_label' => $category->primaryStatLabel(),
            'secondary_stat_label' => $category->secondaryStatLabel(),
            'awards_prizes' => $category->awardsPrizes(),
            'has_scores' => $hasScores,
            'rows' => $rows,
            'matchday_stats' => $this->matchdayStats($category, $entries, $userId, $breakdownsByEntry, $singleIds),
        ];
    }

    /**
     * The live board straight off the stored standings — the current matchday's table is exactly
     * today's leaderboard.
     *
     * @param  Collection<int, Entry>  $entries
     * @return list<array<string, mixed>>
     */
    private function liveRows(LeaderboardCategory $category, Collection $entries, int $userId): array
    {
        if ($category === LeaderboardCategory::Overall) {
            return $entries
                ->sortByDesc(fn (Entry $entry): int => $entry->total_points ?? PHP_INT_MIN)
                ->values()
                ->map(fn (Entry $entry, int $index): array => $this->row(
                    $index + 1, $entry, $userId, $entry->total_points, null, $entry->rank, $entry->previous_rank,
                ))
                ->all();
        }

        return $entries
            ->map(fn (Entry $entry): array => [
                'entry' => $entry,
                'standing' => $entry->standings->firstWhere('category', $category),
            ])
            ->sort(fn (array $a, array $b): int => ($b['standing']?->value ?? 0) <=> ($a['standing']?->value ?? 0)
                ?: ($b['standing']?->tiebreaker ?? 0) <=> ($a['standing']?->tiebreaker ?? 0)
                ?: $a['entry']->id <=> $b['entry']->id)
            ->values()
            ->map(fn (array $pair, int $index): array => $this->row(
                $index + 1,
                $pair['entry'],
                $userId,
                $pair['standing']?->value ?? 0,
                $pair['standing']?->tiebreaker ?? 0,
                $pair['standing']?->rank,
                $pair['standing']?->previous_rank,
            ))
            ->all();
    }

    /**
     * The board as it stood at the end of a past matchday: each entry's metric is rolled up from its
     * breakdowns through that matchday, ranked the same way the live board ranks, with movement
     * measured against the previous matchday's standings.
     *
     * @param  Collection<int, Entry>  $entries
     * @param  array<int, array<int, PredictionBreakdown>>  $breakdownsByEntry
     * @param  list<int>  $cumulativeIds
     * @param  list<int>  $previousIds
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    private function reconstructedRows(
        LeaderboardCategory $category,
        Collection $entries,
        int $userId,
        array $breakdownsByEntry,
        array $cumulativeIds,
        array $previousIds,
        int $selectedIndex,
    ): array {
        $cumulative = [];
        $previous = [];
        foreach ($entries as $entry) {
            $cumulative[$entry->id] = LeaderboardMetrics::fromBreakdowns($this->subset($breakdownsByEntry[$entry->id], $cumulativeIds));
            $previous[$entry->id] = LeaderboardMetrics::fromBreakdowns($this->subset($breakdownsByEntry[$entry->id], $previousIds));
        }

        $rankNow = $this->rankByMetrics($category, $entries, $cumulative);
        $rankPrevious = $this->rankByMetrics($category, $entries, $previous);

        $rows = $entries
            ->sortBy(fn (Entry $entry): int => $rankNow[$entry->id])
            ->values()
            ->map(function (Entry $entry) use ($category, $userId, $cumulative, $rankNow, $rankPrevious, $selectedIndex): array {
                $metrics = $cumulative[$entry->id];
                $rank = $rankNow[$entry->id];

                return $this->row(
                    $rank,
                    $entry,
                    $userId,
                    $category->valueFor($metrics),
                    $category === LeaderboardCategory::Overall ? null : $category->tiebreakerFor($metrics),
                    $rank,
                    $selectedIndex > 0 ? $rankPrevious[$entry->id] : null,
                );
            })
            ->all();

        $hasScores = collect($cumulative)->contains(
            fn (LeaderboardMetrics $metrics): bool => $metrics->points > 0 || $metrics->correctOutcomes > 0 || $metrics->teamGoalsHit > 0,
        );

        return [$rows, $hasScores];
    }

    /**
     * Rank every entry for a board by a metric map: value descending, tie-break descending, entry id
     * ascending — the same order the live board uses.
     *
     * @param  Collection<int, Entry>  $entries
     * @param  array<int, LeaderboardMetrics>  $metricsById
     * @return array<int, int>
     */
    private function rankByMetrics(LeaderboardCategory $category, Collection $entries, array $metricsById): array
    {
        $ordered = $entries
            ->sort(fn (Entry $a, Entry $b): int => $category->valueFor($metricsById[$b->id]) <=> $category->valueFor($metricsById[$a->id])
                ?: $category->tiebreakerFor($metricsById[$b->id]) <=> $category->tiebreakerFor($metricsById[$a->id])
                ?: $a->id <=> $b->id)
            ->values();

        $ranks = [];
        foreach ($ordered as $index => $entry) {
            $ranks[$entry->id] = $index + 1;
        }

        return $ranks;
    }

    /**
     * The you/top/lowest cards for the selected matchday, expressed in the board's metric. Only
     * entries with a scored prediction in the matchday count; all three are null until then.
     *
     * @param  Collection<int, Entry>  $entries
     * @param  array<int, array<int, PredictionBreakdown>>  $breakdownsByEntry
     * @param  list<int>  $singleIds
     * @return array{you: ?array<string, mixed>, top: ?array<string, mixed>, lowest: ?array<string, mixed>}
     */
    private function matchdayStats(
        LeaderboardCategory $category,
        Collection $entries,
        int $userId,
        array $breakdownsByEntry,
        array $singleIds,
    ): array {
        $participants = [];
        foreach ($entries as $entry) {
            $subset = $this->subset($breakdownsByEntry[$entry->id], $singleIds);
            if ($subset === []) {
                continue;
            }

            $participants[] = [
                'entry' => $entry,
                'value' => $category->valueFor(LeaderboardMetrics::fromBreakdowns($subset)),
            ];
        }

        if ($participants === []) {
            return ['you' => null, 'top' => null, 'lowest' => null];
        }

        $sorted = collect($participants)
            ->sort(fn (array $a, array $b): int => $b['value'] <=> $a['value'] ?: $a['entry']->id <=> $b['entry']->id)
            ->values();

        $mine = collect($participants)->first(fn (array $p): bool => $p['entry']->user_id === $userId);

        return [
            'you' => $mine !== null ? $this->stat($mine, $userId) : null,
            'top' => $this->stat($sorted->first(), $userId),
            'lowest' => $this->stat($sorted->last(), $userId),
        ];
    }

    /**
     * @param  array{entry: Entry, value: int}  $participant
     * @return array<string, mixed>
     */
    private function stat(array $participant, int $userId): array
    {
        $entry = $participant['entry'];

        return [
            'entry_id' => $entry->id,
            'name' => $entry->user_id === $userId ? 'You' : ($entry->user->name ?? 'Player'),
            'initials' => $this->initials($entry->user->name ?? ''),
            'avatar' => $entry->user->avatar,
            'value' => $participant['value'],
            'is_me' => $entry->user_id === $userId,
        ];
    }

    /**
     * A board row in the shape the Leaderboards page consumes. `$movementRank`/`$movementPreviousRank`
     * drive the movement arrow and may differ from the displayed rank (the live board moves against
     * its last snapshot; a past matchday moves against the previous matchday).
     *
     * @return array<string, mixed>
     */
    private function row(
        int $rank,
        Entry $entry,
        int $userId,
        ?int $primaryValue,
        ?int $secondaryValue,
        ?int $movementRank,
        ?int $movementPreviousRank,
    ): array {
        return [
            'rank' => $rank,
            'entry_id' => $entry->id,
            'name' => $entry->user_id === $userId ? 'You' : ($entry->user->name ?? 'Player'),
            'initials' => $this->initials($entry->user->name ?? ''),
            'avatar' => $entry->user->avatar,
            'primary_value' => $primaryValue,
            'secondary_value' => $secondaryValue,
            'is_me' => $entry->user_id === $userId,
            'movement' => RankMovement::direction($movementRank, $movementPreviousRank),
            'movement_delta' => RankMovement::delta($movementRank, $movementPreviousRank),
        ];
    }

    /**
     * Keep only the breakdowns whose fixture is in the given set.
     *
     * @param  array<int, PredictionBreakdown>  $breakdowns
     * @param  list<int>  $fixtureIds
     * @return array<int, PredictionBreakdown>
     */
    private function subset(array $breakdowns, array $fixtureIds): array
    {
        return array_intersect_key($breakdowns, array_flip($fixtureIds));
    }

    /**
     * Up to two initials from a display name (e.g. "Marina Jones" -> "MJ").
     */
    private function initials(string $name): string
    {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));

        $letters = collect($parts)
            ->take(2)
            ->map(fn (string $part): string => mb_substr($part, 0, 1))
            ->implode('');

        return mb_strtoupper($letters) ?: '?';
    }
}
