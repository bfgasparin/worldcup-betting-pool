<?php

namespace App\Services\Pools;

use App\Models\GroupPrediction;
use App\Models\Pool;
use App\Models\User;

/**
 * Builds the "Your pools" list shared with the sidebar on every request: the pools a user has
 * joined, each flagged when it wants the player's attention — an open prediction window paired
 * with unfinished group-stage picks. Guests get an empty list without touching the database, so
 * the always-shared prop stays free on public pages.
 */
class JoinedPools
{
    /**
     * @return list<array{slug: string, name: string, source: string, accent: ?string, needs_attention: bool}>
     */
    public function forUser(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return Pool::query()
            ->select('pools.*')
            ->whereHas('entries', fn ($query) => $query->where('user_id', $user->id))
            ->with(['tournament' => fn ($query) => $query->withCount('groupFixtures')])
            // The viewer's own group-prediction count per pool, folded into the one list query so
            // attention can be derived without an N+1 over entries → predictions.
            ->addSelect(['my_group_predictions' => GroupPrediction::query()
                ->selectRaw('count(*)')
                ->join('entries', 'group_predictions.entry_id', '=', 'entries.id')
                ->whereColumn('entries.pool_id', 'pools.id')
                ->where('entries.user_id', $user->id),
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (Pool $pool): array => [
                'slug' => $pool->slug,
                'name' => $pool->name,
                'source' => $pool->source,
                'accent' => $pool->accent?->value,
                'needs_attention' => $pool->needsAttention(
                    (int) $pool->getAttribute('my_group_predictions'),
                    (int) $pool->tournament->getAttribute('group_fixtures_count'),
                ),
            ])
            ->all();
    }
}
