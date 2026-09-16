<?php

use App\Http\Controllers\Tagore\DashboardController;
use App\Http\Controllers\Tagore\FeeController;
use App\Http\Controllers\Tagore\FeeImportController;
use App\Http\Controllers\Tagore\FeeManagementController;
use App\Http\Controllers\Tagore\OnlinePaymentController;
use Illuminate\Support\Facades\Route;

Route::post('/tagore/payments/webhook/{gateway}', [OnlinePaymentController::class, 'webhook'])->where('gateway', 'razorpay')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

Route::middleware(['auth'])->prefix('tagore')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('tagore.dashboard');
    Route::get('/child/{studentId}', [DashboardController::class, 'child'])->whereNumber('studentId')->name('tagore.child');
    Route::post('/child/{studentId}/feedback', [DashboardController::class, 'submitFeedback'])->whereNumber('studentId')->name('tagore.feedback.submit');
    Route::get('/child/{studentId}/fees', [FeeController::class, 'student'])->whereNumber('studentId')->name('tagore.fees.student');
    Route::post('/child/{studentId}/fees/pay', [OnlinePaymentController::class, 'initiate'])->whereNumber('studentId')->name('tagore.payments.initiate');
    Route::post('/payments/confirm', [OnlinePaymentController::class, 'confirm'])->name('tagore.payments.confirm');
    Route::get('/accounts/fees', [FeeController::class, 'accounts'])->name('tagore.fees.accounts');
    Route::post('/accounts/fees/student/{studentId}/payment', [FeeController::class, 'recordOfflinePayment'])->whereNumber('studentId')->name('tagore.fees.payment');
    Route::post('/accounts/fees/payment/{paymentId}/reconcile', [FeeController::class, 'reconcile'])->whereNumber('paymentId')->name('tagore.fees.reconcile');
    Route::get('/accounts/fees/payment/{paymentId}/receipt', [FeeController::class, 'receipt'])->whereNumber('paymentId')->name('tagore.fees.receipt');
    Route::get('/accounts/fees/manage', [FeeManagementController::class, 'index'])->name('tagore.fees.manage');
    Route::post('/accounts/fees/manage/structure', [FeeManagementController::class, 'storeStructure'])->name('tagore.fees.manage.structure');
    Route::post('/accounts/fees/manage/demand', [FeeManagementController::class, 'generateDemand'])->name('tagore.fees.manage.demand');
    Route::post('/accounts/fees/manage/assignment', [FeeManagementController::class, 'storeAssignment'])->name('tagore.fees.manage.assignment');
    Route::post('/accounts/fees/manage/bulk/preview', [FeeManagementController::class, 'bulkPreview'])->name('tagore.fees.manage.bulk.preview');
    Route::post('/accounts/fees/manage/bulk', [FeeManagementController::class, 'generateBulk'])->name('tagore.fees.manage.bulk');
    Route::get('/accounts/fees/import', [FeeImportController::class, 'index'])->name('tagore.fees.import');
    Route::post('/accounts/fees/import/preview', [FeeImportController::class, 'preview'])->name('tagore.fees.import.preview');
    Route::post('/accounts/fees/import/{batchId}/apply', [FeeImportController::class, 'apply'])->whereNumber('batchId')->name('tagore.fees.import.apply');
    Route::get('/api/dashboard', [DashboardController::class, 'api'])->name('tagore.dashboard.api');
});
