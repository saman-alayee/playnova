<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesAdmin;
use App\Models\Setting;
use App\Services\AvalAIService;
use App\Services\AvalAiModelCatalog;
use App\Services\FaviconService;
use App\Services\ZibalGatewayService;
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
            'logo_url' => Setting::logoUrl(),
            'has_custom' => filled($logo),
        ]);
    }

    public function updateLogo(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $request->validate([
            'logo' => 'required|file|mimes:jpeg,jpg,png,svg,webp|max:2048',
        ]);

        $oldLogo = Setting::get('logo');
        if ($oldLogo && file_exists(storage_path('app/public/' . $oldLogo))) {
            unlink(storage_path('app/public/' . $oldLogo));
        }

        $path = $request->file('logo')->store('logo', 'public');
        Setting::set('logo', $path);

        $sourcePath = storage_path('app/public/' . $path);
        foreach (self::logoPublicTargets() as $publicTarget) {
            @copy($sourcePath, $publicTarget);
        }
        FaviconService::regenerateFromFile($sourcePath);

        return $this->success([
            'logo' => $path,
            'logo_url' => Setting::logoUrl(),
            'has_custom' => true,
        ], 'لوگو تغییر یافت.');
    }

    public function deleteLogo(): JsonResponse
    {
        $this->authorizeAdmin();

        $logo = Setting::get('logo');
        if ($logo && file_exists(storage_path('app/public/' . $logo))) {
            unlink(storage_path('app/public/' . $logo));
        }
        Setting::set('logo', null);

        $defaultSource = dirname(base_path()) . '/frontend/public/playnova-logo.png';
        if (file_exists($defaultSource)) {
            foreach (self::logoPublicTargets() as $publicTarget) {
                @copy($defaultSource, $publicTarget);
            }
            FaviconService::regenerateFromFile($defaultSource);
        }

        return $this->success([
            'logo' => null,
            'logo_url' => Setting::logoUrl(),
            'has_custom' => false,
        ], 'لوگو به حالت پیش‌فرض بازگشت.');
    }

    /** @return list<string> */
    private static function logoPublicTargets(): array
    {
        $root = dirname(base_path());

        return [
            $root . '/playnova-logo.png',
            $root . '/frontend/public/logo.png',
            $root . '/frontend/public/playnova-logo.png',
            public_path('logo.png'),
            public_path('playnova-logo.png'),
        ];
    }

    public function paymentGateway(ZibalGatewayService $zibal): JsonResponse
    {
        $this->authorizeAdmin();

        $detectedIp = $zibal->detectServerIp();
        $storedIp = Setting::getZibalServerIp();

        return $this->success([
            'merchant_id' => Setting::getZibalMerchantCode() ?? '',
            'is_active' => Setting::isPaymentGatewayActive(),
            'sandbox' => Setting::isZibalSandbox(),
            'provider' => Setting::get('payment_gateway_provider', 'zibal'),
            'callback_url' => $zibal->callbackUrl(),
            'server_ip' => $storedIp ?: $detectedIp,
            'detected_server_ip' => $detectedIp,
            'has_api_key' => filled(Setting::getZibalApiKey()),
        ]);
    }

    public function updatePaymentGateway(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $request->validate([
            'merchant_id' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'sandbox' => 'nullable|boolean',
            'zibal_api_key' => 'nullable|string|max:255',
            'zibal_server_ip' => 'nullable|string|max:45',
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

        if ($request->filled('zibal_api_key')) {
            Setting::set('zibal_api_key', trim((string) $request->zibal_api_key));
        }

        if ($request->has('zibal_server_ip')) {
            $ip = trim((string) $request->zibal_server_ip);
            Setting::set('zibal_server_ip', $ip !== '' ? $ip : null);
        }

        return $this->success(null, 'تنظیمات درگاه پرداخت زیبال با موفقیت ذخیره شد.');
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

    public function aiSettings(AvalAIService $avalai): JsonResponse
    {
        $this->authorizeAdmin();

        $fallbackModels = [
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-4.1',
            'gpt-4.1-mini',
            'claude-sonnet-4-20250514',
        ];

        $availableModels = $fallbackModels;
        if ($avalai->isConfigured()) {
            try {
                $fetched = $avalai->listModels();
                if ($fetched !== []) {
                    $availableModels = $fetched;
                }
            } catch (\Throwable) {
                // Keep fallback list when API is unreachable.
            }
        }

        $visionModel = Setting::get('avalai_vision_model') ?: config('services.avalai.vision_model', 'gpt-4o');
        $resultVisionModel = Setting::getResultAiVisionModel();

        foreach ([$visionModel, $resultVisionModel] as $selected) {
            if ($selected && ! in_array($selected, $availableModels, true)) {
                array_unshift($availableModels, $selected);
            }
        }

        [$credit, $creditError] = $this->avalAiCreditPayload($avalai);

        return $this->success([
            'base_url' => Setting::get('avalai_base_url') ?: config('services.avalai.base_url'),
            'vision_model' => $visionModel,
            'result_vision_model' => $resultVisionModel,
            'timeout' => Setting::getAvalAiTimeout(),
            'is_active' => Setting::isAvalAiActive(),
            'has_api_key' => filled(Setting::getAvalAiApiKey()),
            'api_key_source' => Setting::avalAiApiKeySource(),
            'available_models' => array_values(array_unique($availableModels)),
            'premium_models' => AvalAiModelCatalog::premiumModelIds(),
            'model_categories' => AvalAiModelCatalog::categorize($availableModels),
            'recommended_result_model' => 'gpt-5.5',
            'suggested_models' => $fallbackModels,
            'credit' => $credit,
            'credit_error' => $creditError,
        ]);
    }

    public function aiCredit(AvalAIService $avalai): JsonResponse
    {
        $this->authorizeAdmin();

        if (! filled(Setting::getAvalAiApiKey())) {
            return $this->error('کلید API سرویس AvalAI تنظیم نشده است.');
        }

        try {
            return $this->success($avalai->getCredit());
        } catch (\Throwable $e) {
            return $this->error('دریافت موجودی اعتبار ناموفق: '.$e->getMessage());
        }
    }

    public function aiModels(AvalAIService $avalai): JsonResponse
    {
        $this->authorizeAdmin();

        if (! $avalai->isConfigured()) {
            return $this->error('کلید API یا سرویس هوش مصنوعی فعال نیست.');
        }

        try {
            $models = $avalai->listModels();

            return $this->success([
                'models' => $models,
                'model_categories' => AvalAiModelCatalog::categorize($models),
            ]);
        } catch (\Throwable $e) {
            return $this->error('دریافت لیست مدل‌ها ناموفق: ' . $e->getMessage());
        }
    }

    public function updateAiSettings(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $request->validate([
            'base_url' => 'nullable|string|max:255',
            'vision_model' => 'required|string|max:100',
            'result_vision_model' => 'nullable|string|max:100',
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

        if ($request->filled('result_vision_model')) {
            Setting::set('result_ai_vision_model', trim((string) $request->result_vision_model));
        }

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

    /**
     * @return array{0: array<string, mixed>|null, 1: string|null}
     */
    private function avalAiCreditPayload(AvalAIService $avalai): array
    {
        if (! filled(Setting::getAvalAiApiKey())) {
            return [null, null];
        }

        try {
            return [$avalai->getCredit(), null];
        } catch (\Throwable $e) {
            return [null, $e->getMessage()];
        }
    }
}
