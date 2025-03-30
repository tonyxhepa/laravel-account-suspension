<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNotSuspended
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        if ($user && method_exists($user, 'isSuspended') && $user->isSuspended()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerate();
            return Redirect::route('login')->withErrors(['email' => 'Your account has been suspended.']);
        }

        return $next($request);
    }
}
