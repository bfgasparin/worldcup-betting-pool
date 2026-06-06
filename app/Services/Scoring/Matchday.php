<?php

namespace App\Services\Scoring;

/**
 * One travel-stop on the leaderboard's timeline: a group-stage round (Matchday 1/2/3) or a single
 * knockout phase (Round of 32 … Final). It names the fixtures that settle that matchday, so the
 * leaderboard can reconstruct how each board looked at the matchday's end and what each player
 * earned within it. Derived structure only — never persisted (see {@see MatchdayCatalog}).
 */
final class Matchday
{
    /**
     * @param  'group'|'knockout'  $kind
     * @param  list<int>  $fixtureIds
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $shortLabel,
        public readonly string $kind,
        public readonly array $fixtureIds,
    ) {}
}
