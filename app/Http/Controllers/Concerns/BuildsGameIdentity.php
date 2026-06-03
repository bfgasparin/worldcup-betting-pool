<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Game;

/**
 * Builds the identity payload that lets a game be told apart from its siblings — games played
 * over the *same* tournament share a name, dates and sport, so every game screen leads with the
 * source (who runs the pool), its accent colour and the scoring style. Shared by the game,
 * prediction and score-review controllers so all of them — and the sidebar, which reads the
 * page-level `game` prop — expose the same identity.
 */
trait BuildsGameIdentity
{
    /**
     * @return array{slug: string, name: string, source: string, accent: ?string, scoring_label: string}
     */
    protected function gameIdentity(Game $game): array
    {
        return [
            'slug' => $game->slug,
            'name' => $game->name,
            'source' => $game->source,
            'accent' => $game->accent?->value,
            'scoring_label' => $game->scoring_strategy->label(),
        ];
    }
}
