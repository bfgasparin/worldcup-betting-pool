<?php

namespace App\Services\Scoring;

use App\Models\Entry;
use App\Models\Game;
use App\Notifications\LeaderboardRankChangedNotification;
use App\Notifications\TopOfLeaderboardNotification;

/**
 * After ranks are snapshotted, emails players about the two leaderboard moments worth knowing:
 * newly reaching #1 (a milestone), and a significant climb or drop. It diffs each entry's freshly
 * written `rank` against its `previous_rank`, so it must run only once ranks are committed.
 */
class LeaderboardNotifier
{
    /**
     * The minimum number of places moved before a climb or drop is worth an email.
     */
    public const int MOVE_THRESHOLD = 2;

    public function notify(Game $game): void
    {
        $ranked = $game->entries()
            ->with('user')
            ->whereNotNull('rank')
            ->get()
            ->sortBy('rank')
            ->values();

        $byRank = $ranked->keyBy('rank');
        $totalEntries = $ranked->count();

        foreach ($ranked as $entry) {
            $user = $entry->user;

            // Skip entries with no recipient or no points yet — nothing meaningful to report.
            if ($user === null || $entry->total_points === null) {
                continue;
            }

            $rank = $entry->rank;
            $previousRank = $entry->previous_rank;

            // Newly top of the table (covers the first-ever leader, whose previous rank is null).
            if ($rank === 1 && $previousRank !== 1) {
                $runnerUp = $byRank->get(2);

                $user->notify(new TopOfLeaderboardNotification(
                    $game->name,
                    $game->slug,
                    $entry->total_points,
                    $totalEntries,
                    $runnerUp?->user?->name,
                    $this->gap($entry, $runnerUp),
                ));

                // The milestone replaces the generic climb email — never send both.
                continue;
            }

            // Significant climb or drop, relative to a known prior position.
            if ($previousRank !== null && abs($previousRank - $rank) >= self::MOVE_THRESHOLD) {
                $ahead = $byRank->get($rank - 1);

                $user->notify(new LeaderboardRankChangedNotification(
                    $game->name,
                    $game->slug,
                    $rank < $previousRank ? 'up' : 'down',
                    $rank,
                    $previousRank,
                    $totalEntries,
                    $entry->total_points,
                    $ahead?->user?->name,
                    $this->gap($ahead, $entry),
                ));
            }
        }
    }

    /**
     * The point difference between a higher- and lower-ranked entry, or null when it can't be computed.
     */
    private function gap(?Entry $higher, ?Entry $lower): ?int
    {
        if ($higher === null || $lower === null) {
            return null;
        }

        if ($higher->total_points === null || $lower->total_points === null) {
            return null;
        }

        return $higher->total_points - $lower->total_points;
    }
}
