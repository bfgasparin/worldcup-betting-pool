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
