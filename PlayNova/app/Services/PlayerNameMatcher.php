<?php

namespace App\Services;

class PlayerNameMatcher
{
    /** @var array<string, string> */
    protected const HOMOGLYPHS = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
        'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        'Α' => 'a', 'Β' => 'b', 'Ε' => 'e', 'Ζ' => 'z', 'Η' => 'h', 'Ι' => 'i', 'Κ' => 'k',
        'Μ' => 'm', 'Ν' => 'n', 'Ο' => 'o', 'Ρ' => 'p', 'Τ' => 't', 'Υ' => 'y', 'Χ' => 'x',
        'α' => 'a', 'β' => 'b', 'ε' => 'e', 'ζ' => 'z', 'η' => 'h', 'ι' => 'i', 'κ' => 'k',
        'μ' => 'm', 'ν' => 'n', 'ο' => 'o', 'ρ' => 'p', 'τ' => 't', 'υ' => 'y', 'χ' => 'x',
        'ı' => 'i', 'İ' => 'i', 'ş' => 's', 'Ş' => 's', 'ğ' => 'g', 'Ğ' => 'g', 'ç' => 'c', 'Ç' => 'c',
        'ö' => 'o', 'Ö' => 'o', 'ü' => 'u', 'Ü' => 'u',
        'ك' => 'k', 'ک' => 'k', 'ي' => 'y', 'ی' => 'y', 'ة' => 'h', 'ۀ' => 'h',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4', '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
        'Ａ' => 'a', 'Ｂ' => 'b', 'Ｃ' => 'c', 'Ｄ' => 'd', 'Ｅ' => 'e', 'Ｆ' => 'f', 'Ｇ' => 'g', 'Ｈ' => 'h',
        'Ｉ' => 'i', 'Ｊ' => 'j', 'Ｋ' => 'k', 'Ｌ' => 'l', 'Ｍ' => 'm', 'Ｎ' => 'n', 'Ｏ' => 'o', 'Ｐ' => 'p',
        'Ｑ' => 'q', 'Ｒ' => 'r', 'Ｓ' => 's', 'Ｔ' => 't', 'Ｕ' => 'u', 'Ｖ' => 'v', 'Ｗ' => 'w', 'Ｘ' => 'x',
        'Ｙ' => 'y', 'Ｚ' => 'z',
        'ℓ' => 'l', '№' => 'n', '×' => 'x', '÷' => '/', '∅' => 'o',
    ];

    public static function normalizeUid(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits !== '' ? $digits : null;
    }

    public static function normalizeName(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $name = self::stripInvisible(trim($value));
        if ($name === '') {
            return null;
        }

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($name, \Normalizer::FORM_KD);
            if (is_string($normalized)) {
                $name = preg_replace('/\p{Mn}+/u', '', $normalized) ?? $normalized;
            }
        }

        $name = mb_strtolower($name, 'UTF-8');
        $name = self::replaceHomoglyphs($name);
        $name = self::stripEmoji($name);
        $name = preg_replace('/[\s\-_.|•·]+/u', '', $name) ?? $name;
        $name = preg_replace('/[^\p{L}\p{N}]+/u', '', $name) ?? $name;

        return $name !== '' ? $name : null;
    }

    public static function skeleton(mixed $value): ?string
    {
        $normalized = self::normalizeName($value);
        if ($normalized === null) {
            return null;
        }

        $skeleton = preg_replace('/[^a-z0-9]+/', '', self::transliterateLatin($normalized));

        return $skeleton !== '' ? $skeleton : null;
    }

    /**
     * @param  list<array{user_id:int,username:string,cod_id:?string,seat_number:?int}>  $participants
     * @param  array<int, true>  $usedUserIds
     * @return array{user_id:int,username:string,cod_id:?string,seat_number:?int,match_method:string,match_score:float}|null
     */
    public static function findBestMatch(
        ?string $detectedName,
        ?string $detectedUid,
        array $participants,
        array &$usedUserIds,
        float $minScore = 0.72,
    ): ?array {
        $detectedUid = self::normalizeUid($detectedUid);

        if ($detectedUid) {
            foreach ($participants as $participant) {
                $userId = (int) $participant['user_id'];
                if (isset($usedUserIds[$userId])) {
                    continue;
                }

                if (self::normalizeUid($participant['cod_id']) === $detectedUid) {
                    $usedUserIds[$userId] = true;

                    return array_merge($participant, [
                        'match_method' => 'uid',
                        'match_score' => 1.0,
                    ]);
                }
            }

            if (strlen($detectedUid) >= 8) {
                foreach ($participants as $participant) {
                    $userId = (int) $participant['user_id'];
                    if (isset($usedUserIds[$userId])) {
                        continue;
                    }

                    $storedUid = self::normalizeUid($participant['cod_id']);
                    if ($storedUid && str_ends_with($storedUid, substr($detectedUid, -8))) {
                        $usedUserIds[$userId] = true;

                        return array_merge($participant, [
                            'match_method' => 'uid_suffix',
                            'match_score' => 0.98,
                        ]);
                    }
                }
            }
        }

        $detectedNormalized = self::normalizeName($detectedName);
        $detectedSkeleton = self::skeleton($detectedName);

        if ($detectedNormalized === null && $detectedSkeleton === null) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;
        $bestMethod = 'name_fuzzy';

        foreach ($participants as $participant) {
            $userId = (int) $participant['user_id'];
            if (isset($usedUserIds[$userId])) {
                continue;
            }

            $username = (string) $participant['username'];
            $storedNormalized = self::normalizeName($username);
            $storedSkeleton = self::skeleton($username);

            if ($detectedNormalized && $storedNormalized && $detectedNormalized === $storedNormalized) {
                $usedUserIds[$userId] = true;

                return array_merge($participant, [
                    'match_method' => 'username',
                    'match_score' => 1.0,
                ]);
            }

            if ($detectedSkeleton && $storedSkeleton && $detectedSkeleton === $storedSkeleton) {
                $usedUserIds[$userId] = true;

                return array_merge($participant, [
                    'match_method' => 'username_skeleton',
                    'match_score' => 0.99,
                ]);
            }

            $score = max(
                self::similarity($detectedNormalized, $storedNormalized),
                self::similarity($detectedSkeleton, $storedSkeleton),
            );

            if ($score >= $minScore && $score > $bestScore) {
                $best = $participant;
                $bestScore = $score;
                $bestMethod = $score >= 0.9 ? 'name_fuzzy_high' : 'name_fuzzy';
            }
        }

        if ($best !== null && $bestScore >= $minScore) {
            $userId = (int) $best['user_id'];
            $usedUserIds[$userId] = true;

            return array_merge($best, [
                'match_method' => $bestMethod,
                'match_score' => round($bestScore, 3),
            ]);
        }

        if ($minScore <= 0.72 && $detectedSkeleton && strlen($detectedSkeleton) >= 3) {
            foreach ($participants as $participant) {
                $userId = (int) $participant['user_id'];
                if (isset($usedUserIds[$userId])) {
                    continue;
                }

                $storedSkeleton = self::skeleton($participant['username']);
                if (! $storedSkeleton) {
                    continue;
                }

                if (str_contains($storedSkeleton, $detectedSkeleton) || str_contains($detectedSkeleton, $storedSkeleton)) {
                    $usedUserIds[$userId] = true;

                    return array_merge($participant, [
                        'match_method' => 'username_partial',
                        'match_score' => 0.7,
                    ]);
                }
            }
        }

        return null;
    }

    public static function similarity(?string $a, ?string $b): float
    {
        if ($a === null || $b === null || $a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 1.0;
        }

        similar_text($a, $b, $percent);
        $textScore = $percent / 100;

        if (strlen($a) > 255 || strlen($b) > 255) {
            return $textScore;
        }

        $maxLen = max(strlen($a), strlen($b));
        if ($maxLen === 0) {
            return 0.0;
        }

        $lev = levenshtein($a, $b);
        $levScore = 1 - ($lev / $maxLen);

        return max($levScore, $textScore);
    }

    protected static function stripInvisible(string $value): string
    {
        return preg_replace('/[\x{00AD}\x{034F}\x{061C}\x{115F}\x{1160}\x{17B4}\x{17B5}\x{180E}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}\x{3164}\x{FEFF}\x{FFA0}\x{FFF9}-\x{FFFB}]/u', '', $value) ?? $value;
    }

    protected static function stripEmoji(string $value): string
    {
        return preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', $value) ?? $value;
    }

    protected static function replaceHomoglyphs(string $value): string
    {
        return strtr($value, self::HOMOGLYPHS);
    }

    protected static function transliterateLatin(string $value): string
    {
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($converted) && $converted !== '') {
                return strtolower($converted);
            }
        }

        return $value;
    }
}
