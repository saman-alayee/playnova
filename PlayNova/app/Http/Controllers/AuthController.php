<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Setting;
use App\Jobs\SendOtpSmsJob;
use App\Modules\User\Services\AuthRegistrationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const RESET_OTP_TTL_SECONDS = 120;

    public function __construct(protected AuthRegistrationService $registration) {}

    public function showLogin()
    {
        return view('auth.login');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $inputError = $this->registration->normalizeRegistrationInput($request);
        if ($inputError) {
            return back()->withErrors($inputError)->withInput();
        }

        $validator = $this->registration->makeRegistrationValidator($request);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        if (! \App\Services\CaptchaService::validate($request->input('captcha'))) {
            return \App\Services\CaptchaService::failResponse();
        }

        $needsMobileVerify = Setting::isSmsRegisterVerifyEnabled();

        if ($needsMobileVerify) {
            return $this->startRegisterMobileVerification($request);
        }

        return $this->createUserFromRequest($request);
    }

    protected function startRegisterMobileVerification(Request $request)
    {
        $result = $this->registration->startMobileVerification($request);

        if (! $result['ok']) {
            return back()->withErrors([$result['field'] ?? 'mobile' => $result['error']])->withInput();
        }

        return redirect()->route('register.verify', $result['token']);
    }

    public function showRegisterVerify(string $token)
    {
        if (! cache()->has('register_pending_' . $token)) {
            return redirect()->route('register')->withErrors(['mobile' => 'نشست تأیید منقضی شده. دوباره ثبت‌نام کنید.']);
        }

        $pending = cache()->get('register_pending_' . $token);
        $mobile = $pending['mobile'] ?? '';
        $maskedMobile = $this->registration->maskMobile($mobile);
        $testMode = Setting::isSmsTestMode() || Setting::isSmsRegisterTestMode();
        $testCode = $testMode ? cache()->get('register_otp_' . $token) : null;

        return view('auth.register-verify-mobile', compact('token', 'maskedMobile', 'testMode', 'testCode'));
    }

    public function verifyRegister(Request $request, string $token)
    {
        $request->validate(['code' => 'required|digits:6']);

        $pending = cache()->get('register_pending_' . $token);
        $cachedOtp = cache()->get('register_otp_' . $token);

        if (! $pending || ! $cachedOtp) {
            return redirect()->route('register')->withErrors(['mobile' => 'نشست تأیید منقضی شده. دوباره ثبت‌نام کنید.']);
        }

        if ((string) $cachedOtp !== (string) $request->code) {
            return back()->withErrors(['code' => 'کد وارد شده صحیح نیست.']);
        }

        $duplicateError = $this->registration->ensureRegistrationAvailable(
            $pending['username'] ?? '',
            $pending['mobile'] ?? '',
            $pending['cod_id'] ?? null
        );
        if ($duplicateError) {
            cache()->forget('register_pending_' . $token);
            cache()->forget('register_otp_' . $token);

            return redirect()->route('register')->withErrors($duplicateError);
        }

        cache()->forget('register_pending_' . $token);
        cache()->forget('register_otp_' . $token);

        try {
            $user = $this->registration->createUserFromPending($pending);
        } catch (QueryException $e) {
            $duplicateErrors = $this->registration->mapRegistrationDuplicateErrors($e);
            if ($duplicateErrors) {
                return redirect()->route('register')->withErrors($duplicateErrors);
            }

            throw $e;
        }

        Auth::login($user);

        return redirect()->route('home')->with('success', 'ثبت‌نام با موفقیت انجام شد. خوش آمدید!');
    }

    public function resendRegisterVerify(string $token)
    {
        $pending = cache()->get('register_pending_' . $token);
        if (! $pending || empty($pending['mobile'])) {
            return redirect()->route('register')->withErrors(['mobile' => 'نشست تأیید منقضی شده.']);
        }

        $code = random_int(100000, 999999);
        cache()->put('register_otp_' . $token, (string) $code, now()->addMinutes(15));

        $testMode = Setting::isSmsTestMode() || Setting::isSmsRegisterTestMode();
        if (Setting::isSmsActive() && ! $testMode) {
            $result = SendOtpSmsJob::sendNow($pending['mobile'], $code, 'register');
            if (! $result['ok']) {
                return back()->withErrors(['code' => $result['message'] ?? 'ارسال مجدد ناموفق بود.']);
            }

            return back()->with('success', 'کد جدید ارسال شد.');
        }

        return back()->with('success', 'کد جدید صادر شد (حالت تست).');
    }

    protected function createUserFromRequest(Request $request)
    {
        try {
            $user = $this->registration->createUserFromRequest($request);
        } catch (QueryException $e) {
            $duplicateErrors = $this->registration->mapRegistrationDuplicateErrors($e);
            if ($duplicateErrors) {
                return back()->withErrors($duplicateErrors)->withInput();
            }

            throw $e;
        }

        Auth::login($user);

        return redirect()->route('home')->with('success', 'ثبت‌نام با موفقیت انجام شد. خوش آمدید!');
    }

    protected function normalizeRegistrationInput(Request $request): ?array
    {
        return $this->registration->normalizeRegistrationInput($request);
    }

    protected function makeRegistrationValidator(Request $request)
    {
        return $this->registration->makeRegistrationValidator($request);
    }

    protected function ensureRegistrationAvailable(string $username, string $mobile, ?string $codId): ?array
    {
        return $this->registration->ensureRegistrationAvailable($username, $mobile, $codId);
    }

    protected function maskMobile(?string $mobile): string
    {
        return $this->registration->maskMobile($mobile);
    }

    public function login(Request $request)
    {
        $login = trim((string) ($request->input('login') ?: $request->input('mobile')));
        $request->merge(['login' => $login]);

        $validator = Validator::make(
            $request->all(),
            array_merge([
                'login' => 'required|string',
                'password' => 'required|string',
            ], \App\Services\CaptchaService::rules()),
            \App\Services\CaptchaService::messages()
        );

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        if (! \App\Services\CaptchaService::validate($request->input('captcha'))) {
            return \App\Services\CaptchaService::failResponse();
        }

        $user = User::findByLogin($login);

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return back()->withErrors(['mobile' => 'اطلاعات ورود صحیح نیست.'])->withInput();
        }

        Auth::login($user, $request->boolean('remember'));

        return redirect()->intended(route('home'))->with('success', 'خوش آمدید ' . $user->username);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'با موفقیت خارج شدید.');
    }

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetCode(Request $request)
    {
        $request->validate([
            'mobile' => 'required|string|max:20',
        ]);

        $normalized = $this->registration->normalizeMobileForLookup($request->mobile);
        if (! $normalized) {
            return back()->withErrors(['mobile' => 'شماره موبایل معتبر نیست.'])->withInput();
        }

        $user = User::where('mobile', $normalized)->first();

        if (! $user) {
            return back()->withErrors(['mobile' => 'کاربری با این شماره موبایل یافت نشد.'])->withInput();
        }

        $token = Str::random(40);
        $code = random_int(100000, 999999);

        cache()->put('password_reset_pending_' . $token, [
            'user_id' => $user->id,
            'mobile' => $user->mobile,
        ], now()->addMinutes(30));

        $this->storeResetOtp($token, $code);

        $testMode = Setting::isSmsTestMode();
        $smsActive = Setting::isSmsActive();

        if ($smsActive && ! $testMode) {
            $provider = strtolower((string) Setting::getSmsProvider());
            if ($provider !== 'melipayamak') {
                return back()->withErrors(['mobile' => 'سرویس پیامک پشتیبانی نمی‌شود.'])->withInput();
            }
            $result = SendOtpSmsJob::sendNow($user->mobile, $code, 'reset');
            if (! $result['ok']) {
                return back()->withErrors(['mobile' => $result['message'] ?? 'ارسال پیامک ناموفق بود.'])->withInput();
            }
        }

        return redirect()->route('password.reset.verify', $token);
    }

    public function showResetPasswordVerify(string $token)
    {
        if (! cache()->has('password_reset_pending_' . $token)) {
            return redirect()->route('password.request')->withErrors(['mobile' => 'نشست بازیابی منقضی شده. دوباره تلاش کنید.']);
        }

        $pending = cache()->get('password_reset_pending_' . $token);
        $mobile = $pending['mobile'] ?? '';
        $maskedMobile = $this->registration->maskMobile($mobile);
        $testMode = Setting::isSmsTestMode();
        $otp = $this->getResetOtp($token);
        $testCode = $testMode && $otp ? $otp['code'] : null;
        $expiresAt = $otp['expires_at'] ?? time();
        $secondsLeft = max(0, $expiresAt - time());
        $otpExpired = $secondsLeft <= 0;

        return view('auth.reset-password-verify', compact(
            'token',
            'maskedMobile',
            'testMode',
            'testCode',
            'expiresAt',
            'secondsLeft',
            'otpExpired'
        ));
    }

    public function resetPassword(Request $request, string $token)
    {
        $request->validate([
            'code' => 'required|digits:6',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $pending = cache()->get('password_reset_pending_' . $token);
        $otp = $this->getResetOtp($token);

        if (! $pending || ! $otp) {
            return redirect()->route('password.request')->withErrors(['mobile' => 'کد تأیید منقضی شده است. دوباره درخواست دهید.']);
        }

        if ((string) $otp['code'] !== (string) $request->code) {
            return back()->withErrors(['code' => 'کد وارد شده صحیح نیست.']);
        }

        $user = User::find($pending['user_id']);
        if (! $user) {
            return redirect()->route('password.request')->withErrors(['mobile' => 'کاربر یافت نشد.']);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        cache()->forget('password_reset_pending_' . $token);
        cache()->forget('password_reset_otp_' . $token);

        return redirect()->route('login')->with('success', 'رمز عبور با موفقیت تغییر کرد.');
    }

    public function resendResetCode(string $token)
    {
        $pending = cache()->get('password_reset_pending_' . $token);
        if (! $pending || empty($pending['mobile'])) {
            return redirect()->route('password.request')->withErrors(['mobile' => 'نشست بازیابی منقضی شده.']);
        }

        $existing = cache()->get('password_reset_otp_' . $token);
        if (is_array($existing) && ($existing['expires_at'] ?? 0) > time()) {
            $remaining = $existing['expires_at'] - time();

            return back()->with('error', 'تا پایان اعتبار کد فعلی ' . $remaining . ' ثانیه صبر کنید.');
        }

        $code = random_int(100000, 999999);
        $this->storeResetOtp($token, $code);

        $testMode = Setting::isSmsTestMode();
        if (Setting::isSmsActive() && ! $testMode) {
            $result = SendOtpSmsJob::sendNow($pending['mobile'], $code, 'reset');
            if (! $result['ok']) {
                return back()->withErrors(['code' => $result['message'] ?? 'ارسال مجدد ناموفق بود.']);
            }

            return back()->with('success', 'کد جدید ارسال شد. اعتبار: ۱۲۰ ثانیه.');
        }

        return back()->with('success', 'کد جدید صادر شد (حالت تست). اعتبار: ۱۲۰ ثانیه.');
    }

    protected function storeResetOtp(string $token, int $code): int
    {
        $expiresAt = now()->addSeconds(self::RESET_OTP_TTL_SECONDS)->timestamp;

        cache()->put('password_reset_otp_' . $token, [
            'code' => (string) $code,
            'expires_at' => $expiresAt,
        ], now()->addSeconds(self::RESET_OTP_TTL_SECONDS + 60));

        return $expiresAt;
    }

    protected function getResetOtp(string $token): ?array
    {
        $data = cache()->get('password_reset_otp_' . $token);

        if (is_string($data) && $data !== '') {
            return null;
        }

        if (! is_array($data) || empty($data['code'])) {
            return null;
        }

        if (($data['expires_at'] ?? 0) <= time()) {
            return null;
        }

        return $data;
    }

    protected function normalizeMobileForLookup(string $mobile): ?string
    {
        return $this->registration->normalizeMobileForLookup($mobile);
    }
}
