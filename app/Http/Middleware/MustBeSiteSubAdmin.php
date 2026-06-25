<?php

namespace App\Http\Middleware;

use Closure;

class MustBeSiteSubAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
         
        if(\Auth::user()->isAdmin())
        {
            return redirect('/admin/dashboard');
        }

        if(\Auth::user()->isTeacher())
        {
            return redirect('/teacher/dashboard');
        }

        if(\Auth::user()->isStudent())
        {
            return redirect('/student/dashboard');
        }
        
        if(\Auth::user()->isLibrarian())
        {
            return redirect('/library/dashboard');          
        }

        abort(404);
    }
}
