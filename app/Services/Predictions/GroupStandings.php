<?php

namespace App\Services\Predictions;

use App\Models\Fixture;
use App\Models\Group;
use App\Models\GroupPrediction;
use App\Models\Team;

/**
 * Computes the ranked standings for one group from a user's predicted group scores.
 *
 * Ranking follows FIFA-style tie-breaking, made fully deterministic (no drawing of
 * lots): points -> goal difference -> goals for -> head-to-head mini-table among the
 * still-tied teams (points -> GD -> GF) -> group seed position (the group_team pivot
 * position, which is unique within a group and therefore guarantees a total order).
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

        usort($teams, fn (TeamStanding $a, TeamStanding $b): int => $this->compareOverall($a, $b));

        $result = [];
        $count = count($teams);
        $index = 0;

        while ($index < $count) {
            $end = $index;

            while ($end + 1 < $count && $this->compareOverall($teams[$end], $teams[$end + 1]) === 0) {
                $end++;
            }

            $cluster = array_slice($teams, $index, $end - $index + 1);

            if (count($cluster) > 1) {
                $cluster = $this->breakTiesByHeadToHead($cluster);
            }

            foreach ($cluster as $standing) {
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
     * Compare two teams by overall points, then goal difference, then goals for.
     * Returns 0 when all three are equal (i.e. they belong to the same tie cluster).
     */
    private function compareOverall(TeamStanding $a, TeamStanding $b): int
    {
        return ($b->points() <=> $a->points())
            ?: ($b->goalDifference() <=> $a->goalDifference())
            ?: ($b->goalsFor <=> $a->goalsFor);
    }

    /**
     * Re-order a cluster of teams tied on overall points/GD/GF using a head-to-head
     * mini-table built only from matches between the tied teams, then the seed position.
     *
     * @param  list<TeamStanding>  $cluster
     * @return list<TeamStanding>
     */
    private function breakTiesByHeadToHead(array $cluster): array
    {
        $ids = array_map(fn (TeamStanding $standing): int => $standing->teamId, $cluster);

        /** @var array<int, TeamStanding> $mini */
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

        usort($cluster, function (TeamStanding $a, TeamStanding $b) use ($mini): int {
            $miniA = $mini[$a->teamId];
            $miniB = $mini[$b->teamId];

            return ($miniB->points() <=> $miniA->points())
                ?: ($miniB->goalDifference() <=> $miniA->goalDifference())
                ?: ($miniB->goalsFor <=> $miniA->goalsFor)
                ?: ($a->position <=> $b->position);
        });

        return $cluster;
    }

    /**
     * Resolve a team id to its loaded Team model on the group (for building UI rows).
     */
    public function team(int $teamId): ?Team
    {
        return $this->group->teams->firstWhere('id', $teamId);
    }
}
