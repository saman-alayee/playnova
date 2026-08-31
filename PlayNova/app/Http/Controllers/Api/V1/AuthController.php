<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\UserResource;
use App\Jobs\SendOtpSmsJob;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use App\Modules\Audit\Services\ActivityLogService;
use App\Modules\User\Services\AuthRegistrationService;
use App\Services\CaptchaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;

class AuthController extends BaseApiController
{
    private const RESET_OTP_TTL_SECONDS = 120;

    public function __construct(
        protected AuthRegistrationService $registration,
        protected ActivityLogService $activity,
    ) {}

    public function captcha(): JsonResponse
    {
        return $this->success(CaptchaService::issue());
    }

    public function login(Request $request): JsonResponse
    {
        $login = trim((string) ($request->input('login') ?: $request->input('mobile')));
        $request->merge(['login' => $login]);

        $validator = Validator::make(
            $request->all(),
            array_merge([
                'login' => 'required|string',
                'password' => 'required|string',
            ], CaptchaService::apiRules()),
            array_merge([
                'login.required' => 'نام کاربری یا موبایل الزامی است.',
                'password.required' => 'رمز عبور الزامی است.',
            ], CaptchaService::messages())
        );

        if ($validator->fails()) {
            return $this->error('اطلاعات ورود نامعتبر است.', 422, $validator->errors());
        }

        if (! CaptchaService::validateWithKey($request->input('captcha_key'), $request->input('captcha'))) {
            return $this->error('کد امنیتی صحیح نیست.', 422, ['captcha' => ['کد امنیتی صحیح نیست.']]);
        }

        $user = User::with('latestKycSubmission')->findByLogin($login);

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->error('اطلاعات ورود صحیح نیست.', 401);
        }

        $token = $user->createToken('api')->plainTextToken;

