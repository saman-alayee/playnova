<?php

namespace App\Http\Middleware;

use App\Http\Traits\InvalidatesAdminDashboard;
use App\Modules\Content\Services\ContentCacheService;
use App\Modules\Tournament\Services\TournamentListingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InvalidateCachesAfterAdminMutation
{
    use InvalidatesAdminDashboard;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $this->invalidateAdminDashboard();
            TournamentListingService::forgetHomeCache();
            TournamentListingService::forgetLeaderboardCache();
            ContentCacheService::forgetAll();
        }

        return $response;
    }
}
