<?php

namespace App\Services\Scoring;

use App\Contracts\ScoreProvider;

/**
 * A fixture's current live scoreline as reported by a {@see ScoreProvider} feed —
 * the in-play counterpart to {@see ProposedScore} (which carries the final result). It carries the
 * fixture's match number so the live feed can map it back, and only regulation goals (penalties and
 * the winner belong to the final, settled through the proposal/approval pipeline).
 */
final class LiveScore
{
    public function __construct(
        public readonly int $matchNumber,
        public readonly int $homeGoals,
        public readonly int $awayGoals,
    ) {}
}
