<?php

namespace App\Services\Live;

use App\Models\Fixture;
use App\Models\User;

/**
 * Whether a user has any live match to follow right now — a fixture currently live in a tournament
 * they've joined a pool in. Shared on every request to drive the navigation's animated "Live"
 * indicator. Guests pay nothing (a single cheap exists() at most), mirroring {@see JoinedPools}.
 */
class HasLiveMatches
{
    public function forUser(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return Fixture::query()
            ->hasLiveState()
            ->whereHas('tournament.pools.entries', fn ($query) => $query->where('user_id', $user->id))
            ->exists();
    }
}
