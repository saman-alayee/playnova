<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'redis' => $this->checkRedis(),
        ];

        $healthy = collect($checks)->every(fn (array $check) => $check['ok'] === true);

        return response()->json([
            'success' => $healthy,
            'status' => $healthy ? 'ok' : 'degraded',
            'environment' => app()->environment(),
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    protected function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['ok' => true, 'driver' => config('database.default')];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    protected function checkCache(): array
    {
        $driver = config('cache.default');

        try {
            $key = 'health:cache:' . uniqid('', true);
            Cache::put($key, 'ok', 10);
            $value = Cache::get($key);
            Cache::forget($key);

            return [
                'ok' => $value === 'ok',
                'driver' => $driver,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'driver' => $driver, 'error' => $e->getMessage()];
        }
    }

    protected function checkQueue(): array
    {
        $connection = config('queue.default');

        return [
            'ok' => $connection !== 'sync',
            'driver' => $connection,
            'size' => $this->queueSize($connection),
        ];
    }

    protected function checkRedis(): array
    {
        if (config('cache.default') !== 'redis' && config('queue.default') !== 'redis') {
            return ['ok' => true, 'skipped' => true, 'reason' => 'redis not required'];
        }

        try {
            $pong = Redis::connection()->ping();

            return [
                'ok' => $pong === true || $pong === 'PONG' || $pong === '+PONG',
                'driver' => 'redis',
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'driver' => 'redis', 'error' => $e->getMessage()];
        }
    }

    protected function queueSize(string $connection): ?int
    {
        try {
            return Queue::connection($connection)->size();
        } catch (Throwable) {
            return null;
        }
    }
}
