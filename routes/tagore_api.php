<?php

use App\Http\Controllers\Tagore\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('tagore/v1')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'api'])->name('tagore.api.dashboard');
});
