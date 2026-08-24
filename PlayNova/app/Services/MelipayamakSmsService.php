<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MelipayamakSmsService
{
    private const SEND_OTP_URL = 'https://rest.payamak-panel.com/api/SendSMS/SendOtp';

    private const SEND_PATTERN_URL = 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber';

    public function sendOtp(string $mobile, int $code, string $purpose = 'register'): array
    {
        $username = Setting::getSmsUsername();
        $password = Setting::getSmsApiKey();
        $from = Setting::getSmsSender();
        $to = $this->normalizeMobile($mobile);

        if (! $username || ! $password || ! $to) {
            return ['ok' => false, 'message' => 'تنظیمات ملی‌پیامک ناقص است (نام کاربری، توکن، شماره گیرنده).'];
        }

        $patternId = Setting::getSmsPatternBodyId($purpose);

        if ($patternId) {
            return $this->sendByPattern($username, $password, $to, $purpose, ['code' => (string) $code], (int) $patternId);
        }

        if (! $from) {
            return ['ok' => false, 'message' => 'خط فرستنده (from) در تنظیمات SMS وارد نشده است.'];
        }

        if ($this->isServiceLine($from)) {
            return [
                'ok' => false,
                'message' => 'خط فرستنده شما خط خدماتی است. کد قالب (bodyId) را در تنظیمات SMS وارد کنید.',
            ];
        }

        return $this->sendViaOtp($username, $password, $from, $to, $code);
    }

    private function sendViaOtp(string $username, string $password, string $from, string $to, int $code): array
    {
        try {
            $response = Http::asForm()
                ->timeout(20)
                ->post(self::SEND_OTP_URL, [
                    'username' => $username,
                    'password' => $password,
                    'from' => $from,
                    'to' => $to,
                    'code' => $code,
                ]);
        } catch (\Throwable $e) {
            Log::error('Melipayamak SendOtp request failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'خطا در ارتباط با سرور پیامک.'];
        }

        return $this->parseApiResponse($response, 'SendOtp');
    }

    private function sendByPattern(string $username, string $password, string $to, string $purpose, array $context, int $bodyId): array
    {
        $args = $this->buildPatternArgs($purpose, $context);

        try {
            $response = Http::asForm()
                ->timeout(20)
                ->post(self::SEND_PATTERN_URL, [
                    'username' => $username,
                    'password' => $password,
                    'to' => $to,
                    'bodyId' => $bodyId,
                    'text' => implode(';', $args),
                ]);
        } catch (\Throwable $e) {
            Log::error('Melipayamak BaseServiceNumber request failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'خطا در ارتباط با سرور پیامک.'];
        }

        return $this->parseApiResponse($response, 'BaseServiceNumber');
    }

    /** @param array<string, string> $context */
    private function buildPatternArgs(string $purpose, array $context): array
    {
        $args = [];
        foreach (Setting::getSmsPatternVariables($purpose) as $variable) {
            $args[] = (string) ($context[$variable] ?? '');
        }

        return $args !== [] ? $args : [(string) ($context['code'] ?? '')];
    }

    private function parseApiResponse($response, string $method): array
    {
        if (! $response->successful()) {
            Log::warning("Melipayamak {$method} HTTP error", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['ok' => false, 'message' => 'پاسخ نامعتبر از سرور پیامک.'];
        }

        $parsed = $this->interpretReturnValue($response->body());
        if ($parsed['ok']) {
            return ['ok' => true, 'recId' => $parsed['recId']];
        }

        Log::warning("Melipayamak {$method} rejected", ['response' => $response->body(), 'code' => $parsed['code']]);

        return [
            'ok' => false,
            'message' => $this->translateError($parsed['code']),
            'code' => $parsed['code'],
        ];
    }

    private function interpretReturnValue(string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            return ['ok' => false, 'code' => null];
        }

        $value = null;
        if (is_numeric($body)) {
            $value = (int) $body;
        } else {
            $json = json_decode($body, true);
            if (is_array($json) && isset($json['Value']) && is_numeric($json['Value'])) {
                $value = (int) $json['Value'];
            }
        }

        if ($value === null) {
            return ['ok' => false, 'code' => null];
        }

        if ($this->isSuccessRecId($value)) {
            return ['ok' => true, 'recId' => $value];
        }

        return ['ok' => false, 'code' => $value];
    }

    private function isSuccessRecId(int $value): bool
    {
        if ($value <= 0) {
            return false;
        }

        return $value > 1000;
    }

    private function isServiceLine(string $from): bool
    {
        $digits = preg_replace('/\D+/', '', $from);

        return str_starts_with($digits, '5000') && strlen($digits) >= 10;
    }

    private function normalizeMobile(string $mobile): ?string
    {
        $digits = preg_replace('/\D+/', '', $mobile);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 2);
        }
        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            $digits = '0'.$digits;
        }
        if (! preg_match('/^09\d{9}$/', $digits)) {
            return null;
        }

        return $digits;
    }

    private function translateError(?int $code): string
    {
        $messages = [
            -110 => 'استفاده از ApiKey به‌جای رمز عبور الزامی است.',
            -109 => 'IP سرور در پنل ملی‌پیامک مجاز نیست.',
            -108 => 'IP سرور به‌دلیل تلاش ناموفق مسدود شده است.',
            -10 => 'متن حاوی لینک است.',
            -7 => 'خطای شماره فرستنده — با پشتیبانی ملی‌پیامک تماس بگیرید.',
            -6 => 'خطای داخلی ملی‌پیامک — با پشتیبانی تماس بگیرید.',
            -5 => 'متغیرهای قالب با پترن تأیید‌شده همخوانی ندارد.',
            -4 => 'کد قالب (bodyId) نامعتبر یا تأیید نشده است.',
            -3 => 'خط ارسالی در سیستم تعریف نشده است.',
            -2 => 'هر بار فقط یک شماره گیرنده مجاز است.',
            -1 => 'دسترسی API غیرفعال است — با پشتیبانی تماس بگیرید.',
            0 => 'نام کاربری یا توکن/API نامعتبر است.',
            1 => 'نام کاربری یا توکن/API نامعتبر است.',
            2 => 'اعتبار کافی نیست.',
            5 => 'شماره فرستنده معتبر نیست.',
            9 => 'ارسال از خط عمومی از طریق API مجاز نیست.',
            11 => 'پیامک ارسال نشد — خط فرستنده یا نوع ارسال مناسب OTP نیست.',
            16 => 'شماره گیرنده نامعتبر است.',
            18 => 'شماره گیرنده غیرفعال است.',
            35 => 'شماره در لیست سیاه مخابرات است.',
        ];

        if ($code !== null && isset($messages[$code])) {
            return $messages[$code];
        }

        return 'ارسال پیامک OTP ناموفق بود (کد: '.($code ?? 'نامشخص').').';
    }
}
