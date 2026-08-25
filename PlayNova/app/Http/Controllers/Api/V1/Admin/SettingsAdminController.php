<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesAdmin;
use App\Models\Setting;
use App\Services\AvalAIService;
use App\Services\FaviconService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsAdminController extends BaseApiController
{
    use AuthorizesAdmin;

    public function logo(): JsonResponse
    {
        $this->authorizeAdmin();

        $logo = Setting::get('logo');

        return $this->success([
            'logo' => $logo,
            'logo_url' => $logo ? asset('storage/' . $logo) : null,
        ]);
    }

    public function updateLogo(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $request->validate(['logo' => 'required|image|max:2048']);

        $oldLogo = Setting::get('logo');
        if ($oldLogo && file_exists(storage_path('app/public/' . $oldLogo))) {
            unlink(storage_path('app/public/' . $oldLogo));
        }

        $path = $request->file('logo')->store('logo', 'public');
        Setting::set('logo', $path);

        $sourcePath = storage_path('app/public/' . $path);
        foreach ([
            dirname(base_path()) . '/playnova-logo.png',
            public_path('logo.png'),
        ] as $publicTarget) {
            @copy($sourcePath, $publicTarget);
        }
        FaviconService::regenerateFromFile($sourcePath);

        return $this->success(['logo' => $path, 'logo_url' => asset('storage/' . $path)], 'لوگو تغییر یافت.');
    }

    public function deleteLogo(): JsonResponse
    {
        $this->authorizeAdmin();

        $logo = Setting::get('logo');
        if ($logo && file_exists(storage_path('app/public/' . $logo))) {
            unlink(storage_path('app/public/' . $logo));
        }
        Setting::set('logo', null);

        return $this->success(null, 'لوگو به حالت پیش‌فرض بازگشت.');
    }

    public function paymentGateway(): JsonResponse
    {
        $this->authorizeAdmin();

        return $this->success([
            'merchant_id' => Setting::getZibalMerchantId(),
            'is_active' => Setting::isPaymentGatewayActive(),
            'sandbox' => Setting::isZibalSandbox(),
            'provider' => Setting::get('payment_gateway_provider', 'zibal'),
        ]);
    }

    public function updatePaymentGateway(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $request->validate([
            'merchant_id' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'sandbox' => 'nullable|boolean',
        ]);

        if ($request->filled('merchant_id')) {
            $merchantId = trim($request->merchant_id);
            if ($merchantId === '') {
                return $this->error('مرچنت کد زیبال نمی‌تواند خالی باشد.');
            }
            Setting::set('zibal_merchant_id', $merchantId);
        }

        Setting::set('payment_gateway_active', $request->boolean('is_active'));
        Setting::set('zibal_sandbox', $request->boolean('sandbox'));
        Setting::set('payment_gateway_provider', 'zibal');

        return $this->success(null, 'تنظیمات درگاه پرداخت ذخیره شد.');
    }

    public function testPaymentGateway(): JsonResponse
    {
        $this->authorizeAdmin();

        if (! Setting::isZibalConfigured()) {
            return $this->error('پیکربندی زیبال ناقص است.');
        }

        $merchantId = Setting::getZibalMerchantId();
        $mode = Setting::isZibalSandbox() ? 'Sandbox' : 'Production';
        $active = Setting::isPaymentGatewayActive() ? 'فعال' : 'غیرفعال';

        return $this->success(['message' => "پیکربندی زیبال معتبر است. مرچنت: {$merchantId} ({$mode}) — درگاه: {$active}"]);
    }

    public function smsSettings(): JsonResponse
    {
        $this->authorizeAdmin();

        return $this->success([
            'sms_provider' => Setting::getSmsProvider(),
            'sms_username' => Setting::getSmsUsername(),
            'sms_sender' => Setting::getSmsSender(),
            'sms_patterns' => Setting::getSmsPatterns(),
            'sms_register_verify' => (bool) Setting::get('sms_register_verify', false),
            'has_api_key' => filled(Setting::getSmsApiKey()),
        ]);
    }

    public function updateSmsSettings(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $request->validate([
            'sms_provider' => 'required|in:test,melipayamak',
            'sms_username' => 'nullable|string|max:100',
            'sms_api_key' => 'nullable|string|max:255',
            'sms_sender' => 'nullable|string|max:20',
            'sms_patterns' => 'nullable|array',
            'sms_patterns.*.key' => 'required_with:sms_patterns|string|max:50|regex:/^[a-z0-9_\-]+$/i',
            'sms_patterns.*.title' => 'nullable|string|max:100',
            'sms_patterns.*.body_id' => 'nullable|integer|min:1',
            'sms_patterns.*.variables' => 'nullable|string|max:200',
            'sms_register_verify' => 'nullable|boolean',
        ]);

        $provider = $request->input('sms_provider', 'test');

        if ($provider === 'melipayamak') {
            $request->validate([
                'sms_username' => 'required|string|max:100',
                'sms_sender' => 'required|string|max:20',
            ]);

            if (! $request->filled('sms_api_key') && ! Setting::getSmsApiKey()) {
                return $this->error('برای فعال‌سازی ارسال واقعی، توکن REST پنل را وارد کنید.');
            }
        }

        Setting::set('sms_provider', $provider);
        Setting::set('sms_active', $provider === 'melipayamak');
        Setting::set('sms_register_test_mode', $provider === 'test');

        if ($request->filled('sms_username')) {
            Setting::set('sms_username', $request->sms_username);
        }
        if ($request->filled('sms_api_key')) {
            Setting::set('sms_api_key', $request->sms_api_key);
        }
        if ($request->filled('sms_sender')) {
            Setting::set('sms_sender', $request->sms_sender);
        }

        $patterns = collect($request->input('sms_patterns', []))
            ->filter(fn ($p) => is_array($p) && filled($p['key'] ?? null))
            ->values()
            ->all();

        if ($patterns !== []) {
            Setting::setSmsPatterns($patterns);
        }

        Setting::set('sms_register_verify', $request->boolean('sms_register_verify'));

        $message = $provider === 'melipayamak'
            ? 'ارسال واقعی ملی‌پیامک فعال شد.'
            : 'حالت تست فعال است.';

        return $this->success(null, $message);
    }

    public function referralSettings(): JsonResponse
    {
        $this->authorizeAdmin();

        return $this->success([
            'bonus_percent' => (float) Setting::get('referral_bonus_percent', 5),
        ]);
    }

    public function updateReferralSettings(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $request->validate(['bonus_percent' => 'required|numeric|min:0|max:100']);
        Setting::set('referral_bonus_percent', $request->bonus_percent);

        return $this->success(null, 'تنظیمات دعوت ذخیره شد.');
    }

    public function aiSettings(): JsonResponse
    {
        $this->authorizeAdmin();

        return $this->success([
            'base_url' => Setting::get('avalai_base_url') ?: config('services.avalai.base_url'),
            'vision_model' => Setting::get('avalai_vision_model') ?: config('services.avalai.vision_model', 'gpt-4o'),
            'timeout' => Setting::getAvalAiTimeout(),
            'is_active' => Setting::isAvalAiActive(),
            'has_api_key' => filled(Setting::getAvalAiApiKey()),
            'api_key_source' => Setting::avalAiApiKeySource(),
            'suggested_models' => [
                'gpt-4o',
                'gpt-4o-mini',
                'gpt-4.1',
                'gpt-4.1-mini',
                'claude-sonnet-4-20250514',
            ],
        ]);
    }

    public function updateAiSettings(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $request->validate([
            'base_url' => 'nullable|string|max:255',
            'vision_model' => 'required|string|max:100',
            'timeout' => 'nullable|integer|min:30|max:300',
            'api_key' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
            'clear_api_key' => 'nullable|boolean',
        ]);

        if ($request->has('base_url')) {
            $baseUrl = trim((string) $request->base_url);
            Setting::set('avalai_base_url', $baseUrl !== '' ? rtrim($baseUrl, '/') : null);
        }

        Setting::set('avalai_vision_model', trim($request->vision_model));
        Setting::set('avalai_active', $request->boolean('is_active', true));

        if ($request->filled('timeout')) {
            Setting::set('avalai_timeout', (int) $request->timeout);
        }

        if ($request->boolean('clear_api_key')) {
            Setting::set('avalai_api_key', null);
        }

        if ($request->filled('api_key')) {
            Setting::set('avalai_api_key', trim((string) $request->api_key));
        }

        return $this->success(null, 'تنظیمات هوش مصنوعی ذخیره شد.');
    }

    public function testAiSettings(AvalAIService $avalai): JsonResponse
    {
        $this->authorizeAdmin();

        if (! $avalai->isConfigured()) {
            return $this->error('کلید API یا سرویس هوش مصنوعی فعال نیست.');
        }

        try {
            $reply = $avalai->chatWithVision(
                [['type' => 'text', 'text' => 'Reply with exactly: OK']],
                'You are a connectivity test. Reply with exactly OK and nothing else.',
            );

            return $this->success([
                'model' => Setting::getAvalAiVisionModel(),
                'response' => mb_substr($reply, 0, 200),
            ], 'اتصال به AvalAI با موفقیت برقرار شد.');
        } catch (\Throwable $e) {
            return $this->error('تست اتصال ناموفق: ' . $e->getMessage());
        }
    }
}
