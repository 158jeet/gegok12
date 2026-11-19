<?php

use Illuminate\Support\Facades\Route;

// The exam addon is optional in the base GegoK12 distribution. Only register
// its installer routes when the addon controller is actually installed.
if (class_exists(\App\Http\Controllers\Admin\Addon\AddonInstallExamController::class)) {
    Route::get('/addon/install/exam', 'Addon\\AddonInstallExamController@create');
    Route::post('/addon/install/exam', 'Addon\\AddonInstallExamController@store');
}
