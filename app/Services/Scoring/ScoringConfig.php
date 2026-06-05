<?php

namespace App\Services\Scoring;

use App\Enums\PhaseKey;
use App\Models\Pool;

/**
 * A typed reader over a pool's `scoring_config` JSON, so the scorers never reach into raw
 * arrays. Missing keys default to 0, which keeps a partially-configured pool from
 * throwing during scoring.
 */
class ScoringConfig
{
    /**
     * @param  array<string, array<string, int>>  $config
     */
    public function __construct(private readonly array $config) {}

    public static function fromPool(Pool $pool): self
    {
        return new self($pool->scoring_config ?? []);
    }

    /**
     * The group-stage scoreline tiers (also reused for knockout scoreline scoring in a future
     * tournament strategy).
     */
    public function groupTiers(): ScorelineTiers
    {
        return new ScorelineTiers(
            $this->group('exact_score'),
            $this->group('winner_and_one_team_exact_goals'),
            $this->group('correct_outcome_wrong_goals'),
            $this->group('one_team_exact_goals_wrong_outcome'),
        );
    }

    /**
     * The knockout-stage scoreline tiers for a given round, scaled by that round's multiplier
     * (the phased-bracket strategy: knockouts are scored on the scoreline, exactly like the group
     * stage, but each round is worth progressively more). Rounds default to a ×1 multiplier.
     */
    public function knockoutTiers(PhaseKey $phase): ScorelineTiers
    {
        $multiplier = $this->knockoutMultiplier($phase);

        return new ScorelineTiers(
            $this->knockout('exact_score') * $multiplier,
            $this->knockout('winner_and_one_team_exact_goals') * $multiplier,
            $this->knockout('correct_outcome_wrong_goals') * $multiplier,
            $this->knockout('one_team_exact_goals_wrong_outcome') * $multiplier,
        );
    }

    /**
     * The flat bonus (phased bracket) for correctly calling who advances a knockout tie, on top
     * of the scoreline tier. Deliberately not round-scaled, so it stays a steady safety net
     * rather than compounding the late-round swing.
     */
    public function knockoutAdvancingBonus(): int
    {
        return $this->knockout('advancing_team');
    }

    public function knockoutCorrectTeam(): int
    {
        // The 10-point bonus per team correctly placed in a knockout match. This slot has been
        // keyed `exact_matchup`/`team_reaches_phase` before — fall back so already-seeded
        // tournaments keep scoring without a re-seed.
        $knockout = $this->config['knockout'] ?? [];

        return (int) ($knockout['correct_team'] ?? $knockout['exact_matchup'] ?? $knockout['team_reaches_phase'] ?? 0);
    }

    public function knockoutGoalCountBonus(): int
    {
        return $this->knockout('team_goal_count_bonus');
    }

    public function champion(): int
    {
        return $this->knockout('champion');
    }

    private function group(string $key): int
    {
        return (int) ($this->config['group'][$key] ?? 0);
    }

    private function knockout(string $key): int
    {
        return (int) ($this->config['knockout'][$key] ?? 0);
    }

    /**
     * The round-weight multiplier applied to a knockout round's scoreline tiers, defaulting to 1
     * for any round without an explicit entry in the `round_multipliers` map.
     */
    private function knockoutMultiplier(PhaseKey $phase): int
    {
        return (int) ($this->config['knockout']['round_multipliers'][$phase->value] ?? 1);
    }
}
