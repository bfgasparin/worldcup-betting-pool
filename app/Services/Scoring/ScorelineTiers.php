<?php

namespace App\Services\Scoring;

/**
 * The four scoreline-comparison point tiers, in descending generosity. Reused for group
 * matches today and (in a future, between-phases tournament) for knockout matches that are
 * scored the same way as the group stage.
 */
final class ScorelineTiers
{
    public function __construct(
        public readonly int $exactScore,
        public readonly int $correctOutcomeAndOneTeamExact,
        public readonly int $correctOutcome,
        public readonly int $oneTeamExact,
    ) {}
}
