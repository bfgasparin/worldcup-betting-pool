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
 * Ranking follows the official FIFA World Cup 2026 group tie-breaking order: points ->
 * head-to-head among the level teams (points -> goal difference -> goals for, re-applied to any
 * still-level subset) -> overall goal difference -> overall goals for. Where FIFA would break a
 * remaining tie by a drawing of lots, this engine does NOT silently fall back to seed position;
 * instead the still-level teams form an "unresolved tie" that a human must order by hand. An
 * optional $manualOrder (an ordered list of team ids, matched per tied cluster by set equality)
 * supplies that human ordering; the seed position is used only as the default display order of
 * the rows offered for dragging, never as an automatic resolver.
 */
class GroupStandings
{
    /**
     * @var array<int, TeamStanding> keyed by team id
     */
    private array $standings = [];

    private bool $complete;

    /**
     * Tied clusters (each a list of team ids) the engine could not separate and for which no
     * matching manual ordering was supplied. Populated as a side effect of {@see ordered()}.
     *
     * @var list<list<int>>
     */
    private array $unresolved = [];

    /**
     * Every truly-tied cluster (size > 1, level on all criteria), in its current effective order
     * (a matching manual ordering applied, else seed order) — whether or not it is resolved, so the
     * UI can keep offering it for (re-)ordering. Populated as a side effect of {@see ordered()}.
     *
     * @var list<list<int>>
     */
    private array $tiedClusters = [];

    /**
     * @var list<TeamStanding>|null memoized result of {@see ordered()}
     */
    private ?array $orderedCache = null;

    /**
     * @param  array<int, GroupPrediction>  $predictionsByFixtureId
     * @param  list<int>  $manualOrder  human-supplied team order, matched per tied cluster by set
     */
    public function __construct(
        private readonly Group $group,
        private readonly array $predictionsByFixtureId,
        private readonly array $manualOrder = [],
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
        if ($this->orderedCache !== null) {
            return $this->orderedCache;
        }

        // Ranking mutates $unresolved/$tiedClusters as a side effect, so reset and compute once.
        $this->unresolved = [];
        $this->tiedClusters = [];

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

        return $this->orderedCache = $result;
    }

    /**
     * The tied clusters (each a list of team ids, in default seed order) the engine could not
     * separate and that have no matching manual ordering. Empty when the group is fully ranked.
     *
     * @return list<list<int>>
     */
    public function unresolvedTies(): array
    {
        $this->ordered();

        return $this->unresolved;
    }

    public function hasUnresolvedTies(): bool
    {
        return $this->unresolvedTies() !== [];
    }

    /**
     * Every truly-tied cluster in its current effective order, resolved or not — the surface the
     * UI offers for ordering (so a player can keep adjusting a tie they have already ordered).
     *
     * @return list<list<int>>
     */
    public function tiedClusters(): array
    {
        $this->ordered();

        return $this->tiedClusters;
    }

