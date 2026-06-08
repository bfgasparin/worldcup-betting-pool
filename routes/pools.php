<?php

use App\Http\Controllers\LiveController;
use App\Http\Controllers\PoolController;
use App\Http\Controllers\PredictionController;
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
});
