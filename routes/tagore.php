<?php

use App\Http\Controllers\Tagore\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('tagore')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('tagore.dashboard');
    Route::get('/api/dashboard', [DashboardController::class, 'api'])->name('tagore.dashboard.api');
});