        $this->activity->logAuth($user, 'login', 'ورود به حساب کاربری');

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'خوش آمدید ' . $user->username);
    }

    public function register(Request $request): JsonResponse
    {
        $inputError = $this->registration->normalizeRegistrationInput($request);
        if ($inputError) {
            return $this->error('اطلاعات ثبت‌نام نامعتبر است.', 422, $inputError);
        }

        $validator = Validator::make(
            $request->all(),
            array_merge($this->registration->registrationRules(), CaptchaService::apiRules()),
            array_merge($this->registration->registrationMessages(), CaptchaService::messages())
        );

        if ($validator->fails()) {
            return $this->error('اطلاعات ثبت‌نام نامعتبر است.', 422, $validator->errors());
        }

        if (! CaptchaService::validateWithKey($request->input('captcha_key'), $request->input('captcha'))) {
            return $this->error('کد امنیتی صحیح نیست.', 422, ['captcha' => ['کد امنیتی صحیح نیست.']]);
        }

        if (Setting::isSmsRegisterVerifyEnabled()) {
            return $this->startRegisterMobileVerification($request);
        }

        return $this->createUserFromRequest($request);
    }

    public function showRegisterVerify(string $token): JsonResponse
    {
        if (! cache()->has('register_pending_' . $token)) {
            return $this->error('نشست تأیید منقضی شده. دوباره ثبت‌نام کنید.', 410);
        }

        $pending = cache()->get('register_pending_' . $token);
        $mobile = $pending['mobile'] ?? '';
        $testMode = Setting::isSmsTestMode() || Setting::isSmsRegisterTestMode();
        $testCode = $testMode ? cache()->get('register_otp_' . $token) : null;

        return $this->success([
            'token' => $token,
            'masked_mobile' => $this->registration->maskMobile($mobile),
            'test_mode' => $testMode,
            'test_code' => $testCode,
        ]);
    }

    public function verifyRegister(Request $request, string $token): JsonResponse
    {
        $request->validate(['code' => 'required|digits:6']);

        $pending = cache()->get('register_pending_' . $token);
        $cachedOtp = cache()->get('register_otp_' . $token);

        if (! $pending || ! $cachedOtp) {
            return $this->error('نشست تأیید منقضی شده. دوباره ثبت‌نام کنید.', 410);
        }

        if ((string) $cachedOtp !== (string) $request->code) {
            return $this->error('کد وارد شده صحیح نیست.', 422, ['code' => ['کد وارد شده صحیح نیست.']]);
        }

        $duplicateError = $this->registration->ensureRegistrationAvailable(
            $pending['username'] ?? '',
            $pending['mobile'] ?? '',
            $pending['cod_id'] ?? null
        );

        if ($duplicateError) {
            cache()->forget('register_pending_' . $token);
            cache()->forget('register_otp_' . $token);

            return $this->error('اطلاعات ثبت‌نام دیگر معتبر نیست.', 422, $duplicateError);
        }

        cache()->forget('register_pending_' . $token);
        cache()->forget('register_otp_' . $token);

        try {
            $user = User::create([
                'name' => $pending['username'],
                'username' => $pending['username'],
                'email' => null,
                'mobile' => $pending['mobile'] ?? null,
                'password' => $pending['password_hash'],
                'cod_id' => User::normalizeCodIdForStorage($pending['cod_id'] ?? null),
                'referral_code' => User::generateReferralCode(),
                'referred_by' => $pending['referred_by'] ?? null,
            ]);
        } catch (QueryException $e) {
            if ($this->isDuplicateCodIdException($e)) {
                return $this->error('اطلاعات ثبت‌نام نامعتبر است.', 422, [
                    'cod_id' => ['این آیدی کالاف قبلاً توسط کاربر دیگری ثبت شده است.'],
                ]);
            }

            throw $e;
        }

        $plainTextToken = $user->createToken('api')->plainTextToken;

        return $this->success([
            'user' => new UserResource($user),
            'token' => $plainTextToken,
        ], 'ثبت‌نام با موفقیت انجام شد. خوش آمدید!', 201);
    }

    public function resendRegisterVerify(string $token): JsonResponse
    {
        $pending = cache()->get('register_pending_' . $token);
        if (! $pending || empty($pending['mobile'])) {
            return $this->error('نشست تأیید منقضی شده.', 410);
        }

        $code = random_int(100000, 999999);
        cache()->put('register_otp_' . $token, (string) $code, now()->addMinutes(15));

        $testMode = Setting::isSmsTestMode() || Setting::isSmsRegisterTestMode();

        if (Setting::isSmsActive() && ! $testMode) {
            $result = SendOtpSmsJob::sendNow($pending['mobile'], $code, 'register');
            if (! $result['ok']) {
                return $this->error($result['message'] ?? 'ارسال مجدد ناموفق بود.', 422);
            }

            return $this->success(null, 'کد جدید ارسال شد.');
        }

        return $this->success([
            'test_mode' => true,
            'test_code' => (string) $code,
        ], 'کد جدید صادر شد (حالت تست).');
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->activity->logAuth($user, 'logout', 'خروج از حساب کاربری');
        $user->currentAccessToken()?->delete();

        return $this->success(null, 'با موفقیت خارج شدید.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('latestKycSubmission');
        $user->unread_notifications_count = Notification::query()
            ->where('user_id', $user->id)
            ->visibleInInbox()
            ->where('is_read', false)
            ->count();

        return $this->success(new UserResource($user));
    }

    public function sendResetCode(Request $request): JsonResponse
    {
        $request->validate(['mobile' => 'required|string|max:20']);

        $normalized = $this->registration->normalizeMobileForLookup($request->mobile);
        if (! $normalized) {
            return $this->error('شماره موبایل معتبر نیست.', 422, ['mobile' => ['شماره موبایل معتبر نیست.']]);
        }

        $user = User::where('mobile', $normalized)->first();
        if (! $user) {
            return $this->error('کاربری با این شماره موبایل یافت نشد.', 404, ['mobile' => ['کاربری با این شماره موبایل یافت نشد.']]);
        }

        $token = Str::random(40);
        $code = random_int(100000, 999999);

        cache()->put('password_reset_pending_' . $token, [
            'user_id' => $user->id,
            'mobile' => $user->mobile,
        ], now()->addMinutes(30));

        $this->storeResetOtp($token, $code);

        $testMode = Setting::isSmsTestMode();
        if (Setting::isSmsActive() && ! $testMode) {
            $provider = strtolower((string) Setting::getSmsProvider());
            if ($provider !== 'melipayamak') {
                return $this->error('سرویس پیامک پشتیبانی نمی‌شود.', 422);
            }
            $result = SendOtpSmsJob::sendNow($user->mobile, $code, 'reset');
            if (! $result['ok']) {
                return $this->error($result['message'] ?? 'ارسال پیامک ناموفق بود.', 422);
            }
        }

        return $this->success([
            'token' => $token,
            'masked_mobile' => $this->registration->maskMobile($user->mobile),
            'test_mode' => $testMode,
            'test_code' => $testMode ? (string) $code : null,
        ], 'کد بازیابی ارسال شد.');
    }

    public function showResetPasswordVerify(string $token): JsonResponse
    {
        if (! cache()->has('password_reset_pending_' . $token)) {
            return $this->error('نشست بازیابی منقضی شده.', 410);
        }

        $pending = cache()->get('password_reset_pending_' . $token);
        $testMode = Setting::isSmsTestMode();
        $otp = $this->getResetOtp($token);

        return $this->success([
            'token' => $token,
            'masked_mobile' => $this->registration->maskMobile($pending['mobile'] ?? ''),
            'test_mode' => $testMode,
            'test_code' => $testMode && $otp ? $otp['code'] : null,
            'seconds_left' => max(0, ($otp['expires_at'] ?? time()) - time()),
        ]);
    }

    public function resetPassword(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'code' => 'required|digits:6',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $pending = cache()->get('password_reset_pending_' . $token);
        $otp = $this->getResetOtp($token);

        if (! $pending || ! $otp) {
            return $this->error('کد تأیید منقضی شده است.', 410);
        }

        if ((string) $otp['code'] !== (string) $request->code) {
            return $this->error('کد وارد شده صحیح نیست.', 422, ['code' => ['کد وارد شده صحیح نیست.']]);
        }

        $user = User::find($pending['user_id']);
        if (! $user) {
            return $this->error('کاربر یافت نشد.', 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        cache()->forget('password_reset_pending_' . $token);
        cache()->forget('password_reset_otp_' . $token);

        return $this->success(null, 'رمز عبور با موفقیت تغییر کرد.');
    }

    public function resendResetCode(string $token): JsonResponse
    {
        $pending = cache()->get('password_reset_pending_' . $token);
        if (! $pending || empty($pending['mobile'])) {
            return $this->error('نشست بازیابی منقضی شده.', 410);
        }

        $existing = cache()->get('password_reset_otp_' . $token);
        if (is_array($existing) && ($existing['expires_at'] ?? 0) > time()) {
            return $this->error('تا پایان اعتبار کد فعلی صبر کنید.', 429);
        }

        $code = random_int(100000, 999999);
        $this->storeResetOtp($token, $code);

        $testMode = Setting::isSmsTestMode();
        if (Setting::isSmsActive() && ! $testMode) {
            $result = SendOtpSmsJob::sendNow($pending['mobile'], $code, 'reset');
            if (! $result['ok']) {
                return $this->error($result['message'] ?? 'ارسال مجدد ناموفق بود.', 422);
            }

            return $this->success(null, 'کد جدید ارسال شد.');
        }

        return $this->success([
            'test_mode' => true,
            'test_code' => (string) $code,
        ], 'کد جدید صادر شد (حالت تست).');
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

    protected function startRegisterMobileVerification(Request $request): JsonResponse
    {
        $result = $this->registration->startMobileVerification($request);

        if (! $result['ok']) {
            return $this->error($result['error'], 422, [$result['field'] ?? 'mobile' => [$result['error']]]);
        }

        $testMode = Setting::isSmsTestMode() || Setting::isSmsRegisterTestMode();
        $testCode = $testMode ? cache()->get('register_otp_' . $result['token']) : null;

        return $this->success([
            'verification_required' => true,
            'token' => $result['token'],
            'masked_mobile' => $this->registration->maskMobile($request->mobile),
            'test_mode' => $testMode,
            'test_code' => $testCode,
        ], 'کد تأیید ارسال شد.', 202);
    }

    protected function createUserFromRequest(Request $request): JsonResponse
    {
        try {
            $user = $this->registration->createUserFromRequest($request);
        } catch (QueryException $e) {
            if ($this->isDuplicateCodIdException($e)) {
                return $this->error('اطلاعات ثبت‌نام نامعتبر است.', 422, [
                    'cod_id' => ['این آیدی کالاف قبلاً توسط کاربر دیگری ثبت شده است.'],
                ]);
            }

            throw $e;
        }

        $plainTextToken = $user->createToken('api')->plainTextToken;

        return $this->success([
            'user' => new UserResource($user),
            'token' => $plainTextToken,
        ], 'ثبت‌نام با موفقیت انجام شد. خوش آمدید!', 201);
    }

    protected function isDuplicateCodIdException(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'users_cod_id_unique')
            || (str_contains($message, 'duplicate') && str_contains($message, 'cod_id'));
    }
}
