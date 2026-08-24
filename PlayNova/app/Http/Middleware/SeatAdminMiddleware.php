<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SeatAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || (! $user->isAdmin() && ! $user->isSeatAdmin())) {
            abort(403, 'دسترسی غیرمجاز.');
        }

        return $next($request);
    }
}
