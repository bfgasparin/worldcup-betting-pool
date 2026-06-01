<?php

namespace App\Services\Scoring;

use App\Models\Game;

/**
 * A typed reader over a game's `scoring_config` JSON, so the scorers never reach into raw
 * arrays. Missing keys default to 0, which keeps a partially-configured game from
 * throwing during scoring.
 */
class ScoringConfig
{
    /**
     * @param  array<string, array<string, int>>  $config
     */
    public function __construct(private readonly array $config) {}

    public static function fromGame(Game $game): self
    {
        return new self($game->scoring_config ?? []);
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
}
