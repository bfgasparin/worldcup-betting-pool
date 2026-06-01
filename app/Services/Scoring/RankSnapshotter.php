<?php

namespace App\Services\Scoring;

use App\Models\Entry;
use App\Models\Tournament;
use Illuminate\Support\Facades\DB;

/**
 * Records each entry's leaderboard rank and the rank it held before this run, so the pool table
 * can show up/down movement after a batch of results lands. Call it once per approved batch,
 * right after {@see ScoreEngine::recompute()}.
 *
 * Ranking mirrors the leaderboard's own stable order: total points descending, unscored entries
 * (null points) last, ties broken by id. A brand-new entry (created since the last snapshot)
 * keeps a null `previous_rank`, which the UI renders as "new" rather than an arrow.
 */
class RankSnapshotter
{
    public function snapshot(Tournament $tournament): void
    {
        $ordered = $tournament->entries()
            ->orderBy('id')
            ->get()
            ->sortByDesc(fn (Entry $entry): int => $entry->total_points ?? PHP_INT_MIN)
            ->values();

        DB::transaction(function () use ($ordered): void {
            foreach ($ordered as $index => $entry) {
                $entry->update([
                    'previous_rank' => $entry->rank,
                    'rank' => $index + 1,
                ]);
            }
        });
    }
}
