<?php

namespace App\Http\Middleware;

use App\Services\ApiErrorLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogApiErrors
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        ApiErrorLogService::logResponse($request, $response);

        return $response;
    }
}
