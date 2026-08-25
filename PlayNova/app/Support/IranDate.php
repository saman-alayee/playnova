<?php

namespace App\Support;

use App\Services\JalaliService;
use Carbon\CarbonInterface;

class IranDate
{
    public static function format(?CarbonInterface $date): ?array
    {
        if (! $date) {
            return null;
        }

        return JalaliService::formatDateTime($date->copy()->timezone('Asia/Tehran'));
    }

    public static function formatString(?CarbonInterface $date): ?string
    {
        $parts = self::format($date);

        return $parts ? "{$parts['date']} {$parts['time']}" : null;
    }
}
