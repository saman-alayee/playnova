<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CachePublicGetResponse
{
    private const TTL_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->method() !== 'GET' || $request->user()) {
            return $next($request);
        }

        $key = 'http:public:' . sha1($request->fullUrl());

        if ($cached = Cache::get($key)) {
            return response($cached['body'], $cached['status'], $cached['headers'])
                ->header('X-PlayNova-Cache', 'HIT');
        }

        $response = $next($request);

        if ($response->isSuccessful() && str_contains((string) $response->headers->get('Content-Type'), 'json')) {
            Cache::put($key, [
                'body' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => $response->headers->all(),
            ], self::TTL_SECONDS);
            $response->headers->set('X-PlayNova-Cache', 'MISS');
        }

        return $response;
    }
}
