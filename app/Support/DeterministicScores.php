<?php

namespace App\Support;

/**
 * Reproducible, seedable pseudo-random scores. Used to fabricate plausible match results both
 * for the `tournament:simulate` command and the local {@see
 * \App\Services\Scoring\Providers\SimulatedScoreProvider}, so a given seed always produces the
 * same "world".
 */
final class DeterministicScores
{
    public function __construct(private readonly string $seed = '') {}

    /**
     * A deterministic value in [0, 1) derived from the seed and the given parts.
     */
    public function noise(int|string ...$parts): float
    {
        $hash = crc32($this->seed.':'.implode(':', $parts));

        return ($hash % 100000) / 100000;
    }

    /**
     * Goals (0–3) nudged by a group seed: a better-seeded team (lower position) skews higher.
     */
    public function biasedGoals(float $noise, int $position): int
    {
        $strength = (4 - $position) * 0.4; // 0 (4th seed) … 1.2 (top seed)

        return (int) max(0, min(3, round($noise * 3 + $strength - 0.6)));
    }
}
