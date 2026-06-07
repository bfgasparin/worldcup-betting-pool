<?php

namespace App\Services\Predictions;

use App\Enums\OrderingScope;
use App\Models\Entry;
use App\Models\EntryGroupOrdering;
use App\Models\Tournament;
use App\Models\TournamentGroupOrdering;
use Illuminate\Support\Collection;

/**
 * Read-model gathering the manual tie orderings (per group, plus the cross-group thirds cut) for
 * either a player's entry or a tournament's official results, so the resolvers can thread them
 * into the ranking engine. The engine re-validates each ordering against the current tie by set
 * equality, so a stale ordering carried here is harmless.
 */
class ManualTieOrdering
{
    /**
     * @param  array<string, list<int>>  $withinGroup  group name => ordered team ids
     * @param  list<int>|null  $thirds  ordered team ids for the straddling thirds cut
     */
    public function __construct(
        private readonly array $withinGroup = [],
        public readonly ?array $thirds = null,
    ) {}

    public static function fromEntry(Entry $entry): self
    {
        // Orderings are mutable (a save mid-request can leave an in-memory relation stale), so
        // always reload them fresh — the same policy BracketResolver uses for predictions.
        $entry->load(['groupOrderings.group']);

        return self::fromRows($entry->groupOrderings);
    }

    public static function fromTournament(Tournament $tournament): self
    {
        $tournament->load(['groupOrderings.group']);

        return self::fromRows($tournament->groupOrderings);
    }

    /**
     * The player's/admin's chosen order for a group's tie, or an empty list when none is recorded.
     *
     * @return list<int>
     */
    public function forGroup(string $name): array
    {
        return $this->withinGroup[$name] ?? [];
    }

    /**
     * Merge a just-confirmed tie's order into a group's existing per-group ordering, replacing only
     * this tie's own teams and preserving every other already-ordered cluster in the group — so
     * confirming one tie never drops another. A group's single row holds every cluster's order as
     * one flat list (the engine filters it per cluster), mirroring the union {@see DefaultTieOrdering}
     * writes for a multi-cluster group.
     *
     * @param  list<int>  $existing  the group's current ordered team ids (across all its clusters)
     * @param  list<int>  $cluster  the confirmed tie's ordered team ids
     * @return list<int>
     */
    public static function merge(array $existing, array $cluster): array
    {
        $preserved = array_values(array_filter(
            $existing,
            fn (int $id): bool => ! in_array($id, $cluster, true),
        ));

        return [...$preserved, ...$cluster];
    }

    /**
     * @param  Collection<int, EntryGroupOrdering|TournamentGroupOrdering>  $rows
     */
    private static function fromRows(Collection $rows): self
    {
        $withinGroup = [];
        $thirds = null;

        foreach ($rows as $row) {
            if ($row->scope === OrderingScope::Thirds) {
                $thirds = $row->ordered_team_ids;

                continue;
            }

            $groupName = $row->group?->name;

            if ($groupName !== null) {
                $withinGroup[$groupName] = $row->ordered_team_ids;
            }
        }

        return new self($withinGroup, $thirds);
    }
}
