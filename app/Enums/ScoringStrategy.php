<?php

namespace App\Enums;

enum ScoringStrategy: string
{
    case UpfrontBracket = 'upfront-bracket';
    case PhasedBracket = 'phased-bracket';

    /**
     * A short, human-readable name for the strategy, shown on the pool-selection card.
     */
    public function label(): string
    {
        return match ($this) {
            self::UpfrontBracket => __('Upfront Bracket'),
            self::PhasedBracket => __('Phased Bracket'),
        };
    }

    /**
     * A one-line explanation of how the strategy scores, so players understand the pool
     * before they enter it.
     */
    public function description(): string
    {
        return match ($this) {
            self::UpfrontBracket => __('Predict every group scoreline and ride your bracket through the knockouts — exact scores score big, and the deeper your teams run the more they bank, capped by a bonus for the champion.'),
            self::PhasedBracket => __('Predict the group stage upfront, then call each knockout round once the real match-ups are set. Scores carry more weight the deeper the tournament runs, so a slow start is never the end and it stays a fight to the final whistle.'),
        };
    }

    /**
     * Plain-language guidance on how and when to fill in predictions for this strategy, shown
     * in the "How this pool works" dialog on the pool page.
     *
     * @return array{summary: string, steps: list<string>}
     */
    public function howToPlay(): array
    {
        return match ($this) {
            self::UpfrontBracket => [
                'summary' => __('Lock in your whole tournament before a ball is kicked.'),
                'steps' => [
                    __('Predict the exact scoreline of every group-stage match.'),
                    __('Your knockout bracket is built automatically from those scores — the teams you send through are the ones you ride all the way to the final.'),
                    __('Get every pick in before predictions lock. You can edit them as much as you like until then.'),
                    __('Once predictions lock your bracket is set, and points roll in as the real results land.'),
                ],
            ],
            self::PhasedBracket => [
                'summary' => __('Predict as the tournament unfolds — and the stakes climb every round.'),
                'steps' => [
                    __('Predict the exact scoreline of every group-stage match before the tournament kicks off.'),
                    __('As each knockout round is decided, a new window opens to predict the real match-ups — the Round of 32, then 16, and on to the Final.'),
                    __('Knockout scores are worth more every round (the Final is worth eight times a group match), so falling behind early is never the end.'),
                    __('Each round locks at its first kickoff — get your picks in while the window is open.'),
                ],
            ],
        };
    }
}
