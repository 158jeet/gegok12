<?php

/**
 * SPDX-License-Identifier: MIT
 * (c) 2025 GegoSoft Technologies and GegoK12 Contributors
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Traits\AuthenticatesUsers;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller implements ShouldQueue
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Determine where to redirect users after login.
     *
     * Routes users based on their role (usergroup_id):
     * - Stock Keeper (ID 12) -> /stock/dashboard
     * - All other roles -> /admin/dashboard (then middleware handles role-specific routing)
     *
     * @return string The redirect path
     */

    protected $redirectTo = '/admin/dashboard';


    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Route active Tagore ERP users into their role-aware workspace; preserve legacy routing for other users.
     */
    protected function authenticated(Request $request, $user)
    {
        if (Schema::hasTable('tagore_user_roles') && DB::table('tagore_user_roles')->where('user_id', $user->id)->where('status', 'active')->exists()) {
            return redirect()->intended(route('tagore.dashboard'));
        }

        return null;
    }

    /**
     * Display the login form.
     *
     * @return \Illuminate\View\View
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }
}
