<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\PredictionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('games', [GameController::class, 'index'])->name('games.index');
    Route::get('games/{tournament:slug}', [GameController::class, 'show'])->name('games.show');

    Route::get('games/{tournament:slug}/predict', [PredictionController::class, 'edit'])->name('games.predict.edit');
    Route::put('games/{tournament:slug}/predict/group', [PredictionController::class, 'updateGroupStage'])->name('games.predict.group');
    Route::put('games/{tournament:slug}/predict/knockout', [PredictionController::class, 'updateKnockout'])->name('games.predict.knockout');
});
