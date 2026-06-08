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
    | Prediction lock buffer (minutes)
    |--------------------------------------------------------------------------
    |
    | How long before a phase's first kickoff its prediction window closes when
    | the lock is derived from the schedule (see App\Models\Pool::predictionsLockAt
    | for the group stage and App\Services\Predictions\PredictionWindowResolver
    | for each phased knockout round). A pool with an explicit predictions_lock_at
    | override ignores this buffer — the override is taken verbatim.
    |
    */

    'prediction_lock_buffer_minutes' => (int) env('SCORING_PREDICTION_LOCK_BUFFER_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Go-live buffer (minutes)
    |--------------------------------------------------------------------------
    |
    | How early before kickoff an admin may mark a fixture live in the Live
    | Center (see App\Models\Fixture::canGoLive). Going live is admin-driven —
    | the system no longer flips a fixture to live automatically when its
    | kickoff passes — but a match still cannot be started long before kickoff.
    |
    */

    'go_live_buffer_minutes' => (int) env('SCORING_GO_LIVE_BUFFER_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Live Center poll interval (milliseconds)
    |--------------------------------------------------------------------------
    |
    | How often the player-facing Live Center re-fetches the live scores and
    | projected leaderboards while a match is in play. The server caches the
    | projection per live-state change, so all viewers share one computation.
    |
    */

    'live_poll_interval_ms' => (int) env('SCORING_LIVE_POLL_INTERVAL_MS', 10000),

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
