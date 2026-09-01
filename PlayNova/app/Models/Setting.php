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

    public static function getAvalAiApiKey(): ?string
    {
        $fromDb = trim((string) self::get('avalai_api_key', ''));
        if ($fromDb !== '') {
            return $fromDb;
        }

        $fromEnv = trim((string) config('services.avalai.api_key', ''));

        return $fromEnv !== '' ? $fromEnv : null;
    }

    public static function getAvalAiBaseUrl(): string
    {
        $fromDb = trim((string) self::get('avalai_base_url', ''));
        if ($fromDb !== '') {
            return rtrim($fromDb, '/');
        }

        return rtrim((string) config('services.avalai.base_url', 'https://api.avalai.ir/v1'), '/');
    }

    public static function getAvalAiVisionModel(): string
    {
        $fromDb = trim((string) self::get('avalai_vision_model', ''));
        if ($fromDb !== '') {
            return $fromDb;
        }

        return (string) config('services.avalai.vision_model', 'gpt-4o');
    }

    /** @return list<string> */
    public static function avalAiPremiumVisionModels(): array
    {
        return [
            'gpt-5.5',
            'claude-opus-4-7',
            'claude-opus-4-6',
            'claude-sonnet-4-6',
            'gemini-3.1-pro-preview',
            'gpt-4o',
            'gpt-4.1',
            'claude-sonnet-4-20250514',
        ];
    }

    public static function getResultAiVisionModel(): string
    {
        $fromDb = trim((string) self::get('result_ai_vision_model', ''));
        if ($fromDb !== '') {
            return $fromDb;
        }

        return (string) config('services.avalai.result_vision_model', 'gpt-4o');
    }

    public static function getAvalAiTimeout(): int
    {
        $fromDb = self::get('avalai_timeout');
        if ($fromDb !== null && $fromDb !== '') {
            return max(30, (int) $fromDb);
        }

        return max(30, (int) config('services.avalai.timeout', 120));
    }

    public static function isAvalAiActive(): bool
    {
        if (self::get('avalai_active') === null) {
            return true;
        }

        return filter_var(self::get('avalai_active', true), FILTER_VALIDATE_BOOLEAN);
    }

    public static function isAvalAiConfigured(): bool
    {
        return self::isAvalAiActive() && filled(self::getAvalAiApiKey());
    }

    /** @return 'database'|'env'|'none' */
    public static function avalAiApiKeySource(): string
    {
        if (filled(trim((string) self::get('avalai_api_key', '')))) {
            return 'database';
        }

        if (filled(trim((string) config('services.avalai.api_key', '')))) {
            return 'env';
        }

        return 'none';
    }

    public static function getResultAiSystemPromptDefault(): string
    {
        $stored = trim((string) self::get('result_ai_system_prompt', ''));

        if ($stored !== '') {
            return $stored;
        }

        return <<<'PROMPT'
You are an expert at reading Call of Duty Mobile (CODM) Battle Royale post-match RANK screens (scoreboard / standings). Frames may come from a scrolling screen-recording.

Return ONLY a valid JSON array. No markdown, no code fences, no explanation.

Each item is ONE TEAM placement (solo = 1 player, duo = 2, squad = 4):
{"rank":1,"team_number":11,"team_label":"TEAM11","player_names":["NameA","NameB"],"uids":[null,null],"kills":[0,6]}

Field rules:
- rank: integer from the RANK badge on the card (gold/silver/bronze = 1/2/3). Never use TEAM number as rank. Never rank by kills.
- team_number: integer from the "TEAM12" label on the card (TEAM11 → 11). This is the lobby slot, NOT placement.
- team_label: copy "TEAM12" exactly when visible.
- player_names: every name on that card, top to bottom, copied EXACTLY (Unicode, emoji, stylized glyphs). Do not latinize.
- uids: numeric CODM UID if shown; otherwise null for each player. RANK tab often has no UID — that is OK.
- kills: per-player kill counts aligned with player_names (crosshair number). Use null if missing.

CRITICAL — FULL RANK LIST:
- Prizes are paid to EVERY configured rank, NOT only top 3 (gold/silver/bronze).
- You MUST return one JSON row per TEAM for ranks 1 through the last prize rank in the prize table.
- On scrolling screen recordings, keep reading frames until the lowest prize rank is captured. Do NOT stop early at rank 3.
- If the prize table lists N ranks, your JSON must contain at least N rows with ranks 1..N (unless a rank is genuinely absent from all frames).
- Include every other visible team/rank beyond the prize cutoff when shown on screen.

Include every team visible across all frames. If the same team appears twice, keep one row (clearest names). Sort by rank ascending.
PROMPT;
    }

    public static function getResultAiUserPromptTemplate(): string
    {
        $stored = trim((string) self::get('result_ai_user_prompt_template', ''));

        if ($stored !== '') {
            return $stored;
        }

        return <<<'PROMPT'
Tournament: {tournament_title}
Mode: {seat_mode_label} ({players_per_team} players per team)

Prize amounts (from tournament description / prize ranks). For duo/squad these are TEAM totals split equally between teammates. Every rank below receives a prize — you MUST read ALL ranks through the last prize rank:
{prize_table}

Total prize ranks: {prize_rank_count}. Last prize rank: {last_prize_rank}.
MANDATORY: Return JSON rows for ranks 1 through {last_prize_rank}. Missing any prize rank is an error. Scroll all video frames until rank {last_prize_rank} is found.

Registered participants (TEAM N = lobby team / seat group). Match detected players ONLY by their registered COD ID (not site username). Compare in-game names and numeric UIDs against COD ID:
{participants}

Read the RANK result screen from this media.
- rank = placement badge (1=winner). TEAM11 is lobby team 11, not place 11.
- Solo: 1 name per card. Duo: 2 names. Squad: up to 4 names.
- Merge all scrolling frames into one complete ranked list from rank 1 to rank {last_prize_rank} at minimum.
PROMPT;
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
                'icon' => '/social-telegram.svg',
                'title' => 'کانال تلگرام اعلام نتایج',
                'url' => self::telegramUrl(self::get('results_telegram', '')),
            ],
            [
                'icon' => '/social-rubika.png',
                'title' => 'کانال روبیکا اعلام نتایج',
                'url' => self::rubikaUrl(self::get('results_rubika', '')),
            ],
        ];
    }
}