<?php

namespace App\Services;

class CaptchaService
{
    public static function refresh(): array
    {
        $a = random_int(2, 12);
        $b = random_int(2, 12);
        session(['captcha_answer' => $a + $b]);

        return [
            'question' => "{$a} + {$b} = ?",
        ];
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

    public static function rules(): array
    {
        return ['captcha' => 'required|numeric'];
    }

    public static function messages(): array
    {
        return [
            'captcha.required' => 'لطفاً پاسخ کد امنیتی را وارد کنید.',
            'captcha.numeric' => 'پاسخ کد امنیتی باید عدد باشد.',
        ];
    }

    public static function failResponse()
    {
        return back()->withErrors(['captcha' => 'کد امنیتی صحیح نیست.'])->withInput();
    }
}
