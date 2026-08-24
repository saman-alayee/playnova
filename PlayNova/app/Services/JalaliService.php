<?php

namespace App\Services;

use Carbon\CarbonInterface;

class JalaliService
{
    public static function toPersianDigits(string $value): string
    {
        return str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
            $value
        );
    }

    public static function gregorianToJalali(int $gy, int $gm, int $gd): array
    {
        $gDaysInMonth = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $gDaysInMonth[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];
    }

    public static function formatDate(CarbonInterface $date): string
    {
        [$jy, $jm, $jd] = self::gregorianToJalali(
            (int) $date->format('Y'),
            (int) $date->format('n'),
            (int) $date->format('j')
        );

        $formatted = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);

        return self::toPersianDigits($formatted);
    }

    public static function formatTime(CarbonInterface $date): string
    {
        return self::toPersianDigits($date->format('H:i'));
    }

    public static function formatDateTime(CarbonInterface $date): array
    {
        return [
            'date' => self::formatDate($date),
            'time' => self::formatTime($date),
        ];
    }
}
