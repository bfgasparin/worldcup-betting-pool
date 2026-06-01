<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Match duration (minutes)
    |--------------------------------------------------------------------------
    |
    | How long after kickoff a fixture is considered finished and therefore
    | eligible for an official score (see App\Models\Fixture::hasEnded). A
    | generous default covers 90 minutes + stoppage, possible extra time and a
    | penalty shootout, so a result is only ever entered once the match is
    | truly over — whether by an admin or the scheduled fetch.
    |
    */

    'match_duration_minutes' => (int) env('SCORING_MATCH_DURATION_MINUTES', 150),

    /*
    |--------------------------------------------------------------------------
    | Simulated score provider
    |--------------------------------------------------------------------------
    |
    | When enabled, the scores:fetch command proposes deterministic, plausible
    | results for finished fixtures via App\Services\Scoring\Providers\
    | SimulatedScoreProvider — a local-only stand-in for a real results API so
    | the end-to-end proposal flow can be exercised. Leave disabled in
    | production, where ManualScoreProvider is used (admins enter every score).
    |
    */

    'simulated_provider' => (bool) env('SCORING_SIMULATED_PROVIDER', false),

];
