<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate Sanctum bearer tokens on public routes without requiring login.
 */
class OptionalSanctumAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() && ! $request->user()) {
            $user = Auth::guard('sanctum')->user();
            if ($user) {
                Auth::setUser($user);
            }
        }

        return $next($request);
    }
}
