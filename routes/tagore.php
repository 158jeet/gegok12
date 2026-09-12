<?php

use App\Http\Controllers\Tagore\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('tagore')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('tagore.dashboard');
    Route::get('/child/{studentId}', [DashboardController::class, 'child'])->whereNumber('studentId')->name('tagore.child');
    Route::post('/child/{studentId}/feedback', [DashboardController::class, 'submitFeedback'])->whereNumber('studentId')->name('tagore.feedback.submit');
    Route::get('/api/dashboard', [DashboardController::class, 'api'])->name('tagore.dashboard.api');
});
