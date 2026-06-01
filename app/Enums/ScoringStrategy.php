<?php

namespace App\Enums;

enum ScoringStrategy: string
{
    case WorldCupStandard = 'world-cup-standard';

    /**
     * A short, human-readable name for the strategy, shown on the game-selection card.
     */
    public function label(): string
    {
        return match ($this) {
            self::WorldCupStandard => 'World Cup Standard',
        };
    }

    /**
     * A one-line explanation of how the strategy scores, so players understand the game
     * before they enter it.
     */
    public function description(): string
    {
        return match ($this) {
            self::WorldCupStandard => 'Predict every group scoreline and ride your bracket through the knockouts — exact scores and deep runs score the most.',
        };
    }
}
