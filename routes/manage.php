<?php

use App\Http\Controllers\FixtureScheduleController;
use App\Http\Controllers\ManageController;
use App\Http\Controllers\ScoreReviewController;
use Illuminate\Support\Facades\Route;

// The system-admin area: tournament management independent of any pool. Gated by the
// `manage-tournament` ability, so admins reach score review/approval and the fixture schedule
// without joining a pool. (Live Control lives alongside the Live Center in routes/pools.php.)
Route::middleware(['auth', 'can:manage-tournament'])->prefix('manage')->name('manage.')->group(function () {
    Route::get('/', [ManageController::class, 'index'])->name('index');

    Route::get('{tournament:slug}/scores', [ScoreReviewController::class, 'review'])->name('scores.review');
    Route::patch('{tournament:slug}/scores/fixtures/{fixture}', [ScoreReviewController::class, 'updateProposal'])->name('scores.proposal');
    Route::put('{tournament:slug}/scores/ordering', [ScoreReviewController::class, 'updateOrdering'])->name('scores.ordering');
    Route::post('{tournament:slug}/scores/approve', [ScoreReviewController::class, 'approve'])->name('scores.approve');

    Route::get('{tournament:slug}/schedule', [FixtureScheduleController::class, 'index'])->name('schedule.index');
    Route::patch('{tournament:slug}/fixtures/{fixture}/reschedule', [FixtureScheduleController::class, 'reschedule'])->name('fixtures.reschedule');
});
