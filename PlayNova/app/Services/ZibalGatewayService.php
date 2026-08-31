<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZibalGatewayService
{
    private const BASE_URL = 'https://gateway.zibal.ir';

    public function isEnabled(): bool
    {
        return Setting::isPaymentGatewayActive() && Setting::isZibalConfigured();
    }

    public function callbackUrl(): string
    {
        return $this->siteOrigin() . '/wallet/callback';
    }

    public function siteOrigin(): string
    {
        $candidates = array_values(array_filter([
            trim((string) config('app.frontend_url', '')),
            trim((string) config('app.url', '')),
            trim((string) Setting::get('app_url', '')),
            'https://playnova.ir',
        ]));

        foreach ($candidates as $candidate) {
            $origin = $this->normalizeOrigin($candidate);
            if ($origin && ! $this->isMachineOrigin($origin)) {
                return $origin;
            }
        }

        foreach ($candidates as $candidate) {
            $origin = $this->normalizeOrigin($candidate);
            if ($origin) {
                return $origin;
            }
        }

        return 'https://playnova.ir';
    }

    protected function normalizeOrigin(string $value): ?string
    {
        $base = trim($value);
        if ($base === '') {
            return null;
        }

        if (! str_starts_with($base, 'http://') && ! str_starts_with($base, 'https://')) {
            $base = 'https://' . ltrim($base, '/');
        }

        if (str_starts_with($base, 'http://') && ! app()->environment('local')) {
            $base = 'https://' . substr($base, 7);
        }

        return rtrim($base, '/');
    }

    protected function isMachineOrigin(string $origin): bool
    {
        $host = parse_url($origin, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return true;
        }

        if (in_array(strtolower($host), ['localhost', '127.0.0.1'], true)) {
            return true;
        }

        return filter_var($host, FILTER_VALIDATE_IP) !== false;
    }

    public function startPaymentUrl(string $trackId): string
    {
        $trackId = preg_replace('/\D+/', '', $trackId);

        return self::BASE_URL . '/start/' . $trackId;
    }

    /**
     * Bridge URL on the registered site domain so the browser sends Referer to Zibal /start.
     */
    public function paymentBridgeUrl(string $trackId): string
    {
        $trackId = preg_replace('/\D+/', '', $trackId);

        return $this->siteOrigin() . '/wallet/pay/' . $trackId;
    }

    public function detectServerIp(): ?string
    {
        try {
            $response = Http::timeout(10)->get('https://help.zibal.ir/ip.php');
            if (! $response->successful()) {
                return null;
            }

            $ip = trim(strip_tags($response->body()));
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        } catch (\Throwable $e) {
            Log::warning('Unable to detect server IP for Zibal', ['message' => $e->getMessage()]);
        }

        $fallback = trim((string) Setting::getZibalServerIp());

        return $fallback !== '' ? $fallback : null;
    }

    public function testConnection(): array
    {
        $merchant = Setting::isZibalSandbox() ? 'zibal' : Setting::getZibalMerchantId();
        if (! $merchant) {
            return ['ok' => false, 'message' => 'مرچنت کد زیبال تنظیم نشده است.'];
        }

        $orderId = 'TEST' . random_int(100000, 999999);

        return $this->requestPayment(
            10000,
            $this->callbackUrl(),
            'PlayNova gateway test',
            null,
            $orderId
        );
    }

    public function requestPayment(
        int $amountToman,
        ?string $callbackUrl = null,
        string $description = 'شارژ کیف پول',
        ?string $mobile = null,
        ?string $orderId = null
    ): array {
        $merchant = Setting::getZibalMerchantId();
        if (! $merchant) {
            return ['ok' => false, 'message' => 'مرچنت کد زیبال تنظیم نشده است.'];
        }

        $callbackUrl = $callbackUrl ?: $this->callbackUrl();
        $amountRial = $amountToman * 10;

        if ($amountRial < 1000) {
            return ['ok' => false, 'message' => 'حداقل مبلغ پرداخت ۱۰۰۰ ریال (۱۰۰ تومان) است.'];
        }

        $payload = [
            'merchant' => $merchant,
            'amount' => $amountRial,
            'callbackUrl' => $callbackUrl,
            'description' => mb_substr($description, 0, 120),
        ];

        if ($orderId) {
            $payload['orderId'] = preg_replace('/[^A-Za-z0-9\-_]/', '', (string) $orderId);
        }

        $normalizedMobile = $this->normalizeMobile($mobile);
        if ($normalizedMobile) {
            $payload['mobile'] = $normalizedMobile;
        }

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(25)->post(self::BASE_URL . '/v1/request', $payload);
        } catch (\Throwable $e) {
            Log::warning('Zibal request failed', [
                'message' => $e->getMessage(),
                'merchant' => $merchant,
                'callbackUrl' => $callbackUrl,
            ]);

            return ['ok' => false, 'message' => 'خطا در اتصال به درگاه زیبال: ' . $e->getMessage()];
        }

        $body = $response->json();
        if (! is_array($body)) {
            Log::warning('Zibal invalid response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['ok' => false, 'message' => 'پاسخ نامعتبر از درگاه زیبال.'];
        }

        $result = (int) ($body['result'] ?? 0);
        $trackId = $body['trackId'] ?? null;

        Log::info('Zibal request', [
            'result' => $result,
            'trackId' => $trackId,
            'merchant' => $merchant,
            'callbackUrl' => $callbackUrl,
            'sandbox' => Setting::isZibalSandbox(),
        ]);

        if ($result !== 100 || ! $trackId) {
            return [
                'ok' => false,
                'message' => $this->humanizeResult($result, $body['message'] ?? null),
                'result_code' => $result,
            ];
        }

        return [
            'ok' => true,
            'track_id' => (string) $trackId,
            'redirect_url' => $this->paymentBridgeUrl((string) $trackId),
            'gateway_url' => $this->startPaymentUrl((string) $trackId),
        ];
    }

    public function verify(string $trackId): array
    {
        $merchant = Setting::getZibalMerchantId();
        if (! $merchant) {
            return ['ok' => false, 'message' => 'مرچنت کد زیبال تنظیم نشده است.'];
        }

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(25)->post(self::BASE_URL . '/v1/verify', [
                'merchant' => $merchant,
                'trackId' => $trackId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Zibal verify failed', ['message' => $e->getMessage(), 'trackId' => $trackId]);

            return ['ok' => false, 'message' => 'خطا در تأیید پرداخت زیبال.'];
        }

        $body = $response->json();
        if (! is_array($body)) {
            return ['ok' => false, 'message' => 'پاسخ نامعتبر از درگاه زیبال.'];
        }

        $result = (int) ($body['result'] ?? 0);

        if ($result === 100 || $result === 201) {
            return [
                'ok' => true,
                'ref_number' => $body['refNumber'] ?? null,
                'card_number' => $body['cardNumber'] ?? null,
            ];
        }

        return [
            'ok' => false,
            'message' => $this->humanizeResult($result, $body['message'] ?? null),
            'result_code' => $result,
        ];
    }

    protected function humanizeResult(int $result, ?string $message): string
    {
        $map = [
            102 => 'مرچنت کد زیبال یافت نشد. مرچنت را در پنل ادمین بررسی کنید.',
            103 => 'درگاه زیبال در پنل کاربری فعال نیست.',
            104 => 'مرچنت کد زیبال نامعتبر است.',
            105 => 'مبلغ پرداخت نامعتبر است.',
            106 => 'آدرس callback نامعتبر است. باید https://playnova.ir/wallet/callback باشد و در پنل زیبال ثبت شود.',
            113 => 'مبلغ از سقف مجاز بیشتر است.',
            115 => 'آی‌پی سرور در پنل زیبال ثبت نشده. IP سرور را در بخش درگاه پرداخت ادمین ببینید و در پنل زیبال ثبت کنید.',
            201 => 'این تراکنش قبلاً تأیید شده است.',
            202 => 'پرداخت ناموفق بود.',
            203 => 'شناسه پیگیری (trackId) نامعتبر است.',
        ];

        if (isset($map[$result])) {
            return $map[$result];
        }

        $message = trim((string) $message);

        return $message !== '' ? $message : 'درگاه پرداخت درخواست را رد کرد (کد: ' . $result . ').';
    }

    public function normalizeMobile(?string $mobile): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $mobile);
        if (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        }
        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            $digits = '0' . $digits;
        }

        return preg_match('/^09\d{9}$/', $digits) ? $digits : null;
    }
}
