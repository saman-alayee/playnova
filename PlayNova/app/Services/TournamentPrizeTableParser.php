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
     * Parse prize table from text; if values look like percentages of prize pool, convert to Toman.
     *
     * @return array<int, float> rank => amount in Toman
     */
    public function parseWithPool(?string $text, float $prizePool): array
    {
        $table = $this->parse($text);

        if ($table === [] || $prizePool <= 0) {
            return $table;
        }

        return $this->convertPercentagesIfNeeded($table, $prizePool);
    }

    /**
     * @param  array<int, float>  $table
     * @return array<int, float>
     */
    public function convertPercentagesIfNeeded(array $table, float $prizePool): array
    {
        if ($table === [] || $prizePool <= 0) {
            return $table;
        }

        $sum = array_sum($table);
        $allWholeNumbers = collect($table)->every(fn ($value) => abs($value - round($value)) < 0.001);

        if ($allWholeNumbers && $sum >= 95 && $sum <= 105 && max($table) <= 100) {
            $converted = [];
            foreach ($table as $rank => $percent) {
                $converted[$rank] = round($prizePool * (float) $percent / 100, 0);
            }

            return $converted;
        }

        return $table;
    }

    /**
     * @return array<int, float> rank => amount in Toman (or percent if marked with %)
     */
    public function parse(?string $text): array
    {
        if ($text === null || trim(strip_tags((string) $text)) === '') {
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
    public function formatForPrompt(array $table, int $seatMode = 1): string
    {
        if ($table === []) {
            return 'جدول جایزه در توضیحات مسابقه یافت نشد. فقط رتبه‌بندی را استخراج کنید.';
        }

        $lines = [];
        $teamSize = max(1, $seatMode);

        if ($teamSize > 1) {
            $lines[] = sprintf(
                'Mode: %d players per team. Each amount below is the TEAM total for that placement (split equally between teammates).',
                $teamSize,
            );
            $lines[] = 'Example: Place 1 = 70,000 Toman team total → each duo teammate gets 35,000 Toman.';
        } else {
            $lines[] = 'Mode: solo. Each amount is paid to one player at that placement.';
        }

        $lines[] = sprintf('Configured prize ranks: %d', count($table));

        foreach ($table as $rank => $amount) {
            if ($teamSize > 1) {
                $share = $this->splitAmongPlayers((float) $amount, $teamSize)[0];
                $lines[] = sprintf(
                    'Place %d: %s Toman for the whole team (%s Toman per player)',
                    $rank,
                    number_format((float) $amount),
                    number_format($share),
                );
            } else {
                $lines[] = sprintf('رتبه %d: %s تومان', $rank, number_format((float) $amount));
            }
        }

        $lastRank = max(array_keys($table));
        $lines[] = sprintf(
            'REQUIRED: Extract teams for ALL prize ranks 1 through %d (%d ranks). Do not stop at rank 3.',
            $lastRank,
            count($table),
        );

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
     * Split a rank/team total among N players so the shares sum back to the total.
     *
     * @return list<float>
     */
    public function splitAmongPlayers(float $total, int $playerCount): array
    {
        $playerCount = max(1, $playerCount);
        $total = (int) max(0, round($total, 0));
        $base = intdiv($total, $playerCount);
        $remainder = $total % $playerCount;
        $shares = array_fill(0, $playerCount, (float) $base);
        $shares[$playerCount - 1] += $remainder;

        return $shares;
    }

    /**
     * @return array{0:int,1:float}|null
     */
    protected function parseLine(string $line): ?array
    {
        $line = $this->persianToAsciiDigits($line);
        $line = preg_replace('/\s+/u', ' ', $line) ?? $line;

        foreach ($this->ordinalsLongestFirst() as $word => $rank) {
            $pattern = '/(?:تیم|نفر|رتبه|مقام)?\s*' . preg_quote($word, '/')
                . '\s*(?:در\s*)?(?:مجموع(?:اً)?|جایزه|مبلغ)?'
                . '\s*[:：\-–\.]?\s*([\d][\d,\.]*)\s*(هزار|میلیون|درصد|٪|%)?/u';
            if (preg_match($pattern, $line, $matches)) {
                $amount = $this->normalizeAmount($matches[1], $matches[2] ?? '');
                if ($amount > 0) {
                    return [$rank, $amount];
                }
            }
        }

        if (preg_match('/(?:تیم|نفر|رتبه|مقام|place|team|rank)\s*[#№]?\s*(\d{1,2})\s*[:：\-–\.]\s*([\d][\d,\.]*)\s*(هزار|میلیون)?/iu', $line, $matches)) {
            $amount = $this->normalizeAmount($matches[2], $matches[3] ?? '');
            if ($amount > 0) {
                return [(int) $matches[1], $amount];
            }
        }

        if (preg_match('/^(\d{1,2})\s*[:：\-–\.]\s*([\d][\d,\.]*)\s*(هزار|میلیون)?/u', $line, $matches)) {
            $amount = $this->normalizeAmount($matches[2], $matches[3] ?? '');
            if ($amount > 0) {
                return [(int) $matches[1], $amount];
            }
        }

        if (preg_match('/(?:تیم|نفر|رتبه|مقام)\s*(\d{1,2})\s+(?:در\s*)?(?:مجموع(?:اً)?|جایزه|مبلغ)?\s*([\d][\d,\.]*)\s*(هزار|میلیون)?/u', $line, $matches)) {
            $amount = $this->normalizeAmount($matches[2], $matches[3] ?? '');
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

        foreach ($this->ordinalsLongestFirst() as $word => $rank) {
            $pattern = '/(?:تیم|نفر|رتبه|مقام)?\s*' . preg_quote($word, '/')
                . '\s*(?:در\s*)?(?:مجموع(?:اً)?|جایزه|مبلغ)?'
                . '\s*[:：\-–\.]?\s*([\d][\d,\.]*)\s*(هزار|میلیون|درصد|٪|%)?/u';
            if (preg_match($pattern, $text, $matches)) {
                $amount = $this->normalizeAmount($matches[1], $matches[2] ?? '');
                if ($amount > 0) {
                    $table[$rank] = $amount;
                }
            }
        }

        if (preg_match_all('/(?:تیم|نفر|رتبه|مقام)\s*(\d{1,2})\s*[:：\-–\.]?\s*([\d][\d,\.]*)\s*(هزار|میلیون)?/u', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $amount = $this->normalizeAmount($match[2], $match[3] ?? '');
                if ($amount > 0) {
                    $table[(int) $match[1]] = $amount;
                }
            }
        }

        ksort($table);

        return $table;
    }

    /**
     * @return array<string, int>
     */
    protected function ordinalsLongestFirst(): array
    {
        $words = self::ORDINAL_WORDS;
        uksort($words, fn (string $left, string $right) => mb_strlen($right) <=> mb_strlen($left));

        return $words;
    }

    protected function normalizeAmount(string $raw, string $multiplier = ''): float
    {
        $value = $this->persianToAsciiDigits($raw);
        $value = preg_replace('/[^\d.]/', '', $value) ?? '';

        if ($value === '' || ! is_numeric($value)) {
            return 0.0;
        }

        $amount = (float) $value;

        return match (trim($multiplier)) {
            'هزار' => $amount * 1000,
            'میلیون' => $amount * 1_000_000,
            'درصد', '٪', '%' => $amount,
            default => $amount,
        };
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
