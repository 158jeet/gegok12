<?php

use App\Http\Controllers\Superadmin\DashboardController;

/*Route::get('/', function () {
  return redirect()->route('login');
    //return view('welcome');
});*/
Route::get('/', function () {
    return redirect()->route('login');
});


Auth::routes();



//Impersonate as teacher
Route::get('/teacher/{id}/impersonate', 'Auth\ImpersonateController@impersonate')->middleware('auth', 'schooladmin');
Route::get('/library/{id}/impersonate', 'Auth\ImpersonateController@librarianimpersonate')->middleware('auth', 'schooladmin');
Route::get('/student/{id}/impersonate', 'Auth\ImpersonateController@studentimpersonate')->middleware('auth', 'schooladmin');
Route::get('/teacher/impersonate/stop', 'Auth\ImpersonateController@stopImpersonate')->middleware('auth');

Route::get('/schooladmin/{id}/impersonate', 'Auth\ImpersonateController@schoolAdminimpersonate')->middleware('auth', 'superadmin');

//Reset Password for member
// Route::get('/password/reset/{token}', 'Auth\ResetPasswordController@showResetForm');
// Route::post('/password/reset', 'Auth\ResetPasswordController@reset')->name('password.reset');
//Email Verification for Member
Route::get('/emailverification/{token}', 'Auth\EmailVerificationController@emailverification');
// OTP Verification
// SMS test endpoint removed: it previously sent a hard-coded SMS on every public GET.
Route::get('/verifyotp', 'OTPController@create');
Route::post('/verifyotp', 'OTPController@store');
//siteadmin
Route::group(['middleware' => ['siteadmin'], 'namespace' => 'Admin'], function () {
    Route::get('/payment/subscription', 'PaymentController@Subscription');
});


// Cache maintenance is intentionally CLI-only; never expose cache clearing as a public HTTP route.

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

\Illuminate\Support\Facades\Route::get('/olympiad/register', [\App\Http\Controllers\Tagore\OlympiadRegistrationController::class, 'create'])
    ->middleware('throttle:olympiad-register-get')->name('olympiad.register');
\Illuminate\Support\Facades\Route::post('/olympiad/register', [\App\Http\Controllers\Tagore\OlympiadRegistrationController::class, 'store'])
    ->middleware('throttle:olympiad-register-post')->name('olympiad.register.store');
\Illuminate\Support\Facades\Route::get('/olympiad/registered', [\App\Http\Controllers\Tagore\OlympiadRegistrationController::class, 'registered'])
    ->middleware('throttle:olympiad-register-get')->name('olympiad.registered');
// Test-only browser acceptance endpoints. These routes are not registered in production.
if (app()->environment('testing') && filter_var(env('TAGORE_E2E_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
    \Illuminate\Support\Facades\Route::get('/__e2e/health', function () {
        return response()->json(['ok' => true, 'environment' => app()->environment()]);
    });

    \Illuminate\Support\Facades\Route::get('/__e2e/session/{role}', function (\Illuminate\Http\Request $request, string $role) {
        $accounts = [
            'owner' => 'owner@tagore-demo.local',
            'principal' => 'principal@tagore-demo.local',
            'coordinator' => 'coordinator@tagore-demo.local',
            'teacher' => 'teacher@tagore-demo.local',
            'parent' => 'parent@tagore-demo.local',
            'student' => 'student@tagore-demo.local',
            'accounts' => 'accounts@tagore-demo.local',
            'fee-editor' => 'fees@tagore-demo.local',
        ];
        abort_unless(array_key_exists($role, $accounts), 404);
        $user = \App\Models\User::query()->where('email', $accounts[$role])->whereNull('deleted_at')->firstOrFail();
        \Illuminate\Support\Facades\Auth::login($user);
        $request->session()->regenerate();
        return redirect()->route('tagore.dashboard');
    })->where('role', 'owner|principal|coordinator|teacher|parent|student|accounts|fee-editor');

    \Illuminate\Support\Facades\Route::get('/__e2e/routes', function () {
        return response()->json(
            collect(\Illuminate\Support\Facades\Route::getRoutes())
                ->filter(function ($route) {
                    return str_starts_with($route->uri(), 'tagore/')
                        && !str_contains($route->uri(), '{')
                        && in_array('GET', $route->methods(), true);
                })
                ->map(fn ($route) => ['uri' => '/' . $route->uri()])
                ->values()
        );
    });
}
