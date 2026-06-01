<?php

namespace App\Services\Scoring;

use App\Contracts\ScoreProvider;

/**
 * A finished-match score offered by a {@see ScoreProvider}, identified by its
 * fixture match number (external sources key on the match number / date, not our fixture ids).
 */
final class ProposedScore
{
    public function __construct(
        public readonly int $matchNumber,
        public readonly int $homeGoals,
        public readonly int $awayGoals,
        public readonly ?int $winnerTeamId = null,
        public readonly ?int $homePenalties = null,
        public readonly ?int $awayPenalties = null,
    ) {}
}
