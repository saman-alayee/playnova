<?php

namespace App\Services;

class TournamentPrizeTableParser
{
    /** @var array<string, int> */
    protected const ORDINAL_WORDS = [
        'اول' => 1, 'یکم' => 1, 'نفر اول' => 1, 'تیم اول' => 1,
        'دوم' => 2, 'دویوم' => 2, 'دومین' => 2, 'نفر دوم' => 2, 'تیم دوم' => 2,
        'سوم' => 3, 'سومین' => 3, 'نفر سوم' => 3, 'تیم سوم' => 3,
        'چهارم' => 4, 'چهارمی' => 4, 'نفر چهارم' => 4, 'تیم چهارم' => 4,
        'پنجم' => 5, 'پنجمی' => 5, 'نفر پنجم' => 5, 'تیم پنجم' => 5,
        'ششم' => 6, 'نفر ششم' => 6, 'تیم ششم' => 6,
        'هفتم' => 7, 'نفر هفتم' => 7, 'تیم هفتم' => 7,
        'هشتم' => 8, 'نفر هشتم' => 8, 'تیم هشتم' => 8,
        'نهم' => 9, 'نفر نهم' => 9, 'تیم نهم' => 9,
        'دهم' => 10, 'نفر دهم' => 10, 'تیم دهم' => 10,
        'یازدهم' => 11, 'نفر یازدهم' => 11, 'تیم یازدهم' => 11,
        'دوازدهم' => 12, 'نفر دوازدهم' => 12, 'تیم دوازدهم' => 12,
        'سیزدهم' => 13, 'چهاردهم' => 14, 'پانزدهم' => 15,
        'شانزدهم' => 16, 'هفدهم' => 17, 'هجدهم' => 18, 'نوزدهم' => 19,
        'بیستم' => 20, 'بیست و یکم' => 21, 'بیست‌و‌یکم' => 21, 'بیست و یکمین' => 21,
    ];

    /**
     * @return array<int, float> rank => amount in Toman
     */
    public function parse(?string $text): array
    {
        if (! filled($text)) {
            return [];
        }

        $plain = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = str_replace(["\r\n", "\r"], "\n", $plain);
        $table = [];

        foreach (preg_split('/\n+/u', $plain) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parsed = $this->parseLine($line);
            if ($parsed !== null) {
                [$rank, $amount] = $parsed;
                if ($rank > 0 && $amount > 0) {
                    $table[$rank] = $amount;
                }
            }
        }

        if ($table === []) {
            $table = $this->parseInline($plain);
        }

        ksort($table);

        return $table;
    }

    /**
     * @param  array<int, float>  $table
     */
    public function formatForPrompt(array $table): string
    {
        if ($table === []) {
            return 'جدول جایزه در توضیحات مسابقه یافت نشد. فقط رتبه‌بندی را استخراج کنید.';
        }

        $lines = [];
        foreach ($table as $rank => $amount) {
            $lines[] = sprintf('رتبه %d: %s تومان', $rank, number_format((float) $amount));
        }

        return implode("\n", $lines);
    }

    public function amountForRank(array $table, int $rank, float $fallbackFirstPrize = 0.0): float
    {
        if (isset($table[$rank])) {
            return (float) $table[$rank];
        }

        if ($rank === 1 && $fallbackFirstPrize > 0) {
            return $fallbackFirstPrize;
        }

        return 0.0;
    }

    /**
     * @return array{0:int,1:float}|null
     */
    protected function parseLine(string $line): ?array
    {
        $line = $this->persianToAsciiDigits($line);
        $line = preg_replace('/\s+/u', ' ', $line) ?? $line;

        foreach (self::ORDINAL_WORDS as $word => $rank) {
            $pattern = '/(?:تیم|نفر|رتبه|مقام)?\s*' . preg_quote($word, '/') . '\s*[:：\-–\.]?\s*([\d][\d,\.]*)/u';
            if (preg_match($pattern, $line, $matches)) {
                $amount = $this->normalizeAmount($matches[1]);
                if ($amount > 0) {
                    return [$rank, $amount];
                }
            }
        }

        if (preg_match('/(?:تیم|نفر|رتبه|مقام|place|team|rank)\s*[#№]?\s*(\d{1,2})\s*[:：\-–\.]\s*([\d][\d,\.]*)/iu', $line, $matches)) {
            $amount = $this->normalizeAmount($matches[2]);
            if ($amount > 0) {
                return [(int) $matches[1], $amount];
            }
        }

        if (preg_match('/^(\d{1,2})\s*[:：\-–\.]\s*([\d][\d,\.]*)/u', $line, $matches)) {
            $amount = $this->normalizeAmount($matches[2]);
            if ($amount > 0) {
                return [(int) $matches[1], $amount];
            }
        }

        if (preg_match('/(?:تیم|نفر|رتبه|مقام)\s*(\d{1,2})\s+([\d][\d,\.]*)/u', $line, $matches)) {
            $amount = $this->normalizeAmount($matches[2]);
            if ($amount > 0) {
                return [(int) $matches[1], $amount];
            }
        }

        return null;
    }

    /**
     * @return array<int, float>
     */
    protected function parseInline(string $text): array
    {
        $table = [];
        $text = $this->persianToAsciiDigits($text);

        foreach (self::ORDINAL_WORDS as $word => $rank) {
            $pattern = '/(?:تیم|نفر|رتبه|مقام)?\s*' . preg_quote($word, '/') . '\s*[:：\-–\.]?\s*([\d][\d,\.]*)/u';
            if (preg_match($pattern, $text, $matches)) {
                $amount = $this->normalizeAmount($matches[1]);
                if ($amount > 0) {
                    $table[$rank] = $amount;
                }
            }
        }

        if (preg_match_all('/(?:تیم|نفر|رتبه|مقام)\s*(\d{1,2})\s*[:：\-–\.]?\s*([\d][\d,\.]*)/u', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $amount = $this->normalizeAmount($match[2]);
                if ($amount > 0) {
                    $table[(int) $match[1]] = $amount;
                }
            }
        }

        ksort($table);

        return $table;
    }

    protected function normalizeAmount(string $raw): float
    {
        $value = $this->persianToAsciiDigits($raw);
        $value = preg_replace('/[^\d.]/', '', $value) ?? '';

        if ($value === '' || ! is_numeric($value)) {
            return 0.0;
        }

        return (float) $value;
    }

    protected function persianToAsciiDigits(string $value): string
    {
        static $map = [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ];

        return strtr($value, $map);
    }
}