    /**
     * Each tied cluster paired with whether a matching manual ordering currently resolves it, so
     * the UI can confirm to the player that their choice was read (resolved) versus still pending.
     *
     * @return list<array{teamIds: list<int>, resolved: bool}>
     */
    public function tieClustersWithStatus(): array
    {
        $this->ordered();

        $unresolvedSets = array_map(fn (array $set): array => $this->sortedSet($set), $this->unresolved);

        return array_map(fn (array $cluster): array => [
            'teamIds' => $cluster,
            'resolved' => ! in_array($this->sortedSet($cluster), $unresolvedSets, true),
        ], $this->tiedClusters);
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function sortedSet(array $ids): array
    {
        sort($ids);

        return $ids;
    }

    public function isComplete(): bool
    {
        return $this->complete;
    }

    /**
     * The 1st-placed team id, or null when the group is not fully predicted yet, or when that
     * rank sits inside an unresolved tie that still needs a manual ordering.
     */
    public function winner(): ?int
    {
        return $this->resolvedTeamIdAtRank(0);
    }

    public function runnerUp(): ?int
    {
        return $this->resolvedTeamIdAtRank(1);
    }

    /**
     * The 3rd-placed team's full record, used to rank thirds across groups. Null when the group
     * is incomplete or 3rd place is inside an unresolved tie.
     */
    public function thirdStanding(): ?TeamStanding
    {
        if (! $this->complete || $this->isRankUnresolved(2)) {
            return null;
        }

        return $this->ordered()[2] ?? null;
    }

    private function resolvedTeamIdAtRank(int $rank): ?int
    {
        if (! $this->complete || $this->isRankUnresolved($rank)) {
            return null;
        }

        return $this->ordered()[$rank]->teamId ?? null;
    }

    /**
     * Whether the team occupying the given rank belongs to an unresolved tied cluster.
     */
    private function isRankUnresolved(int $rank): bool
    {
        $teamId = $this->ordered()[$rank]->teamId ?? null;

        if ($teamId === null) {
            return false;
        }

        foreach ($this->unresolved as $set) {
            if (in_array($teamId, $set, true)) {
                return true;
            }
        }

        return false;
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
     * Order teams that head-to-head cannot separate: overall GD -> overall GF. Teams still level
     * on both form a truly-unresolvable cluster — resolved by a matching manual ordering if one
     * was supplied, otherwise left in seed order and recorded as an unresolved tie.
     *
     * @param  list<TeamStanding>  $subset
     * @return list<TeamStanding>
     */
    private function rankByOverall(array $subset): array
    {
        usort($subset, fn (TeamStanding $a, TeamStanding $b): int => ($b->goalDifference() <=> $a->goalDifference())
            ?: ($b->goalsFor <=> $a->goalsFor));

        $result = [];

        foreach ($this->clustersEqualOnOverall($subset) as $run) {
            if (count($run) === 1) {
                $result[] = $run[0];

                continue;
            }

            $result = array_merge($result, $this->applyManualOrDefer($run));
        }

        return $result;
    }

    /**
     * Group a GD/GF-sorted subset into runs that are equal on both overall goal difference and
     * overall goals for. A run of more than one team is truly unresolvable by the FIFA criteria.
     *
     * @param  list<TeamStanding>  $sorted
     * @return list<list<TeamStanding>>
     */
    private function clustersEqualOnOverall(array $sorted): array
    {
        $clusters = [];
        $current = [];

        foreach ($sorted as $standing) {
            if ($current === [] || $this->equalOnOverall($current[0], $standing)) {
                $current[] = $standing;

                continue;
            }

            $clusters[] = $current;
            $current = [$standing];
        }

        if ($current !== []) {
            $clusters[] = $current;
        }

        return $clusters;
    }

    private function equalOnOverall(TeamStanding $a, TeamStanding $b): bool
    {
        return $a->goalDifference() === $b->goalDifference()
            && $a->goalsFor === $b->goalsFor;
    }

    /**
     * Resolve a truly-tied cluster with the manual ordering when it is a permutation of exactly
     * the tied set; otherwise leave it in seed order and record it as an unresolved tie.
     *
     * @param  list<TeamStanding>  $run
     * @return list<TeamStanding>
     */
    private function applyManualOrDefer(array $run): array
    {
        $ids = array_map(fn (TeamStanding $standing): int => $standing->teamId, $run);

        $chosen = array_values(array_filter(
            $this->manualOrder,
            fn (int $id): bool => in_array($id, $ids, true),
        ));

        if (count($chosen) === count($ids) && count(array_unique($chosen)) === count($ids)) {
            $byId = [];
            foreach ($run as $standing) {
                $byId[$standing->teamId] = $standing;
            }

            $resolved = array_map(fn (int $id): TeamStanding => $byId[$id], $chosen);
            $this->tiedClusters[] = $chosen;

            return $resolved;
        }

        // No matching manual ordering: present the tied teams in seed order (a stable default for
        // the drag UI) and flag the cluster so winner()/runnerUp()/thirdStanding() defer to null.
        usort($run, fn (TeamStanding $a, TeamStanding $b): int => $a->position <=> $b->position);
        $clusterIds = array_map(fn (TeamStanding $standing): int => $standing->teamId, $run);
        $this->tiedClusters[] = $clusterIds;
        $this->unresolved[] = $clusterIds;

        return $run;
    }

    /**
     * Resolve a team id to its loaded Team model on the group (for building UI rows).
     */
    public function team(int $teamId): ?Team
    {
        return $this->group->teams->firstWhere('id', $teamId);
    }
}
