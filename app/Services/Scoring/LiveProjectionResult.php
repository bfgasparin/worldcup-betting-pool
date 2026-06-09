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
     * @param  array<int, list<array{entry_id: int, home_goals: ?int, away_goals: ?int, points: int, advancing_team_id: ?int, predicted_home: ?array<string, mixed>, predicted_away: ?array<string, mixed>}>>  $fixturePicks  live fixture id => every entry's pick + live points
     */
    public function __construct(
        public readonly array $boards,
        public readonly string $version,
        public readonly array $fixturePicks = [],
    ) {}
}
