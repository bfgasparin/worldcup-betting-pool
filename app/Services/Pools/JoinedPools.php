<?php

namespace App\Services\Pools;

use App\Models\Pool;
use App\Models\User;

/**
 * Builds the "Your pools" list shared with the sidebar on every request: the pools a user has
 * joined, each flagged when it wants the player's attention — an open prediction window with
 * unfinished work, decided by {@see PredictionAttention}. Guests get an empty list without touching
 * the database, so the always-shared prop stays free on public pages.
 */
class JoinedPools
{
    public function __construct(
        private readonly PredictionAttention $attention = new PredictionAttention,
    ) {}

    /**
     * @return list<array{slug: string, name: string, source: string, accent: ?string, needs_attention: bool}>
     */
    public function forUser(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return Pool::query()
            ->whereHas('entries', fn ($query) => $query->where('user_id', $user->id))
            // The structure counts and the viewer's own entry (with its group-prediction count) are
            // folded into the list load so attention can be derived without an N+1 over entries.
            ->with([
                'tournament' => fn ($query) => $query->withCount('groupFixtures'),
                'entries' => fn ($query) => $query->where('user_id', $user->id)->withCount('groupPredictions'),
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (Pool $pool): array => [
                'slug' => $pool->slug,
                'name' => $pool->name,
                'source' => $pool->source,
                'accent' => $pool->accent?->value,
                'needs_attention' => $this->attention->needsAttention($pool, $pool->entries->first()),
            ])
            ->all();
    }
}
