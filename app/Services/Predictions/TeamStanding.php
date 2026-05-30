<?php

namespace App\Services\Predictions;

/**
 * Mutable accumulator for one team's record within a single group, built up by
 * applying the user's predicted scores fixture by fixture.
 */
class TeamStanding
{
    public int $won = 0;

    public int $drawn = 0;

    public int $lost = 0;

    public int $goalsFor = 0;

    public int $goalsAgainst = 0;

    public function __construct(
        public readonly int $teamId,
        public readonly int $position,
    ) {}

    /**
     * Apply one match result from this team's perspective.
     */
    public function record(int $goalsFor, int $goalsAgainst): void
    {
        $this->goalsFor += $goalsFor;
        $this->goalsAgainst += $goalsAgainst;

        if ($goalsFor > $goalsAgainst) {
            $this->won++;
        } elseif ($goalsFor === $goalsAgainst) {
            $this->drawn++;
        } else {
            $this->lost++;
        }
    }

    public function played(): int
    {
        return $this->won + $this->drawn + $this->lost;
    }

    public function points(): int
    {
        return $this->won * 3 + $this->drawn;
    }

    public function goalDifference(): int
    {
        return $this->goalsFor - $this->goalsAgainst;
    }
}
