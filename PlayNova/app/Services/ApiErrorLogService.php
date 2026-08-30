<?php

namespace App\Services;

use App\Models\ApiErrorLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApiErrorLogService
{
    protected const SKIP_PATHS = [
        'api/v1/health',
        'api/v1/admin/api-errors',
    ];

    protected const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'captcha',
        'national_id',
        'bank_card_number',
    ];

    public static function logException(Request $request, Throwable $e, int $statusCode = 500): void
    {
        if (! self::shouldLog($request, $statusCode)) {
            return;
        }

        self::store($request, [
            'status_code' => $statusCode,
            'message' => Str::limit($e->getMessage(), 1000),
            'exception_class' => $e::class,
            'stack_trace' => self::formatTrace($e),
        ]);

        $request->attributes->set('api_error_logged', true);
    }

    public static function logResponse(Request $request, Response $response): void
    {
        $statusCode = $response->getStatusCode();

        if (! self::shouldLog($request, $statusCode) || $request->attributes->get('api_error_logged')) {
            return;
        }

        $message = self::extractMessage($response) ?? 'خطای سرور';

        self::store($request, [
            'status_code' => $statusCode,
            'message' => Str::limit($message, 1000),
            'exception_class' => null,
            'stack_trace' => null,
        ]);
    }

    protected static function shouldLog(Request $request, int $statusCode): bool
    {
        if ($statusCode < 500 || ! $request->is('api/*')) {
            return false;
        }

        $path = trim($request->path(), '/');

        foreach (self::SKIP_PATHS as $skip) {
            if (Str::startsWith($path, $skip)) {
                return false;
            }
        }

        return true;
    }

    protected static function store(Request $request, array $data): void
    {
        try {
            ApiErrorLog::create([
                'status_code' => $data['status_code'],
                'method' => strtoupper($request->method()),
                'endpoint' => '/'.trim($request->path(), '/'),
                'message' => $data['message'],
                'exception_class' => $data['exception_class'] ?? null,
                'stack_trace' => $data['stack_trace'] ?? null,
                'user_id' => $request->user()?->id,
                'ip_address' => $request->ip(),
                'context' => [
                    'query' => $request->query(),
                    'body' => self::sanitizeInput($request->all()),
                    'user_agent' => Str::limit((string) $request->userAgent(), 255),
                ],
            ]);
            Cache::forget('admin:dashboard:stats');
        } catch (Throwable $e) {
            Log::error('[api-error-log] failed to persist error log', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected static function sanitizeInput(array $input): array
    {
        $sanitized = [];

        foreach ($input as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $sanitized[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeInput($value);
                continue;
            }

            $sanitized[$key] = is_string($value) ? Str::limit($value, 500) : $value;
        }

        return $sanitized;
    }

    protected static function extractMessage(Response $response): ?string
    {
        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return null;
        }

        $decoded = json_decode($content, true);

        if (is_array($decoded)) {
            return $decoded['message'] ?? $decoded['error'] ?? null;
        }

        return Str::limit(strip_tags($content), 500);
    }

    protected static function formatTrace(Throwable $e): string
    {
        return Str::limit($e->getTraceAsString(), 8000);
    }
}
