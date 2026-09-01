<?php

namespace App\Services;

class AvalAiModelCatalog
{
    public const TIER_ECONOMY = 'economy';

    public const TIER_BALANCED = 'balanced';

    public const TIER_PREMIUM = 'premium';

    public const TIER_OTHER = 'other';

    /** @return array<string, array{id: string, label: string, description: string, sort: int}> */
    public static function tierDefinitions(): array
    {
        return [
            self::TIER_ECONOMY => [
                'id' => self::TIER_ECONOMY,
                'label' => 'ارزان — کم‌هزینه',
                'description' => 'مناسب تست اتصال و کارهای ساده. سرعت بالا، هزینه پایین.',
                'sort' => 1,
            ],
            self::TIER_BALANCED => [
                'id' => self::TIER_BALANCED,
                'label' => 'متعادل — بهینه',
                'description' => 'تعادل خوب بین کیفیت، سرعت و هزینه. برای اکثر کارها پیشنهاد می‌شود.',
                'sort' => 2,
            ],
            self::TIER_PREMIUM => [
                'id' => self::TIER_PREMIUM,
                'label' => 'پریمیوم — گران / بهترین کیفیت',
                'description' => 'بالاترین دقت، مخصوصاً برای تحلیل نتیجه مسابقه و خواندن همه رتبه‌های جایزه.',
                'sort' => 3,
            ],
            self::TIER_OTHER => [
                'id' => self::TIER_OTHER,
                'label' => 'سایر مدل‌ها',
                'description' => 'مدل‌های دریافت‌شده از API که در فهرست پیش‌فرض نیستند.',
                'sort' => 4,
            ],
        ];
    }

    /**
     * @return array<string, array{tier: string, label_fa: string, note_fa: string, recommended_for: list<string>}>
     */
    public static function knownModels(): array
    {
        return [
            'gpt-4o-mini' => [
                'tier' => self::TIER_ECONOMY,
                'label_fa' => 'GPT-4o Mini',
                'note_fa' => 'ارزان‌ترین گزینه OpenAI — مناسب تست',
                'recommended_for' => ['vision'],
            ],
            'gpt-4.1-mini' => [
                'tier' => self::TIER_ECONOMY,
                'label_fa' => 'GPT-4.1 Mini',
                'note_fa' => 'نسخه سبک — هزینه کم',
                'recommended_for' => ['vision'],
            ],
            'gpt-4o' => [
                'tier' => self::TIER_BALANCED,
                'label_fa' => 'GPT-4o',
                'note_fa' => 'کیفیت خوب با هزینه معقول',
                'recommended_for' => ['vision', 'result'],
            ],
            'gpt-4.1' => [
                'tier' => self::TIER_BALANCED,
                'label_fa' => 'GPT-4.1',
                'note_fa' => 'دقت بالاتر از 4o-mini — هزینه متوسط',
                'recommended_for' => ['vision', 'result'],
            ],
            'claude-sonnet-4-20250514' => [
                'tier' => self::TIER_BALANCED,
                'label_fa' => 'Claude Sonnet 4',
                'note_fa' => 'متعادل برای Vision و OCR',
                'recommended_for' => ['vision', 'result'],
            ],
            'claude-sonnet-4-6' => [
                'tier' => self::TIER_BALANCED,
                'label_fa' => 'Claude Sonnet 4.6',
                'note_fa' => 'نسخه جدیدتر Sonnet — تعادل کیفیت/هزینه',
                'recommended_for' => ['vision', 'result'],
            ],
            'gpt-5.5' => [
                'tier' => self::TIER_PREMIUM,
                'label_fa' => 'GPT-5.5',
                'note_fa' => 'پیشنهاد برای تحلیل نتیجه — خواندن همه رتبه‌ها',
                'recommended_for' => ['result'],
            ],
            'claude-opus-4-7' => [
                'tier' => self::TIER_PREMIUM,
                'label_fa' => 'Claude Opus 4.7',
                'note_fa' => 'بالاترین کیفیت Claude — هزینه بالا',
                'recommended_for' => ['result'],
            ],
            'claude-opus-4-6' => [
                'tier' => self::TIER_PREMIUM,
                'label_fa' => 'Claude Opus 4.6',
                'note_fa' => 'دقت عالی — مناسب اسکرین‌شات پیچیده',
                'recommended_for' => ['result'],
            ],
            'gemini-3.1-pro-preview' => [
                'tier' => self::TIER_PREMIUM,
                'label_fa' => 'Gemini 3.1 Pro',
                'note_fa' => 'مدل پیشرفته Google — Vision قوی',
                'recommended_for' => ['result'],
            ],
        ];
    }

