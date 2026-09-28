<?php
/**
 * Handles user authentication workflow including validation, throttling, and responses.
 */

namespace App\Traits;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use App\Traits\ThrottlesLogins;
use App\Traits\RedirectsUsers;
use Illuminate\Http\Request;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

trait AuthenticatesUsers
{
    use RedirectsUsers, ThrottlesLogins;

    /**
     * Handle a login request to the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request)
    {
        $this->validateLogin($request);

        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        if ($this->attemptLogin($request)) {
            return $this->sendLoginResponse($request);
        }

        // If the login attempt was unsuccessful we will increment the number of attempts
        // to login and redirect the user back to the login form. Of course, when this
        // user surpasses their maximum number of attempts they will get locked out.
        $this->incrementLoginAttempts($request);

        return $this->sendFailedLoginResponse($request);
    }

    /**
     * Validate the user login request.
     *
     * Registers custom validators:
     * - checkschool: Validates that the school is active
     * - checkusers: Validates that the user exists
     * - checkactive: Validates that the user is not suspended (inactive status)
     * - checkexit: Validates that the user has not exited (exit status)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function validateLogin(Request $request)
    {
        /**
         * Validator: checkschool
         * Ensures the user's school is active (status = 1).
         * SuperAdmins (usergroup_id == 1) bypass school checks.
         *
         * @param string $attribute The attribute being validated
         * @param string $value The value being validated
         * @param array $parameters Additional parameters
         * @param \Illuminate\Validation\Validator $validator The validator instance
         * @return bool True if school is active or user is superadmin
         */
        Validator::extend('checkschool', function ($attribute, $value, $parameters, $validator) {
            $users = $this->findLoginUser();

            if (!$users) {
                return false;
            }

            if ($users->usergroup_id == 1) {
                return true;
            }

            return School::IsActive($users->school_id)->exists();
        }, 'Invalid Credentials. You are not in this school');

        /**
         * Validator: checkusers
         * Validates that the user exists in the system.
         *
         * @param string $attribute The attribute being validated
         * @param string $value The value being validated
         * @param array $parameters Additional parameters
         * @param \Illuminate\Validation\Validator $validator The validator instance
         * @return bool True if user exists
         */
        Validator::extend('checkusers', function ($attribute, $value, $parameters, $validator) {
            return $this->findLoginUser() !== null;
        }, 'Invalid Credentials');

        /**
         * Validator: checkactive
         * Validates that the user's profile status is not 'inactive' (suspended).
         *
         * @param string $attribute The attribute being validated
         * @param string $value The value being validated
         * @param array $parameters Additional parameters
         * @param \Illuminate\Validation\Validator $validator The validator instance
         * @return bool True if user is active
         */
        Validator::extend('checkactive', function ($attribute, $value, $parameters, $validator) {
            $users = $this->findLoginUser();

            return $users
                && $users->status !== 'inactive'
                && (!$users->userprofile || $users->userprofile->status !== 'inactive');
        }, 'You are suspended by site admin');

        /**
         * Validator: checkexit
         * Validates that the user's profile status is not 'exit' (no longer works in school).
         *
         * @param string $attribute The attribute being validated
         * @param string $value The value being validated
         * @param array $parameters Additional parameters
         * @param \Illuminate\Validation\Validator $validator The validator instance
         * @return bool True if user status is not 'exit'
         */
        Validator::extend('checkexit', function ($attribute, $value, $parameters, $validator) {
            $users = $this->findLoginUser();

            return $users
                && $users->status !== 'exit'
                && (!$users->userprofile || $users->userprofile->status !== 'exit');
        }, 'You have exited this school');

        $field = $this->username();

        $rules = [
            $field => 'bail|required|string|checkactive|checkexit',
            'password' => 'bail|required|string|checkschool',
        ];

        if ($field === 'email') {
            $rules[$field] .= '|email';
        }

        $this->validate($request, $rules);
    }

    /**
     * Attempt to log the user into the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function attemptLogin(Request $request)
    {
        return $this->guard()->attempt(
            $this->credentials($request), $request->filled('remember')
        );
    }

    /**
     * Get the needed authorization credentials from the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function credentials(Request $request)
    {
        $field = $this->username();
        $value = $request->input($field);

        if ($field === 'email' && $value !== null) {
            $user = User::whereRaw('LOWER(email) = LOWER(?)', [$value])->first();
            if ($user) {
                $value = $user->email;
            }
        }

        return [$field => $value, 'password' => $request->input('password')];
    }

    /**
     * Send the response after the user was authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    protected function sendLoginResponse(Request $request)
    {
        $request->session()->regenerate();

        $this->clearLoginAttempts($request);

        return $this->authenticated($request, $this->guard()->user())
                ?: redirect()->intended($this->redirectPath());
    }

    /**
     * The user has been authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function authenticated(Request $request, $user)
    {
        //
    }

    /**
     * Get the failed login response instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function sendFailedLoginResponse(Request $request)
    {
        throw ValidationException::withMessages([
            $this->username() => [trans('auth.failed')],
        ]);
    }

    /**
     * Get the login username to be used by the controller.
     *
     * Supports flexible login: users can login with either email or registration_number.
     * Validates the input to determine which field to use.
     *
     * @return string The field name ('email' or 'registration_number')
     */
    public function username()
    {
        $login = request()->input('email');

        if ($login === null || $login === '') {
            $field = 'email';
        } elseif (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $field = 'email';
        } else {
            $existsAsRegistration = User::where('registration_number', $login)->exists();
            $field = $existsAsRegistration ? 'registration_number' : 'email';
        }

        request()->merge([$field => $login]);

        return $field;
    }

    protected function findLoginUser(): ?User
    {
        $field = $this->username();
        $value = request()->input($field);

        if ($value === null || $value === '') {
            return null;
        }

        $query = User::with('userprofile');

        if ($field === 'email') {
            return $query->whereRaw('LOWER(email) = LOWER(?)', [$value])->first();
        }

        return $query->where('registration_number', $value)->first();
    }

    /**
     * Log the user out of the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request)
    {
        $this->guard()->logout();

        $request->session()->invalidate();

        return $this->loggedOut($request) ?: redirect('/');
    }

    /**
     * The user has logged out of the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    protected function loggedOut(Request $request)
    {
        //
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard()
    {
        return Auth::guard();
    }
}
