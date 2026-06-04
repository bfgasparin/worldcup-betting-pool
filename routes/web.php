<?php

use App\Http\Controllers\Auth\LoginCodeController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::post('login/code', [LoginCodeController::class, 'store'])
    ->middleware(['guest', 'throttle:login-code'])
    ->name('login.code.send');

require __DIR__.'/onboarding.php';
require __DIR__.'/games.php';
require __DIR__.'/settings.php';
