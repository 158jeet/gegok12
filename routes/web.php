<?php

use App\Http\Controllers\Superadmin\DashboardController;

Route::get('/', function () {
    return redirect()->route('login');
});

Auth::routes();

// Browser E2E authentication is deliberately available only in the disposable
// testing environment. It creates a normal Laravel session server-side so the
// browser never handles or submits demo passwords.
if (app()->environment('testing') && (bool) config('app.e2e_enabled')) {
    Route::get('/__e2e/session/{role}', function (string $role) {
        $emails = [
            'owner' => 'owner@tagore-demo.local',
            'principal' => 'principal@tagore-demo.local',
            'coordinator' => 'coordinator@tagore-demo.local',
            'teacher' => 'teacher@tagore-demo.local',
            'parent' => 'parent@tagore-demo.local',
            'student' => 'student@tagore-demo.local',
            'accounts' => 'accounts@tagore-demo.local',
            'fee-editor' => 'fees@tagore-demo.local',
        ];

        abort_unless(isset($emails[$role]), 404);
        $user = \App\Models\User::query()->where('email', $emails[$role])->whereNull('deleted_at')->firstOrFail();
        auth()->login($user);
        request()->session()->regenerate();

        return redirect()->route('tagore.dashboard');
    })->name('e2e.session');

    Route::get('/__e2e/routes', function () {
        return collect(app('router')->getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods, true))
            ->filter(fn ($route) => str_starts_with($route->uri(), 'tagore/'))
            ->filter(fn ($route) => !str_contains($route->uri(), '{'))
            ->map(fn ($route) => ['uri' => '/' . ltrim($route->uri(), '/'), 'name' => $route->getName()])
            ->unique('uri')
            ->values();
    });
}

Route::get('/teacher/{id}/impersonate', 'Auth\ImpersonateController@impersonate')->middleware('auth', 'schooladmin');
Route::get('/library/{id}/impersonate', 'Auth\ImpersonateController@librarianimpersonate')->middleware('auth', 'schooladmin');
Route::get('/student/{id}/impersonate', 'Auth\ImpersonateController@studentimpersonate')->middleware('auth', 'schooladmin');
Route::get('/teacher/impersonate/stop', 'Auth\ImpersonateController@stopImpersonate');
Route::get('/schooladmin/{id}/impersonate', 'Auth\ImpersonateController@schoolAdminimpersonate')->middleware('auth', 'superadmin');

Route::get('/emailverification/{token}', 'Auth\EmailVerificationController@emailverification');
Route::get('/checksms', 'TestController@checksms');
Route::get('/verifyotp', 'OTPController@create');
Route::post('/verifyotp', 'OTPController@store');

Route::group(['middleware' => ['siteadmin'], 'namespace' => 'Admin'], function () {
    Route::get('/payment/subscription', 'PaymentController@Subscription');
});

Route::get('/cache-clear', function () {
    Artisan::call('cache:clear');
});

Route::get('/{slug}/standardlist','AdmissionController@list');
Route::get( '/{slug}/admission-form', 'AdmissionController@create' );
Route::post( '/{slug}/admission-form', 'AdmissionController@store' );

Route::post( '/{slug}/admission-form/validationAvatar', 'AdmissionController@validationAvatar' );
Route::post( '/{slug}/admission-form/validationFatherAvatar', 'AdmissionController@validationFatherAvatar' );
Route::post( '/{slug}/admission-form/validationMotherAvatar', 'AdmissionController@validationMotherAvatar' );
Route::post( '/{slug}/admission-form/validationStandard', 'AdmissionController@validationStandard' );
Route::post( '/{slug}/admission-form/validationStudentDetail', 'AdmissionController@validationStudentDetail' );
Route::post( '/{slug}/admission-form/validationAcademicDetail', 'AdmissionController@validationAcademicDetail' );
Route::post( '/{slug}/admission-form/validationParentDetail', 'AdmissionController@validationParentDetail' );
Route::post( '/{slug}/admission-form/validationPersonalDetail', 'AdmissionController@validationPersonalDetail' );

require base_path('routes/tagore.php');
