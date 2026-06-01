<?php

namespace App\Enums;

enum ScoringStrategy: string
{
    case UpfrontBracket = 'upfront-bracket';

    /**
     * A short, human-readable name for the strategy, shown on the game-selection card.
     */
    public function label(): string
    {
        return match ($this) {
            self::UpfrontBracket => 'Upfront Bracket',
        };
    }

    /**
     * A one-line explanation of how the strategy scores, so players understand the game
     * before they enter it.
     */
    public function description(): string
    {
        return match ($this) {
            self::UpfrontBracket => 'Predict every group scoreline and ride your bracket through the knockouts — exact scores score big, and the deeper your teams run the more they bank, capped by a bonus for the champion.',
        };
    }
}
