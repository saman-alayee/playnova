<?php

namespace App\Modules\Content\Services;

use App\Models\Rule;
use App\Models\Setting;
use App\Services\FaqData;
use App\Modules\Tournament\Services\TournamentListingService;
use Illuminate\Support\Facades\Cache;

class ContentCacheService
{
    private const TTL_SECONDS = 1800;

    public function privacyContent(): string
    {
        return Cache::remember('content:privacy', self::TTL_SECONDS, fn () => Setting::get(
            'privacy_content',
            'متن حریم خصوصی PlayNova به‌زودی در این بخش قرار می‌گیرد.'
        ));
    }

    public function aboutContent(): string
    {
        return Cache::remember('content:about', self::TTL_SECONDS, fn () => Setting::get(
            'about_content',
            'متن درباره ما به‌زودی در این بخش قرار می‌گیرد.'
        ));
    }

    public function contactInfo(): array
    {
        return Cache::remember('content:contact', self::TTL_SECONDS, fn () => [
            'email' => Setting::get('contact_email', 'support@playnova.ir'),
            'phone' => Setting::get('contact_phone', ''),
            'address' => Setting::get('contact_address', ''),
        ]);
    }

    public function rules()
    {
        return Cache::remember('content:rules', self::TTL_SECONDS, fn () => Rule::orderBy('id')->get());
    }

    public function faq(?string $category = null): array
    {
        $key = 'content:faq:' . ($category ?: 'all');

        return Cache::remember($key, self::TTL_SECONDS, function () use ($category) {
            $categories = FaqData::categories();
            $activeKey = $category;
            if ($activeKey && ! isset($categories[$activeKey])) {
                $activeKey = null;
            }

            return [
                'categories' => $categories,
                'active_key' => $activeKey,
                'active_category' => $activeKey ? $categories[$activeKey] : null,
                'support_phone' => trim((string) Setting::get('contact_phone', '')),
            ];
        });
    }

    public static function forgetAll(): void
    {
        foreach (['content:privacy', 'content:about', 'content:contact', 'content:rules'] as $key) {
            Cache::forget($key);
        }

        foreach (array_keys(FaqData::categories()) as $category) {
            Cache::forget('content:faq:' . $category);
        }

        Cache::forget('content:faq:all');
        TournamentListingService::forgetHomeCache();
    }
}
