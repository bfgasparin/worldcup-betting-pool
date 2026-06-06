<?php

namespace App\Services\Scoring;

/**
 * How a rank moved between two snapshots — shared by the live leaderboard ({@see ScoreEngine}-fed
 * standings) and the per-matchday reconstruction ({@see MatchdayLeaderboard}), so both describe
 * movement the same way the frontend's movement arrow expects.
 */
final class RankMovement
{
    /**
     * The direction a rank moved: `up`, `down`, `same`, or `new` (first appearance). Null until a
     * rank exists at all.
     */
    public static function direction(?int $rank, ?int $previousRank): ?string
    {
        if ($rank === null) {
            return null;
        }

        if ($previousRank === null) {
            return 'new';
        }

        return match (true) {
            $rank < $previousRank => 'up',
            $rank > $previousRank => 'down',
            default => 'same',
        };
    }

    /**
     * How many places a rank moved — always positive, or null when there is no comparable previous
     * rank (first appearance / before any snapshot).
     */
    public static function delta(?int $rank, ?int $previousRank): ?int
    {
        if ($rank === null || $previousRank === null) {
            return null;
        }

        return abs($rank - $previousRank) ?: null;
    }
}
