<?php

namespace App\Contracts;

use App\Models\Tournament;
use App\Services\Scoring\ProposedScore;

/**
 * A source of official match scores for a tournament. The default {@see
 * \App\Services\Scoring\Providers\ManualScoreProvider} returns nothing (admins enter scores by
 * hand); a real results API can be bound in its place later without touching the fetch command
 * or the review/approval flow.
 */
interface ScoreProvider
{
    /**
     * The finished-match scores the provider currently knows about, keyed by nothing in
     * particular — each carries the fixture's match number so the command can map it back.
     *
     * @return iterable<ProposedScore>
     */
    public function fetch(Tournament $tournament): iterable;
}
