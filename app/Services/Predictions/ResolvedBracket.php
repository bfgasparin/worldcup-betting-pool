<?php

namespace App\Services\Predictions;

/**
 * The result of resolving a user's entry: the standings for every group, the ranking of
 * the eight best third-placed teams, and the resolved home/away team id for every
 * knockout fixture (null where the prediction is not complete enough to know yet).
 */
class ResolvedBracket
{
    /**
     * @param  array<string, GroupStandings>  $standings  keyed by group name (A-L)
     * @param  list<int>|null  $rankedThirds  top-8 third-placed team ids in rank order, or null when groups are incomplete
     * @param  array<int, array{home: ?int, away: ?int}>  $resolved  keyed by knockout fixture id
     */
    public function __construct(
        public readonly array $standings,
        public readonly ?array $rankedThirds,
        public readonly array $resolved,
    ) {}

    /**
     * @return array{home: ?int, away: ?int}
     */
    public function fixture(int $fixtureId): array
    {
        return $this->resolved[$fixtureId] ?? ['home' => null, 'away' => null];
    }
}
