<?php

use App\Http\Controllers\FixtureScheduleController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\ScoreReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('games', [GameController::class, 'index'])->name('games.index');
    Route::get('games/{game:slug}', [GameController::class, 'show'])->name('games.show');
    Route::get('games/{game:slug}/leaderboard', [GameController::class, 'leaderboard'])->name('games.leaderboard');
    Route::get('games/{game:slug}/predict', [PredictionController::class, 'edit'])->name('games.predict.edit');
    Route::put('games/{game:slug}/predict/group', [PredictionController::class, 'updateGroupStage'])->name('games.predict.group');
    Route::put('games/{game:slug}/predict/knockout', [PredictionController::class, 'updateKnockout'])->name('games.predict.knockout');
    Route::put('games/{game:slug}/predict/ordering', [PredictionController::class, 'updateOrdering'])->name('games.predict.ordering');

    // Admin-only score review & approval.
    Route::middleware('can:manage-tournament')->group(function () {
        Route::get('games/{game:slug}/scores', [ScoreReviewController::class, 'review'])->name('games.scores.review');
        Route::patch('games/{game:slug}/scores/fixtures/{fixture}', [ScoreReviewController::class, 'updateProposal'])->name('games.scores.proposal');
        Route::put('games/{game:slug}/scores/ordering', [ScoreReviewController::class, 'updateOrdering'])->name('games.scores.ordering');
        Route::post('games/{game:slug}/scores/approve', [ScoreReviewController::class, 'approve'])->name('games.scores.approve');

        // Admin-only fixture rescheduling (delays, venue moves).
        Route::get('games/{game:slug}/schedule', [FixtureScheduleController::class, 'index'])->name('games.schedule.index');
        Route::patch('games/{game:slug}/fixtures/{fixture}/reschedule', [FixtureScheduleController::class, 'reschedule'])->name('games.fixtures.reschedule');
    });
});
