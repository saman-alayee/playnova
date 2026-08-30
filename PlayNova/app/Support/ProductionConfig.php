<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class ProductionConfig
{
    public static function assertReady(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        $issues = [];

        if (config('cache.default') === 'file') {
            $issues[] = 'CACHE_DRIVER should be redis in production.';
        }

        if (config('queue.default') === 'sync') {
            $issues[] = 'QUEUE_CONNECTION should be redis in production.';
        }

        if (config('session.driver') === 'file') {
            $issues[] = 'SESSION_DRIVER should be redis in production.';
        }

        if (config('app.debug')) {
            $issues[] = 'APP_DEBUG must be false in production.';
        }

        if (empty(config('sentry.dsn')) && empty(env('SENTRY_LARAVEL_DSN')) && empty(env('SENTRY_DSN'))) {
            $issues[] = 'SENTRY_LARAVEL_DSN is not configured.';
        }

        foreach ($issues as $issue) {
            Log::warning('[production-config] ' . $issue);
        }
    }
}
