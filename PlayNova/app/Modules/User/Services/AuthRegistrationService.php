<?php

namespace App\Modules\User\Services;

use App\Models\Setting;
use App\Models\User;
use App\Jobs\SendOtpSmsJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthRegistrationService
{
    public function normalizeRegistrationInput(Request $request): ?array
    {
        $username = trim((string) $request->input('username', ''));
        $request->merge(['username' => $username]);

        $normalizedMobile = $this->normalizeMobileForLookup((string) $request->input('mobile', ''));
        if (! $normalizedMobile) {
            return ['mobile' => 'شماره موبایل معتبر نیست.'];
        }
        $request->merge(['mobile' => $normalizedMobile]);

        $codId = trim((string) $request->input('cod_id', ''));
        $request->merge(['cod_id' => $codId !== '' ? $codId : null]);

        return null;
    }

    public function makeRegistrationValidator(Request $request)
    {
        return Validator::make(
            $request->all(),
            $this->registrationRules(),
            $this->registrationMessages()
        );
    }

    public function registrationRules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'mobile' => ['required', 'string', 'max:20', 'unique:users,mobile'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'cod_id' => ['required', 'string', 'max:100', 'unique:users,cod_id'],
            'referral_code' => ['nullable', 'string', 'exists:users,referral_code'],
            'accept_rules' => ['required', 'accepted'],
        ];
    }

    public function registrationMessages(): array
    {
        return [
            'username.required' => 'نام کاربری الزامی است.',
            'username.unique' => 'این نام کاربری قبلاً ثبت شده است.',
            'mobile.required' => 'شماره موبایل الزامی است.',
            'mobile.unique' => 'این شماره موبایل قبلاً ثبت‌نام شده است.',
            'cod_id.required' => 'آیدی کالاف الزامی است.',
            'cod_id.unique' => 'این آیدی کالاف قبلاً توسط کاربر دیگری ثبت شده است.',
            'accept_rules.required' => 'برای ثبت‌نام باید قوانین و مقررات را بپذیرید.',
            'accept_rules.accepted' => 'برای ثبت‌نام باید قوانین و مقررات را بپذیرید.',
        ];
    }

    public function ensureRegistrationAvailable(string $username, string $mobile, ?string $codId): ?array
    {
        $username = trim($username);
        $mobile = trim($mobile);
        $codId = trim((string) $codId);

        if ($username !== '' && User::where('username', $username)->exists()) {
            return ['username' => 'این نام کاربری قبلاً ثبت شده است.'];
        }

        if ($mobile !== '' && User::where('mobile', $mobile)->exists()) {
            return ['mobile' => 'این شماره موبایل قبلاً ثبت‌نام شده است.'];
        }

        if ($codId !== '' && User::where('cod_id', $codId)->exists()) {
            return ['cod_id' => 'این آیدی کالاف قبلاً توسط کاربر دیگری ثبت شده است.'];
        }

        return null;
    }

    public function createUserFromRequest(Request $request): User
    {
        $referrer = null;
        if ($request->filled('referral_code')) {
            $referrer = User::where('referral_code', $request->referral_code)->first();
        }

        return User::create([
            'name' => $request->username,
            'username' => $request->username,
            'email' => null,
            'mobile' => $request->mobile,
            'password' => Hash::make($request->password),
            'cod_id' => $request->cod_id,
            'referral_code' => User::generateReferralCode(),
            'referred_by' => $referrer?->id,
        ]);
    }

    /** @return array{ok: bool, token?: string, error?: string, field?: string} */
    public function startMobileVerification(Request $request): array
    {
        $referrer = null;
        if ($request->filled('referral_code')) {
            $referrer = User::where('referral_code', $request->referral_code)->first();
        }

        $token = Str::random(40);
        $code = random_int(100000, 999999);

        cache()->put('register_pending_' . $token, [
            'username' => $request->username,
            'mobile' => $request->mobile,
            'password_hash' => Hash::make($request->password),
            'cod_id' => $request->cod_id,
            'referral_code' => $request->referral_code,
            'referred_by' => $referrer?->id,
        ], now()->addMinutes(30));

        cache()->put('register_otp_' . $token, (string) $code, now()->addMinutes(15));

        $testMode = Setting::isSmsTestMode() || Setting::isSmsRegisterTestMode();
        $smsActive = Setting::isSmsActive();

        if ($smsActive && ! $testMode) {
            $provider = strtolower((string) Setting::getSmsProvider());
            if ($provider !== 'melipayamak') {
                return ['ok' => false, 'error' => 'سرویس پیامک پشتیبانی نمی‌شود.', 'field' => 'mobile'];
            }

            $result = SendOtpSmsJob::sendNow($request->mobile, $code, 'register');
            if (! $result['ok']) {
                return ['ok' => false, 'error' => $result['message'] ?? 'ارسال پیامک ناموفق بود.', 'field' => 'mobile'];
            }
        }

        return ['ok' => true, 'token' => $token];
    }

    public function maskMobile(?string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', (string) $mobile);
        if (strlen($digits) < 4) {
            return (string) $mobile;
        }

        return substr($digits, 0, 4) . '***' . substr($digits, -2);
    }

    public function normalizeMobileForLookup(string $mobile): ?string
    {
        $digits = preg_replace('/\D+/', '', $mobile);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        }
        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            $digits = '0' . $digits;
        }
        if (! preg_match('/^09\d{9}$/', $digits)) {
            return null;
        }

        return $digits;
    }
}
