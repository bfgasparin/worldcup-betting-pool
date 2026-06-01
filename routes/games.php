<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\ScoreReviewController;
use App\Http\Controllers\TransitionTournamentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('games', [GameController::class, 'index'])->name('games.index');
    Route::get('games/{tournament:slug}', [GameController::class, 'show'])->name('games.show');
    Route::get('games/{tournament:slug}/leaderboard', [GameController::class, 'leaderboard'])->name('games.leaderboard');
    Route::get('games/{tournament:slug}/predict', [PredictionController::class, 'edit'])->name('games.predict.edit');
    Route::put('games/{tournament:slug}/predict/group', [PredictionController::class, 'updateGroupStage'])->name('games.predict.group');
    Route::put('games/{tournament:slug}/predict/knockout', [PredictionController::class, 'updateKnockout'])->name('games.predict.knockout');
    Route::patch('games/{tournament:slug}/status', TransitionTournamentController::class)->name('games.status.update');

    // Admin-only score review & approval.
    Route::middleware('can:manage-tournament')->group(function () {
        Route::get('games/{tournament:slug}/scores', [ScoreReviewController::class, 'review'])->name('games.scores.review');
        Route::patch('games/{tournament:slug}/scores/fixtures/{fixture}', [ScoreReviewController::class, 'updateProposal'])->name('games.scores.proposal');
        Route::post('games/{tournament:slug}/scores/approve', [ScoreReviewController::class, 'approve'])->name('games.scores.approve');
    });
});
