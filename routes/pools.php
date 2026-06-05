<?php

use App\Http\Controllers\FixtureScheduleController;
use App\Http\Controllers\PoolController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\ScoreReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('pools', [PoolController::class, 'index'])->name('pools.index');
    Route::get('pools/{pool:slug}', [PoolController::class, 'show'])->name('pools.show');
    Route::post('pools/{pool:slug}/join', [PoolController::class, 'join'])->name('pools.join');
    Route::get('pools/{pool:slug}/leaderboard', [PoolController::class, 'leaderboard'])->name('pools.leaderboard');
    Route::get('pools/{pool:slug}/predict', [PredictionController::class, 'edit'])->name('pools.predict.edit');
    Route::put('pools/{pool:slug}/predict/group', [PredictionController::class, 'updateGroupStage'])->name('pools.predict.group');
    Route::put('pools/{pool:slug}/predict/knockout', [PredictionController::class, 'updateKnockout'])->name('pools.predict.knockout');
    Route::put('pools/{pool:slug}/predict/ordering', [PredictionController::class, 'updateOrdering'])->name('pools.predict.ordering');

    // Admin-only score review & approval.
    Route::middleware('can:manage-tournament')->group(function () {
        Route::get('pools/{pool:slug}/scores', [ScoreReviewController::class, 'review'])->name('pools.scores.review');
        Route::patch('pools/{pool:slug}/scores/fixtures/{fixture}', [ScoreReviewController::class, 'updateProposal'])->name('pools.scores.proposal');
        Route::put('pools/{pool:slug}/scores/ordering', [ScoreReviewController::class, 'updateOrdering'])->name('pools.scores.ordering');
        Route::post('pools/{pool:slug}/scores/approve', [ScoreReviewController::class, 'approve'])->name('pools.scores.approve');

        // Admin-only fixture rescheduling (delays, venue moves).
        Route::get('pools/{pool:slug}/schedule', [FixtureScheduleController::class, 'index'])->name('pools.schedule.index');
        Route::patch('pools/{pool:slug}/fixtures/{fixture}/reschedule', [FixtureScheduleController::class, 'reschedule'])->name('pools.fixtures.reschedule');
    });
});
