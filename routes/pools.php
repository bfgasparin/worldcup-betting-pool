<?php

use App\Http\Controllers\FixtureScheduleController;
use App\Http\Controllers\LiveControlController;
use App\Http\Controllers\LiveController;
use App\Http\Controllers\PoolController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\ScoreReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    // The Live Center: a landing that routes to whatever tournament is live, then a per-tournament
    // live view. Declare the literal `live` before the `{tournament:slug}` route.
    Route::get('live', [LiveController::class, 'index'])->name('live.index');
    Route::get('live/{tournament:slug}', [LiveController::class, 'show'])->name('live.show');

    Route::get('pools', [PoolController::class, 'index'])->name('pools.index');
    Route::get('pools/{pool:slug}', [PoolController::class, 'show'])->name('pools.show');
    Route::post('pools/{pool:slug}/join', [PoolController::class, 'join'])->name('pools.join');
    Route::post('pools/{pool:slug}/briefing-seen', [PoolController::class, 'markBriefingSeen'])->name('pools.briefing.seen');
    Route::get('pools/{pool:slug}/leaderboard', [PoolController::class, 'leaderboard'])->name('pools.leaderboard');
    Route::get('pools/{pool:slug}/predict', [PredictionController::class, 'edit'])->name('pools.predict.edit');
    Route::put('pools/{pool:slug}/predict/group', [PredictionController::class, 'updateGroupStage'])->name('pools.predict.group');
    Route::put('pools/{pool:slug}/predict/knockout', [PredictionController::class, 'updateKnockout'])->name('pools.predict.knockout');
    Route::put('pools/{pool:slug}/predict/ordering', [PredictionController::class, 'updateOrdering'])->name('pools.predict.ordering');
    Route::post('pools/{pool:slug}/predict/import', [PredictionController::class, 'import'])->name('pools.predict.import');

    // Admin-only score review & approval.
    Route::middleware('can:manage-tournament')->group(function () {
        Route::get('pools/{pool:slug}/scores', [ScoreReviewController::class, 'review'])->name('pools.scores.review');
        Route::patch('pools/{pool:slug}/scores/fixtures/{fixture}', [ScoreReviewController::class, 'updateProposal'])->name('pools.scores.proposal');
        Route::put('pools/{pool:slug}/scores/ordering', [ScoreReviewController::class, 'updateOrdering'])->name('pools.scores.ordering');
        Route::post('pools/{pool:slug}/scores/approve', [ScoreReviewController::class, 'approve'])->name('pools.scores.approve');

        // Admin-only fixture rescheduling (delays, venue moves).
        Route::get('pools/{pool:slug}/schedule', [FixtureScheduleController::class, 'index'])->name('pools.schedule.index');
        Route::patch('pools/{pool:slug}/fixtures/{fixture}/reschedule', [FixtureScheduleController::class, 'reschedule'])->name('pools.fixtures.reschedule');

        // Admin-only Live Center control: go live, keep the live score, end the match.
        Route::get('live/{tournament:slug}/control', [LiveControlController::class, 'index'])->name('live.control.index');
        Route::post('live/{tournament:slug}/fixtures/{fixture}/go-live', [LiveControlController::class, 'goLive'])->name('live.control.go-live');
        Route::patch('live/{tournament:slug}/fixtures/{fixture}/score', [LiveControlController::class, 'updateScore'])->name('live.control.score');
        Route::post('live/{tournament:slug}/fixtures/{fixture}/end', [LiveControlController::class, 'endMatch'])->name('live.control.end');
    });
});
