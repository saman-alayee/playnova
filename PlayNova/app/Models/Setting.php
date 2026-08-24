<?php

namespace App\Models;

use App\Modules\Content\Services\ContentCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    private const CACHE_PREFIX = 'setting:';

    private const CACHE_TTL_SECONDS = 3600;

    public static function get($key, $default = null)
    {
        return Cache::remember(
            self::CACHE_PREFIX.$key,
            self::CACHE_TTL_SECONDS,
            function () use ($key, $default) {
                $setting = self::where('key', $key)->first();

                return $setting ? $setting->value : $default;
            }
        );
    }

    private const CONTENT_CACHE_KEYS = [
        'privacy_content',
        'about_content',
        'contact_email',
        'contact_phone',
        'contact_address',
    ];

    public static function set($key, $value)
    {
        $result = self::updateOrCreate(['key' => $key], ['value' => $value]);
        self::forget($key);

        if (in_array($key, self::CONTENT_CACHE_KEYS, true)) {
            ContentCacheService::forgetAll();
        }

        return $result;
    }

    public static function forget(string $key): void
    {
        Cache::forget(self::CACHE_PREFIX.$key);
    }

    public static function clearCache(): void
    {
        $keys = self::query()->pluck('key');

        foreach ($keys as $key) {
            Cache::forget(self::CACHE_PREFIX.$key);
        }
    }

    public static function isPaymentGatewayActive(): bool
    {
        return filter_var(self::get('payment_gateway_active', false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function getZibalMerchantCode(): ?string
    {
        $merchant = trim((string) self::get('zibal_merchant_id'));
        if ($merchant !== '') {
            return $merchant;
        }

        return trim((string) self::get('zarinpal_merchant_id')) ?: null;
    }

    public static function getZibalMerchantId(): ?string
    {
        if (self::isZibalSandbox()) {
            return 'zibal';
        }

        $merchant = self::getZibalMerchantCode();

        return $merchant !== '' ? $merchant : null;
    }

    public static function isZibalSandbox(): bool
    {
        if (self::get('zibal_sandbox') !== null) {
            return filter_var(self::get('zibal_sandbox', false), FILTER_VALIDATE_BOOLEAN);
        }

        return filter_var(self::get('zarinpal_sandbox', false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function isZibalConfigured(): bool
    {
        if (self::isZibalSandbox()) {
            return true;
        }

        return filled(self::getZibalMerchantCode());
    }

    public static function getZibalApiKey(): ?string
    {
        $key = trim((string) self::get('zibal_api_key', ''));

        return $key !== '' ? $key : null;
    }

    public static function getZibalServerIp(): ?string
    {
        $ip = trim((string) self::get('zibal_server_ip', ''));

        return $ip !== '' ? $ip : null;
    }

    public static function isSmsActive()
    {
        return self::get('sms_active', false) == true;
    }

    public static function getSmsProvider()
    {
        return self::get('sms_provider', 'test');
    }

    public static function isSmsTestMode()
    {
        return self::getSmsProvider() === 'test';
    }

    public static function getSmsUsername()
    {
        return self::get('sms_username');
    }

    public static function getSmsApiKey()
    {
        return self::get('sms_api_key');
    }

    public static function getSmsSender()
    {
        return self::get('sms_sender', '1000');
    }

    public static function getSmsPatternId(): ?int
    {
        return self::getSmsRegisterPatternId();
    }

    public static function getSmsRegisterPatternId(): ?int
    {
        $value = self::get('sms_pattern_register_id') ?: self::get('sms_pattern_id');

        return filled($value) ? (int) $value : null;
    }

    public static function getSmsResetPatternId(): ?int
    {
        $value = self::get('sms_pattern_reset_id');

        return filled($value) ? (int) $value : null;
    }

    public static function getSmsPatterns(): array
    {
        $raw = self::get('sms_patterns');
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $patterns = array_values(array_filter($decoded, fn ($p) => is_array($p) && filled($p['key'] ?? null)));
                if ($patterns !== []) {
                    return $patterns;
                }
            }
        }

        return self::defaultSmsPatterns();
    }

    public static function defaultSmsPatterns(): array
    {
        return [
            [
                'key' => 'register',
                'title' => 'ثبت‌نام / تأیید موبایل',
                'body_id' => self::get('sms_pattern_register_id') ?: self::get('sms_pattern_id'),
                'variables' => 'code',
            ],
            [
                'key' => 'reset',
                'title' => 'فراموشی رمز عبور',
                'body_id' => self::get('sms_pattern_reset_id'),
                'variables' => 'code',
            ],
        ];
    }

    public static function setSmsPatterns(array $patterns): void
    {
        $normalized = [];
        foreach ($patterns as $pattern) {
            if (! is_array($pattern) || ! filled($pattern['key'] ?? null)) {
                continue;
            }
            $normalized[] = [
                'key' => trim((string) $pattern['key']),
                'title' => trim((string) ($pattern['title'] ?? $pattern['key'])),
                'body_id' => filled($pattern['body_id'] ?? null) ? (int) $pattern['body_id'] : null,
                'variables' => trim((string) ($pattern['variables'] ?? 'code')) ?: 'code',
            ];
        }

        self::set('sms_patterns', json_encode($normalized, JSON_UNESCAPED_UNICODE));

        foreach ($normalized as $pattern) {
            if ($pattern['key'] === 'register' && $pattern['body_id']) {
                self::set('sms_pattern_register_id', $pattern['body_id']);
            }
            if ($pattern['key'] === 'reset' && $pattern['body_id']) {
                self::set('sms_pattern_reset_id', $pattern['body_id']);
            }
        }
    }

    public static function getSmsPatternBodyId(string $purpose): ?int
    {
        foreach (self::getSmsPatterns() as $pattern) {
            if (($pattern['key'] ?? '') === $purpose && filled($pattern['body_id'] ?? null)) {
                return (int) $pattern['body_id'];
            }
        }

        if ($purpose === 'reset') {
            $legacy = self::get('sms_pattern_reset_id');

            return filled($legacy) ? (int) $legacy : null;
        }

        $legacy = self::get('sms_pattern_register_id') ?: self::get('sms_pattern_id');

        return filled($legacy) ? (int) $legacy : null;
    }

    /** @return list<string> */
    public static function getSmsPatternVariables(string $purpose): array
    {
        foreach (self::getSmsPatterns() as $pattern) {
            if (($pattern['key'] ?? '') === $purpose) {
                $vars = trim((string) ($pattern['variables'] ?? 'code'));

                return array_values(array_filter(array_map('trim', explode(';', $vars ?: 'code'))));
            }
        }

        return ['code'];
    }

    public static function isSmsRegisterVerifyEnabled()
    {
        return self::get('sms_register_verify', false) == true;
    }

    public static function isSmsRegisterTestMode()
    {
        return self::get('sms_register_test_mode', false) == true;
    }

    public static function isMelipayamakConfigured(): bool
    {
        return filled(self::getSmsUsername())
            && filled(self::getSmsApiKey())
            && filled(self::getSmsSender());
    }

    public static function getReferralBonusPercent()
    {
        return (float) self::get('referral_bonus_percent', 5);
    }

    public static function logoUrl(): string
    {
        $logo = self::get('logo');
        if ($logo) {
            return asset('storage/' . ltrim($logo, '/'));
        }

        if (file_exists(public_path('logo.png'))) {
            return asset('logo.png');
        }

        return asset('playnova-logo.png');
    }

    public static function socialLinks(): array
    {
        return [
            'telegram' => self::get('social_telegram', ''),
            'rubika' => self::get('social_rubika', ''),
            'instagram' => self::get('social_instagram', ''),
        ];
    }

    public static function telegramUrl(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return str_starts_with($value, 'http')
            ? $value
            : 'https://t.me/' . ltrim($value, '@');
    }

    public static function rubikaUrl(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return str_starts_with($value, 'http')
            ? $value
            : 'https://rubika.ir/' . ltrim($value, '@');
    }

    public static function resultsChannelItems(): array
    {
        return [
            [
                'icon' => url('/social-telegram.svg'),
                'title' => 'کانال تلگرام اعلام نتایج',
                'url' => self::telegramUrl(self::get('results_telegram', '')),
            ],
            [
                'icon' => url('/social-rubika.png'),
                'title' => 'کانال روبیکا اعلام نتایج',
                'url' => self::rubikaUrl(self::get('results_rubika', '')),
            ],
        ];
    }
}