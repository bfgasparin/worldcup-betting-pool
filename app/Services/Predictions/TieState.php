<?php

namespace App\Services\Predictions;

/**
 * A snapshot of the unresolved ties in one set of standings (a player's predicted bracket or a
 * tournament's official results): the still-level within-group clusters and the third-placed
 * teams whose tie straddles the qualifying cut. Produced by {@see TieResolutionState}.
 */
class TieState
{
    /**
     * @param  array<string, GroupStandings>  $standings  keyed by group name (for presenting rows)
     * @param  array<string, list<list<int>>>  $groupTies  group name => tied team-id clusters (effective order), always present while tied so the UI can keep offering them for ordering
     * @param  list<int>  $thirds  the straddling third-placed team ids (effective order), or empty
     * @param  bool  $thirdsResolved  whether a matching ordering already resolves the thirds cut
     * @param  bool  $groupsResolved  whether every group tie has a matching ordering
     */
    public function __construct(
        public readonly array $standings,
        public readonly array $groupTies,
        public readonly array $thirds,
        public readonly bool $thirdsResolved,
        public readonly bool $groupsResolved,
    ) {}

    /**
     * Whether any tie still needs a human to order it (so downstream cannot be filled/approved).
     */
    public function blocked(): bool
    {
        return ! $this->groupsResolved || ($this->thirds !== [] && ! $this->thirdsResolved);
    }

    /**
     * The unresolved within-group tied cluster for a group that exactly matches the given team set,
     * or null when none matches (used to validate a submitted ordering against the live tie).
     *
     * @param  list<int>  $teamIds
     * @return list<int>|null
     */
    public function matchingGroupCluster(string $groupName, array $teamIds): ?array
    {
        $wanted = $teamIds;
        sort($wanted);

        foreach ($this->groupTies[$groupName] ?? [] as $cluster) {
            $candidate = $cluster;
            sort($candidate);

            if ($candidate === $wanted) {
                return $cluster;
            }
        }

        return null;
    }
}
