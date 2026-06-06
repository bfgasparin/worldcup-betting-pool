<?php

namespace App\Services\Pools;

use Illuminate\Contracts\Support\Arrayable;

/**
 * What outstanding prediction work a player has in a pool's currently-open windows: the boolean the
 * sidebar shows as a dot, plus the per-window breakdown the pool page lists in its reminder banner.
 * Produced by {@see PredictionAttention} and shared with the frontend verbatim.
 *
 * @implements Arrayable<string, mixed>
 */
class AttentionSummary implements Arrayable
{
    /**
     * @param  list<array{phase_key: string, label: string, deadline: ?string, missing_count: int, total_count: int, has_unresolved_ties: bool}>  $openWindows
     */
    public function __construct(
        public readonly bool $needsAttention,
        public readonly array $openWindows = [],
    ) {}

    /**
     * @return array{needs_attention: bool, open_windows: list<array{phase_key: string, label: string, deadline: ?string, missing_count: int, total_count: int, has_unresolved_ties: bool}>}
     */
    public function toArray(): array
    {
        return [
            'needs_attention' => $this->needsAttention,
            'open_windows' => $this->openWindows,
        ];
    }
}