    public static function inferTier(string $model): string
    {
        $known = self::knownModels();
        if (isset($known[$model])) {
            return $known[$model]['tier'];
        }

        $lower = strtolower($model);

        if (str_contains($lower, 'mini') || str_contains($lower, 'flash') || str_contains($lower, 'nano')) {
            return self::TIER_ECONOMY;
        }

        if (str_contains($lower, 'opus') || str_contains($lower, 'gpt-5') || str_contains($lower, 'pro-preview')) {
            return self::TIER_PREMIUM;
        }

        if (str_contains($lower, 'sonnet') || str_contains($lower, 'gpt-4')) {
            return self::TIER_BALANCED;
        }

        return self::TIER_OTHER;
    }

    /** @return array{tier: string, label_fa: string, note_fa: string, recommended_for: list<string>} */
    public static function metaFor(string $model): array
    {
        $known = self::knownModels();
        if (isset($known[$model])) {
            return $known[$model];
        }

        $tier = self::inferTier($model);

        return match ($tier) {
            self::TIER_ECONOMY => [
                'tier' => $tier,
                'label_fa' => $model,
                'note_fa' => 'مدل سبک (تخمین: ارزان)',
                'recommended_for' => ['vision'],
            ],
            self::TIER_PREMIUM => [
                'tier' => $tier,
                'label_fa' => $model,
                'note_fa' => 'مدل پیشرفته (تخمین: گران)',
                'recommended_for' => ['result'],
            ],
            self::TIER_BALANCED => [
                'tier' => $tier,
                'label_fa' => $model,
                'note_fa' => 'مدل متعادل (تخمین)',
                'recommended_for' => ['vision', 'result'],
            ],
            default => [
                'tier' => self::TIER_OTHER,
                'label_fa' => $model,
                'note_fa' => 'مدل ناشناخته — قبل از استفاده تست کنید',
                'recommended_for' => [],
            ],
        };
    }

    /**
     * @param  list<string>  $models
     * @return list<array{
     *   id: string,
     *   label: string,
     *   description: string,
     *   models: list<array{id: string, label_fa: string, note_fa: string, recommended_for: list<string>}>
     * }>
     */
    public static function categorize(array $models): array
    {
        $tiers = self::tierDefinitions();
        $grouped = [
            self::TIER_ECONOMY => [],
            self::TIER_BALANCED => [],
            self::TIER_PREMIUM => [],
            self::TIER_OTHER => [],
        ];

        $seen = [];
        foreach ($models as $model) {
            $model = trim($model);
            if ($model === '' || isset($seen[$model])) {
                continue;
            }
            $seen[$model] = true;
            $meta = self::metaFor($model);
            $grouped[$meta['tier']][] = [
                'id' => $model,
                'label_fa' => $meta['label_fa'],
                'note_fa' => $meta['note_fa'],
                'recommended_for' => $meta['recommended_for'],
            ];
        }

        $result = [];
        foreach ($tiers as $tierId => $tier) {
            if ($grouped[$tierId] === []) {
                continue;
            }
            $result[] = [
                'id' => $tierId,
                'label' => $tier['label'],
                'description' => $tier['description'],
                'models' => $grouped[$tierId],
            ];
        }

        usort($result, fn (array $a, array $b) => ($tiers[$a['id']]['sort'] ?? 99) <=> ($tiers[$b['id']]['sort'] ?? 99));

        return $result;
    }

    /** @return list<string> */
    public static function premiumModelIds(): array
    {
        return array_keys(array_filter(
            self::knownModels(),
            fn (array $meta) => $meta['tier'] === self::TIER_PREMIUM,
        ));
    }
}
