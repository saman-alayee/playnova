<?php

namespace App\Services;

use Illuminate\Support\Str;

class CaptchaService
{
    private const CACHE_PREFIX = 'captcha:';

    private const TTL_MINUTES = 10;

    public static function issue(): array
    {
        $a = random_int(2, 12);
        $b = random_int(2, 12);
        $key = (string) Str::uuid();

        cache()->put(self::cacheKey($key), $a + $b, now()->addMinutes(self::TTL_MINUTES));

        return [
            'key' => $key,
            'question' => "{$a} + {$b} = ?",
        ];
    }

    public static function refresh(): array
    {
        $issued = self::issue();
        session(['captcha_answer' => self::peekAnswer($issued['key'])]);

        return [
            'question' => $issued['question'],
            'key' => $issued['key'],
        ];
    }

    public static function validateWithKey(?string $key, ?string $answer): bool
    {
        if ($key === null || $key === '' || $answer === null || $answer === '') {
            return false;
        }

        $expected = cache()->pull(self::cacheKey($key));

        if ($expected === null) {
            return false;
        }

        return (int) $answer === (int) $expected;
    }

    public static function validate(?string $answer): bool
    {
        $expected = session('captcha_answer');

        if ($expected === null || $answer === null || $answer === '') {
            return false;
        }

        session()->forget('captcha_answer');

        return (int) $answer === (int) $expected;
    }

    public static function apiRules(): array
    {
        return [
            'captcha_key' => 'required|string|uuid',
            'captcha' => 'required|numeric',
        ];
    }

    public static function rules(): array
    {
        return ['captcha' => 'required|numeric'];
    }

    public static function messages(): array
    {
        return [
            'captcha_key.required' => 'کد امنیتی منقضی شده. صفحه را رفرش کنید.',
            'captcha_key.uuid' => 'کد امنیتی نامعتبر است.',
            'captcha.required' => 'لطفاً پاسخ کد امنیتی را وارد کنید.',
            'captcha.numeric' => 'پاسخ کد امنیتی باید عدد باشد.',
        ];
    }

    public static function failResponse()
    {
        return back()->withErrors(['captcha' => 'کد امنیتی صحیح نیست.'])->withInput();
    }

    private static function cacheKey(string $key): string
    {
        return self::CACHE_PREFIX . $key;
    }

    private static function peekAnswer(string $key): ?int
    {
        $value = cache()->get(self::cacheKey($key));

        return $value === null ? null : (int) $value;
    }
}
