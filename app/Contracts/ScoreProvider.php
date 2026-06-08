<?php

namespace App\Contracts;

use App\Models\Tournament;
use App\Services\Scoring\LiveScore;
use App\Services\Scoring\ProposedScore;
use App\Services\Scoring\Providers\ManualScoreProvider;

/**
 * A source of match scores for a tournament — both the live scorelines of in-play matches and the
 * finals of finished ones. The default {@see ManualScoreProvider}
 * returns nothing (admins drive the Live Center and enter every result by hand); a real results API
 * can be bound in its place later without touching the live feed, the fetch command, or the
 * review/approval flow.
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

    /**
     * The current live scorelines for in-play (kicked-off, not-yet-final) fixtures, each carrying
     * the fixture's match number. A live score reports regulation goals only and converges to the
     * eventual {@see fetch()} final; the default provider reports nothing.
     *
     * @return iterable<LiveScore>
     */
    public function live(Tournament $tournament): iterable;
}
