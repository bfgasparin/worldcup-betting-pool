<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Pool;

/**
 * Builds the identity payload that lets a pool be told apart from its siblings — pools played
 * over the *same* tournament share a name, dates and sport, so every pool screen leads with the
 * source (who runs the pool), its accent colour and the scoring style. Shared by the pool,
 * prediction and score-review controllers so all of them — and the sidebar, which reads the
 * page-level `pool` prop — expose the same identity.
 */
trait BuildsPoolIdentity
{
    /**
     * @return array{slug: string, name: string, source: string, accent: ?string, scoring_label: string}
     */
    protected function poolIdentity(Pool $pool): array
    {
        return [
            'slug' => $pool->slug,
            'name' => $pool->name,
            'source' => $pool->source,
            'accent' => $pool->accent?->value,
            'scoring_label' => $pool->scoring_strategy->label(),
        ];
    }
}
