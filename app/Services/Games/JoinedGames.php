<?php

namespace App\Services\Games;

use App\Models\Game;
use App\Models\GroupPrediction;
use App\Models\User;

/**
 * Builds the "Your games" list shared with the sidebar on every request: the games a user has
 * joined, each flagged when it wants the player's attention — an open prediction window paired
 * with unfinished group-stage picks. Guests get an empty list without touching the database, so
 * the always-shared prop stays free on public pages.
 */
class JoinedGames
{
    /**
     * @return list<array{slug: string, name: string, source: string, accent: ?string, needs_attention: bool}>
     */
    public function forUser(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return Game::query()
            ->select('games.*')
            ->whereHas('entries', fn ($query) => $query->where('user_id', $user->id))
            ->with(['tournament' => fn ($query) => $query->withCount('groupFixtures')])
            // The viewer's own group-prediction count per game, folded into the one list query so
            // attention can be derived without an N+1 over entries → predictions.
            ->addSelect(['my_group_predictions' => GroupPrediction::query()
                ->selectRaw('count(*)')
                ->join('entries', 'group_predictions.entry_id', '=', 'entries.id')
                ->whereColumn('entries.game_id', 'games.id')
                ->where('entries.user_id', $user->id),
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (Game $game): array => [
                'slug' => $game->slug,
                'name' => $game->name,
                'source' => $game->source,
                'accent' => $game->accent?->value,
                'needs_attention' => $game->needsAttention(
                    (int) $game->getAttribute('my_group_predictions'),
                    (int) $game->tournament->getAttribute('group_fixtures_count'),
                ),
            ])
            ->all();
    }
}
