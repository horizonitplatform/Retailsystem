<?php

namespace App\Http\Middleware;

use Closure;
use Auth;
use Illuminate\Support\Facades\Session;

class RedirectIfNotEmployee
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @param string $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = 'employee')
    {
        if (!auth()->guard($guard)->check()) {
            $request->session()->flash('error', 'You must be an employee to see this page');
            return redirect(route('admin.login'));
        }
        if(auth()->guard($guard)->user()->hasRole('transport')){
            Auth::logout();
            Session::flush();
            $request->session()->flash('error', 'สำหรับแอดมินเท่านั้น');
            return redirect(route('admin.login'));
        }

        return $next($request);
    }
}
