<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesAdmin;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\ApiErrorLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ApiErrorLogAdminController extends BaseApiController
{
    use AuthorizesAdmin;

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $query = ApiErrorLog::query()->with(['user:id,username,email', 'resolver:id,username']);

        $status = $request->query('status', 'unresolved');

        if ($status === 'unresolved') {
            $query->whereNull('resolved_at');
        } elseif ($status === 'resolved') {
            $query->whereNotNull('resolved_at');
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                    ->orWhere('endpoint', 'like', "%{$search}%")
                    ->orWhere('exception_class', 'like', "%{$search}%");
            });
        }

        $logs = $query->orderByDesc('created_at')->paginate(30);

        return $this->paginated($logs->through(fn (ApiErrorLog $log) => $this->formatLog($log, false)));
    }

    public function show(ApiErrorLog $apiErrorLog): JsonResponse
    {
        $this->authorizeAdmin();

        $apiErrorLog->load(['user:id,username,email', 'resolver:id,username']);

        return $this->success($this->formatLog($apiErrorLog, true));
    }

    public function stats(): JsonResponse
    {
        $this->authorizeAdmin();

        return $this->success([
            'unresolved_count' => ApiErrorLog::whereNull('resolved_at')->count(),
            'last_24h_count' => ApiErrorLog::where('created_at', '>=', now()->subDay())->count(),
        ]);
    }

    public function resolve(ApiErrorLog $apiErrorLog): JsonResponse
    {
        $this->authorizeAdmin();

        if (! $apiErrorLog->isResolved()) {
            $apiErrorLog->update([
                'resolved_at' => now(),
                'resolved_by' => request()->user()?->id,
            ]);
            Cache::forget('admin:dashboard:stats');
        }

        return $this->success(null, 'خطا به‌عنوان بررسی‌شده علامت‌گذاری شد.');
    }

    public function resolveAll(): JsonResponse
    {
        $this->authorizeAdmin();

        $count = ApiErrorLog::whereNull('resolved_at')->update([
            'resolved_at' => now(),
            'resolved_by' => request()->user()?->id,
        ]);
        Cache::forget('admin:dashboard:stats');

        return $this->success(['count' => $count], "{$count} خطا علامت‌گذاری شد.");
    }

    public function destroyAll(): JsonResponse
    {
        $this->authorizeAdmin();

        $count = ApiErrorLog::query()->count();
        ApiErrorLog::query()->delete();
        Cache::forget('admin:dashboard:stats');

        return $this->success(['count' => $count], "{$count} خطا حذف شد.");
    }

    protected function formatLog(ApiErrorLog $log, bool $detailed): array
    {
        $data = [
            'id' => $log->id,
            'status_code' => $log->status_code,
            'method' => $log->method,
            'endpoint' => $log->endpoint,
            'message' => $log->message,
            'exception_class' => $log->exception_class,
            'user' => $log->user ? [
                'id' => $log->user->id,
                'username' => $log->user->username,
                'email' => $log->user->email,
            ] : null,
            'ip_address' => $log->ip_address,
            'is_resolved' => $log->isResolved(),
            'resolved_at' => $log->resolved_at?->toIso8601String(),
            'resolved_by' => $log->resolver ? [
                'id' => $log->resolver->id,
                'username' => $log->resolver->username,
            ] : null,
            'created_at' => $log->created_at?->toIso8601String(),
            'created_at_display' => $log->created_at?->format('Y/m/d H:i'),
        ];

        if ($detailed) {
            $data['stack_trace'] = $log->stack_trace;
            $data['context'] = $log->context;
        }

        return $data;
    }
}
