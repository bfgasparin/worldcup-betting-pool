<?php

namespace App\Services\Scoring\Providers;

use App\Contracts\ScoreProvider;
use App\Models\Tournament;

/**
 * The default, no-op provider: it never proposes scores, so admins enter every result by hand in
 * the review screen. It exists so the fetch command and the binding are in place; a real
 * results-API provider can be bound over it once the tournament is under way.
 */
class ManualScoreProvider implements ScoreProvider
{
    public function fetch(Tournament $tournament): iterable
    {
        return [];
    }
}
