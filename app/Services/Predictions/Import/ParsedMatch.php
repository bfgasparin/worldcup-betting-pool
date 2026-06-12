<?php

namespace App\Services\Predictions\Import;

use App\Models\Fixture;
use App\Models\Team;

/**
 * One match from a backfill JSON blob after it has been mapped against the tournament: the raw
 * team codes/goals as pasted, plus the resolved {@see Fixture} and {@see Team}
 * ids (null where a code or match number did not match anything). The knockout flag is taken from
 * the fixture's phase, not the presence of an "advances" key.
 */
final class ParsedMatch
{
    public function __construct(
        public readonly int $matchNumber,
        public readonly ?int $fixtureId,
        public readonly bool $isKnockout,
        public readonly ?string $homeCode,
        public readonly ?string $awayCode,
        public readonly ?int $homeTeamId,
        public readonly ?int $awayTeamId,
        public readonly ?int $homeGoals,
        public readonly ?int $awayGoals,
        public readonly ?string $advancesCode,
        public readonly ?int $advancesTeamId,
    ) {}

    public function isGroup(): bool
    {
        return ! $this->isKnockout;
    }

    public function hasBothGoals(): bool
    {
        return $this->homeGoals !== null && $this->awayGoals !== null;
    }
}
