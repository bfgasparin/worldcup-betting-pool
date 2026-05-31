<?php

namespace App\Services\Predictions;

use App\Models\Fixture;
use App\Models\Group;
use App\Models\GroupPrediction;
use App\Models\Team;

/**
 * Computes the ranked standings for one group from a set of group scores — either a user's
 * predicted scores (the prediction wizard) or the official, already-played results (the
 * tournament group page). Only fixtures present in the score map are counted.
 *
 * Ranking follows the official FIFA World Cup 2026 group tie-breaking order, made fully
 * deterministic (no fair-play score, FIFA ranking or drawing of lots): points -> head-to-head
 * among the level teams (points -> goal difference -> goals for, re-applied to any still-level
 * subset) -> overall goal difference -> overall goals for -> group seed position (the
 * group_team pivot position, which is unique within a group and so guarantees a total order).
 */
class GroupStandings
{
    /**
     * @var array<int, TeamStanding> keyed by team id
     */
    private array $standings = [];

    private bool $complete;

    /**
     * @param  array<int, GroupPrediction>  $predictionsByFixtureId
     */
    public function __construct(
        private readonly Group $group,
        private readonly array $predictionsByFixtureId,
    ) {
        $this->build();
    }

    private function build(): void
    {
        foreach ($this->group->teams as $team) {
            $this->standings[$team->id] = new TeamStanding($team->id, (int) $team->pivot->position);
        }

        foreach ($this->group->fixtures as $fixture) {
            $prediction = $this->predictionsByFixtureId[$fixture->id] ?? null;

            if ($prediction === null) {
                continue;
            }

            $home = $this->standings[$fixture->home_team_id] ?? null;
            $away = $this->standings[$fixture->away_team_id] ?? null;

            if ($home === null || $away === null) {
                continue;
            }

            $home->record($prediction->home_goals, $prediction->away_goals);
            $away->record($prediction->away_goals, $prediction->home_goals);
        }

        $this->complete = $this->group->fixtures->isNotEmpty()
            && $this->group->fixtures->every(fn (Fixture $fixture): bool => isset($this->predictionsByFixtureId[$fixture->id]));
    }

    /**
     * The teams ordered from 1st to last. Always available (even for an incomplete
     * group) so the wizard can show provisional standings as scores are entered.
     *
     * @return list<TeamStanding>
     */
    public function ordered(): array
    {
        $teams = array_values($this->standings);

        // Overall points come first; everything else only separates teams level on points.
        usort($teams, fn (TeamStanding $a, TeamStanding $b): int => $b->points() <=> $a->points());

        $result = [];
        $count = count($teams);
        $index = 0;

        while ($index < $count) {
            $end = $index;

            while ($end + 1 < $count && $teams[$end]->points() === $teams[$end + 1]->points()) {
                $end++;
            }

            $cluster = array_slice($teams, $index, $end - $index + 1);

            foreach ($this->rankTiedOnPoints($cluster) as $standing) {
                $result[] = $standing;
            }

            $index = $end + 1;
        }

        return $result;
    }

    public function isComplete(): bool
    {
        return $this->complete;
    }

    /**
     * The 1st-placed team id, or null when the group is not fully predicted yet.
     */
    public function winner(): ?int
    {
        return $this->teamIdAtRank(0);
    }

    public function runnerUp(): ?int
    {
        return $this->teamIdAtRank(1);
    }

    /**
     * The 3rd-placed team's full record, used to rank thirds across groups.
     */
    public function thirdStanding(): ?TeamStanding
    {
        if (! $this->complete) {
            return null;
        }

        return $this->ordered()[2] ?? null;
    }

    private function teamIdAtRank(int $rank): ?int
    {
        if (! $this->complete) {
            return null;
        }

        return $this->ordered()[$rank]->teamId ?? null;
    }

