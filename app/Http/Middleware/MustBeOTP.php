<?php

namespace App\Http\Middleware;

use App\Traits\AuthenticationProcess;
use Illuminate\Support\Facades\Auth;
use App\Models\Authentication;
use App\Models\User;
use Closure;

class MustBeOTP
{
    use AuthenticationProcess;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if(auth()->user()->isAdmin())
        {
            $user = User::where('id',Auth::id())->first();

            if($user->mobile_verified != 1)
            {
                if( $this->checkAuthentication(Auth::id()) )
                {
                    return $next($request);
                }
                else
                {
                    abort(403);
                } 
            }
        }
        else
        {
            abort(403);
        }
    }
}