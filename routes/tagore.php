<?php

use App\Http\Controllers\Tagore\AcademicStructureController;
use App\Http\Controllers\Tagore\AdministrationController;
use App\Http\Controllers\Tagore\AdmissionsController;
use App\Http\Controllers\Tagore\DashboardController;
use App\Http\Controllers\Tagore\FeeController;
use App\Http\Controllers\Tagore\FeeImportController;
use App\Http\Controllers\Tagore\FeeManagementController;
use App\Http\Controllers\Tagore\LegacyStudentMappingController;
use App\Http\Controllers\Tagore\LegacyFeeReconciliationController;
use App\Http\Controllers\Tagore\OnlinePaymentController;
use App\Http\Controllers\Tagore\ParentDashboardController;
use App\Http\Controllers\Tagore\StudentParentMigrationController;
use App\Http\Controllers\Tagore\TaskController;
use Illuminate\Support\Facades\Route;

Route::post('/tagore/payments/webhook/{gateway}', [OnlinePaymentController::class, 'webhook'])->where('gateway', 'razorpay')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

Route::middleware(['auth', \App\Http\Middleware\TagorePerformance::class])->prefix('tagore')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('tagore.dashboard');
    Route::get('/parent', [ParentDashboardController::class, 'index'])->name('tagore.parent.dashboard');
    Route::get('/admin', [AdministrationController::class, 'index'])->name('tagore.admin');
    Route::post('/admin/institution', [AdministrationController::class, 'storeInstitution'])->name('tagore.admin.institution');
    Route::post('/admin/academic-year', [AdministrationController::class, 'storeAcademicYear'])->name('tagore.admin.academic-year');
    Route::post('/admin/role', [AdministrationController::class, 'assignRole'])->name('tagore.admin.role');
    Route::get('/admin/academic-structure', [AcademicStructureController::class, 'index'])->name('tagore.academic.structure');
    Route::post('/admin/academic-structure/stream', [AcademicStructureController::class, 'storeStream'])->name('tagore.academic.stream');
    Route::post('/admin/academic-structure/section', [AcademicStructureController::class, 'storeSection'])->name('tagore.academic.section');
    Route::get('/admin/student-parent-migration', [StudentParentMigrationController::class, 'index'])->name('tagore.migration.student-parent');
    Route::post('/admin/student-parent-migration/sync', [StudentParentMigrationController::class, 'sync'])->name('tagore.migration.student-parent.sync');

    Route::get('/admissions', [AdmissionsController::class, 'index'])->name('tagore.admissions.index');
    Route::get('/admissions/create', [AdmissionsController::class, 'create'])->name('tagore.admissions.create');
    Route::post('/admissions', [AdmissionsController::class, 'store'])->name('tagore.admissions.store');
    Route::get('/admissions/{leadId}', [AdmissionsController::class, 'show'])->whereNumber('leadId')->name('tagore.admissions.show');
    Route::post('/admissions/{leadId}/activity', [AdmissionsController::class, 'activity'])->whereNumber('leadId')->name('tagore.admissions.activity');

    Route::get('/tasks', [TaskController::class, 'index'])->name('tagore.tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tagore.tasks.store');
    Route::patch('/tasks/{taskId}', [TaskController::class, 'update'])->whereNumber('taskId')->name('tagore.tasks.update');
    Route::post('/tasks/{taskId}/review', [TaskController::class, 'review'])->whereNumber('taskId')->name('tagore.tasks.review');
    Route::patch('/reviews/{reviewId}/complete', [TaskController::class, 'completeReview'])->whereNumber('reviewId')->name('tagore.reviews.complete');

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
    Route::get('/accounts/fees/import/{batchId}/reconciliation', [LegacyFeeReconciliationController::class, 'show'])->whereNumber('batchId')->name('tagore.fees.import.reconciliation');
    Route::get('/accounts/fees/import/{batchId}/mapping', [LegacyStudentMappingController::class, 'index'])->whereNumber('batchId')->name('tagore.fees.import.mapping');
    Route::get('/accounts/fees/import/{batchId}/mapping/suggestions', [LegacyStudentMappingController::class, 'suggestions'])->whereNumber('batchId')->name('tagore.fees.import.mapping.suggestions');
    Route::post('/accounts/fees/import/{batchId}/mapping/auto', [LegacyStudentMappingController::class, 'autoMatch'])->whereNumber('batchId')->name('tagore.fees.import.mapping.auto');
    Route::post('/accounts/fees/import/{batchId}/mapping/{rowId}', [LegacyStudentMappingController::class, 'store'])->whereNumber('batchId')->whereNumber('rowId')->name('tagore.fees.import.mapping.store');
    Route::get('/api/dashboard', [DashboardController::class, 'api'])->name('tagore.dashboard.api');
});
