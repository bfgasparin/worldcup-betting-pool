<?php

namespace App\Services\Scoring;

use App\Enums\LeaderboardCategory;

/**
 * The fully-built leaderboard page for one selected matchday: the timeline of matchdays a player
 * can travel to, which one is selected, the three boards as they stood at that matchday's end (each
 * carrying its per-matchday cards), and how many players are on the board (for prize splitting).
 */
final class MatchdayLeaderboardView
{
    /**
     * @param  list<array<string, mixed>>  $matchdays  the timeline descriptors (key/label/status/…)
     * @param  list<array<string, mixed>>  $boards  one per {@see LeaderboardCategory}
     */
    public function __construct(
        public readonly array $matchdays,
        public readonly string $selectedKey,
        public readonly bool $selectedIsCurrent,
        public readonly array $boards,
        public readonly int $participants,
    ) {}
}
