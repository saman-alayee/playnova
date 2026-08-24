<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZarinpalGatewayService
{
    public function isEnabled(): bool
    {
        return Setting::isPaymentGatewayActive() && Setting::isZarinpalConfigured();
    }

    public function baseUrl(): string
    {
        return Setting::isZarinpalSandbox()
            ? 'https://sandbox.zarinpal.com/pg/v4/payment'
            : 'https://api.zarinpal.com/pg/v4/payment';
    }

    public function startPayUrl(string $authority): string
    {
        $host = Setting::isZarinpalSandbox()
            ? 'https://sandbox.zarinpal.com'
            : 'https://www.zarinpal.com';

        return $host . '/pg/StartPay/' . $authority;
    }

    public function requestPayment(int $amountToman, string $callbackUrl, string $description, ?string $mobile = null, ?string $email = null): array
    {
        $merchantId = $this->resolveMerchantId();
        if (! $merchantId) {
            return ['ok' => false, 'message' => 'مرچنت آیدی زرین‌پال تنظیم نشده است.'];
        }

        $payload = [
            'merchant_id' => $merchantId,
            'amount' => $amountToman,
            'currency' => 'IRT',
            'callback_url' => $callbackUrl,
            'description' => $description,
        ];

        $metadata = array_filter([
            'mobile' => $mobile ? $this->normalizeMobile($mobile) : null,
            'email' => $email,
        ]);
        if ($metadata !== []) {
            $payload['metadata'] = $metadata;
        }

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(25)->post($this->baseUrl() . '/request.json', $payload);
        } catch (\Throwable $e) {
            Log::warning('Zarinpal request failed', ['message' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'خطا در اتصال به درگاه زرین‌پال: ' . $e->getMessage()];
        }

        $body = $response->json();
        if (! $response->successful()) {
            $message = $body['errors']['message'] ?? $body['errors'][0]['message'] ?? 'درگاه پرداخت درخواست را رد کرد.';

            return ['ok' => false, 'message' => $message];
        }

        $code = (int) ($body['data']['code'] ?? 0);
        $authority = $body['data']['authority'] ?? null;

        if ($code !== 100 || ! $authority) {
            $message = $body['data']['message'] ?? $body['errors'][0]['message'] ?? 'ایجاد تراکنش زرین‌پال ناموفق بود.';

            return ['ok' => false, 'message' => $message];
        }

        return [
            'ok' => true,
            'authority' => $authority,
            'redirect_url' => $this->startPayUrl($authority),
        ];
    }

    public function verify(string $authority, int $amountToman): array
    {
        $merchantId = $this->resolveMerchantId();
        if (! $merchantId) {
            return ['ok' => false, 'message' => 'مرچنت آیدی زرین‌پال تنظیم نشده است.'];
        }

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(25)->post($this->baseUrl() . '/verify.json', [
                'merchant_id' => $merchantId,
                'amount' => $amountToman,
                'authority' => $authority,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Zarinpal verify failed', ['message' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'خطا در تأیید پرداخت زرین‌پال.'];
        }

        $body = $response->json();
        if (! $response->successful()) {
            return ['ok' => false, 'message' => 'تأیید پرداخت توسط زرین‌پال رد شد.'];
        }

        $code = (int) ($body['data']['code'] ?? -1);

        if ($code === 100 || $code === 101) {
            return [
                'ok' => true,
                'ref_id' => $body['data']['ref_id'] ?? null,
                'card_pan' => $body['data']['card_pan'] ?? null,
            ];
        }

        $message = $body['data']['message'] ?? $body['errors'][0]['message'] ?? 'پرداخت تأیید نشد.';

        return ['ok' => false, 'message' => $message];
    }

    public function resolveMerchantId(): ?string
    {
        foreach ([Setting::getZarinpalMerchantId(), Setting::getZarinpalApiKey()] as $candidate) {
            $normalized = $this->normalizeMerchantId($candidate);
            if ($normalized) {
                return $normalized;
            }
        }

        return null;
    }

    protected function normalizeMerchantId(?string $raw): ?string
    {
        $id = strtolower(trim((string) $raw));
        if ($id === '') {
            return null;
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id)) {
            return $id;
        }

        if (preg_match('/^[0-9a-f]{32}$/', $id)) {
            return substr($id, 0, 8) . '-'
                . substr($id, 8, 4) . '-'
                . substr($id, 12, 4) . '-'
                . substr($id, 16, 4) . '-'
                . substr($id, 20, 12);
        }

        if (preg_match('/^[0-9a-f]{24}$/', $id)) {
            $id = str_pad($id, 32, '0', STR_PAD_RIGHT);

            return substr($id, 0, 8) . '-'
                . substr($id, 8, 4) . '-'
                . substr($id, 12, 4) . '-'
                . substr($id, 16, 4) . '-'
                . substr($id, 20, 12);
        }

        return strlen($id) >= 36 ? $id : null;
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
