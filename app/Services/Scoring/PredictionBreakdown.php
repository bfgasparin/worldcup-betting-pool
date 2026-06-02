<?php

namespace App\Services\Scoring;

/**
 * The full result of scoring one prediction (group or knockout), in the shape every leaderboard
 * rolls up. {@see points} is what the Overall board banks; the rest feed the per-category boards:
 *
 *  - {@see isCorrectOutcome}: the match result was right (group: the winner, or a correct draw;
 *    knockout: the team that actually advanced) — the Match Winners board.
 *  - {@see teamGoalsHit} (0–2): how many individual team goal counts were correct — the Goal
 *    Sniper board.
 */
final class PredictionBreakdown
{
    public function __construct(
        public readonly int $points,
        public readonly bool $isCorrectOutcome,
        public readonly int $teamGoalsHit,
    ) {}
}
