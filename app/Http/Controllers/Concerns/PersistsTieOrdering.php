<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\OrderingScope;
use App\Models\EntryGroupOrdering;
use App\Models\TournamentGroupOrdering;
use App\Services\Predictions\ManualTieOrdering;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Shared persistence for the player and admin manual tie-ordering endpoints. A within-group row
 * holds every tied cluster in the group as one flat ordered list, so the confirmed cluster is
 * MERGED into the existing order (replacing only its own slice) rather than overwriting it —
 * otherwise confirming one tie wipes another in the same group. The thirds cut is a single
 * cross-group cluster, so it is replaced outright. The read-modify-write is wrapped in a locked
 * transaction so two rapid confirms can't each miss the other's update.
 */
trait PersistsTieOrdering
{
    /**
     * @param  callable(): HasMany<EntryGroupOrdering, *>|callable(): HasMany<TournamentGroupOrdering, *>  $orderings  a fresh ordering relation each call
     * @param  list<int>  $cluster  the just-confirmed tie, in chosen order
     */
    protected function persistTieOrdering(callable $orderings, OrderingScope $scope, ?int $groupId, array $cluster): void
    {
        DB::transaction(function () use ($orderings, $scope, $groupId, $cluster): void {
            $existing = $orderings()
                ->where('group_id', $groupId)
                ->where('scope', $scope)
                ->lockForUpdate()
                ->first();

            $ordered = $scope === OrderingScope::WithinGroup
                ? ManualTieOrdering::merge($existing?->ordered_team_ids ?? [], $cluster)
                : $cluster;

            $tied = $ordered;
            sort($tied);

            $orderings()->updateOrCreate(
                ['group_id' => $groupId, 'scope' => $scope],
                ['tied_team_ids' => $tied, 'ordered_team_ids' => $ordered],
            );
        });
    }
}
