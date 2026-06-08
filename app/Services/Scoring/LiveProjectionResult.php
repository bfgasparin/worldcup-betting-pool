<?php

namespace App\Services\Scoring;

/**
 * The result of a {@see LiveProjection}: the projected leaderboards (one per board key) computed
 * from current live scores, plus the live version they were computed at (the cache key component).
 * Plain, serialisable data — safe to cache and hand straight to Inertia.
 */
final class LiveProjectionResult
{
    /**
     * @param  array<string, list<array<string, mixed>>>  $boards  board key => ranked projected rows
     */
    public function __construct(
        public readonly array $boards,
        public readonly string $version,
    ) {}
}
