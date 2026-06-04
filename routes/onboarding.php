<?php

use App\Http\Controllers\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::patch('onboarding/name', [OnboardingController::class, 'updateName'])->name('onboarding.name');
    Route::post('onboarding/avatar', [OnboardingController::class, 'updateAvatar'])->name('onboarding.avatar');
    Route::post('onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
});
