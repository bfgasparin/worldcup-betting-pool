<?php

namespace App\Services\Scoring;

use App\Enums\LeaderboardCategory;
use App\Models\Entry;
use App\Models\Game;
use App\Models\LeaderboardStanding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Records each entry's rank — and the rank it held before this run — on every leaderboard, so the
 * tables can show up/down movement after a batch of results lands. Call it once per approved batch,
 * right after {@see ScoreEngine::recompute()} has written the standings.
 *
 * Each board ranks its {@see LeaderboardStanding} rows by value descending, then tiebreaker
 * descending, then entry id. The Overall board's rank is also mirrored onto `Entry.rank`/
 * `previous_rank` (computed straight from `total_points`, unscored entries last) so the notifier and
 * the game-page preview keep reading it there. A brand-new entry keeps a null `previous_rank`,
 * which the UI renders as "new" rather than an arrow.
 */
class RankSnapshotter
{
    public function snapshot(Game $game): void
    {
        $entries = $game->entries()->orderBy('id')->get();

        DB::transaction(function () use ($game, $entries): void {
            $this->mirrorOverallToEntries($entries);
            $this->rankStandings($game);
        });
    }

    /**
     * Mirror the Overall ranking onto the entries themselves, straight from `total_points` (unscored
     * entries last). Independent of the standings rows so it holds even before a recompute.
     *
     * @param  Collection<int, Entry>  $entries
     */
    private function mirrorOverallToEntries(Collection $entries): void
    {
        $ordered = $entries
            ->sortByDesc(fn (Entry $entry): int => $entry->total_points ?? PHP_INT_MIN)
            ->values();

        foreach ($ordered as $index => $entry) {
            $entry->update([
                'previous_rank' => $entry->rank,
                'rank' => $index + 1,
            ]);
        }
    }

    /**
     * Rank every board's standing rows in place.
     */
    private function rankStandings(Game $game): void
    {
        $entryIds = $game->entries()->pluck('id');

        if ($entryIds->isEmpty()) {
            return;
        }

        foreach (LeaderboardCategory::cases() as $category) {
            $ordered = LeaderboardStanding::query()
                ->whereIn('entry_id', $entryIds)
                ->where('category', $category)
                ->orderByDesc('value')
                ->orderByDesc('tiebreaker')
                ->orderBy('entry_id')
                ->get();

            foreach ($ordered as $index => $standing) {
                $standing->update([
                    'previous_rank' => $standing->rank,
                    'rank' => $index + 1,
                ]);
            }
        }
    }
}
