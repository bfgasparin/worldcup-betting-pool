<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Pool;

/**
 * Builds the identity payload every pool screen leads with: the pool's own (verbatim) name is the
 * headline, with the source (who runs it), the tournament it is played over and the scoring style
 * as secondary context that tells sibling pools apart. Shared by the pool, prediction and
 * score-review controllers so all of them — and the sidebar, which reads the page-level `pool`
 * prop — expose the same identity.
 */
trait BuildsPoolIdentity
{
    /**
     * @return array{slug: string, name: string, source: string, tournament_name: string, accent: ?string, scoring_label: string}
     */
    protected function poolIdentity(Pool $pool): array
    {
        return [
            'slug' => $pool->slug,
            'name' => $pool->name,
            'source' => $pool->source,
            // The (canonical English) tournament name, shown as context beneath the pool name; the
            // frontend translates it at display time.
            'tournament_name' => $pool->tournament->name,
            'accent' => $pool->accent?->value,
            'scoring_label' => $pool->scoring_strategy->label(),
        ];
    }
}
