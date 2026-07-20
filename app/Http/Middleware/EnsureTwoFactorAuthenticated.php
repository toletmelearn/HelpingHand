<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTwoFactorAuthenticated
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        // If user has 2FA enabled but not verified in this session
        if ($user && $user->two_factor_enabled && !session('two_factor_verified')) {
            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