    /**
     * Order a set of teams level on points. Head-to-head results among them (points -> GD ->
     * GF) come first and are re-applied to any subset that stays level; teams that head-to-head
     * cannot separate at all fall through to overall GD -> GF -> seed position.
     *
     * @param  list<TeamStanding>  $cluster
     * @return list<TeamStanding>
     */
    private function rankTiedOnPoints(array $cluster): array
    {
        if (count($cluster) <= 1) {
            return $cluster;
        }

        $mini = $this->headToHeadTable($cluster);

        usort($cluster, function (TeamStanding $a, TeamStanding $b) use ($mini): int {
            return ($mini[$b->teamId]->points() <=> $mini[$a->teamId]->points())
                ?: ($mini[$b->teamId]->goalDifference() <=> $mini[$a->teamId]->goalDifference())
                ?: ($mini[$b->teamId]->goalsFor <=> $mini[$a->teamId]->goalsFor);
        });

        $result = [];
        $count = count($cluster);
        $index = 0;

        while ($index < $count) {
            $end = $index;

            while ($end + 1 < $count && $this->sameHeadToHead($mini, $cluster[$end], $cluster[$end + 1])) {
                $end++;
            }

            $subset = array_slice($cluster, $index, $end - $index + 1);

            // Head-to-head separated nobody in this cluster -> overall GD/GF/seed. Otherwise
            // re-apply head-to-head to the still-level subset (recomputed among its members).
            $ranked = count($subset) === $count
                ? $this->rankByOverall($subset)
                : $this->rankTiedOnPoints($subset);

            $result = array_merge($result, $ranked);

            $index = $end + 1;
        }

        return $result;
    }

    /**
     * Build a head-to-head mini-table from only the matches played between the given teams.
     *
     * @param  list<TeamStanding>  $cluster
     * @return array<int, TeamStanding>
     */
    private function headToHeadTable(array $cluster): array
    {
        $ids = array_map(fn (TeamStanding $standing): int => $standing->teamId, $cluster);

        $mini = [];
        foreach ($cluster as $standing) {
            $mini[$standing->teamId] = new TeamStanding($standing->teamId, $standing->position);
        }

        foreach ($this->group->fixtures as $fixture) {
            if (! in_array($fixture->home_team_id, $ids, true) || ! in_array($fixture->away_team_id, $ids, true)) {
                continue;
            }

            $prediction = $this->predictionsByFixtureId[$fixture->id] ?? null;

            if ($prediction === null) {
                continue;
            }

            $mini[$fixture->home_team_id]->record($prediction->home_goals, $prediction->away_goals);
            $mini[$fixture->away_team_id]->record($prediction->away_goals, $prediction->home_goals);
        }

        return $mini;
    }

    /**
     * Whether two teams are level on every head-to-head metric (points -> GD -> GF).
     *
     * @param  array<int, TeamStanding>  $mini
     */
    private function sameHeadToHead(array $mini, TeamStanding $a, TeamStanding $b): bool
    {
        return $mini[$a->teamId]->points() === $mini[$b->teamId]->points()
            && $mini[$a->teamId]->goalDifference() === $mini[$b->teamId]->goalDifference()
            && $mini[$a->teamId]->goalsFor === $mini[$b->teamId]->goalsFor;
    }

    /**
     * Order teams that head-to-head cannot separate: overall GD -> overall GF -> seed position.
     *
     * @param  list<TeamStanding>  $subset
     * @return list<TeamStanding>
     */
    private function rankByOverall(array $subset): array
    {
        usort($subset, function (TeamStanding $a, TeamStanding $b): int {
            return ($b->goalDifference() <=> $a->goalDifference())
                ?: ($b->goalsFor <=> $a->goalsFor)
                ?: ($a->position <=> $b->position);
        });

        return $subset;
    }

    /**
     * Resolve a team id to its loaded Team model on the group (for building UI rows).
     */
    public function team(int $teamId): ?Team
    {
        return $this->group->teams->firstWhere('id', $teamId);
    }
}
