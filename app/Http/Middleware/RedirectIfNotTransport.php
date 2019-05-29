<?php

namespace App\Http\Middleware;

use Closure;
use Auth;
use Illuminate\Support\Facades\Session;

class RedirectIfNotTransport
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
            $request->session()->flash('error', 'สำหรับขนส่งเท่านั้น');
            return redirect(route('transport.login'));
        }
        
        if(!auth()->guard($guard)->user()->hasRole('transport')){
            Auth::logout();
            Session::flush();
            $request->session()->flash('error', 'สำหรับขนส่งเท่านั้น');
            return redirect(route('transport.login'));
        }

        return $next($request);
    }
}
