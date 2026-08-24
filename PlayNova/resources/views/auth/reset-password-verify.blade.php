@extends('layouts.app')
@section('title', 'تعیین رمز جدید | PlayNova')

@section('content')
<div class="max-w-md mx-auto bg-dark-800 border border-dark-600 rounded-xl p-6">
    <h1 class="text-2xl font-bold mb-2 text-center">تعیین رمز جدید</h1>
    <p class="text-sm text-gray-400 text-center mb-4">
        کد ارسال‌شده به <span class="text-secondary font-bold" dir="ltr">{{ $maskedMobile }}</span> را وارد کنید و رمز جدید را تعیین کنید.
    </p>

    <div id="otp-timer-box" class="mb-4 p-3 rounded-lg border text-sm text-center {{ ($otpExpired ?? false) ? 'border-red-700/50 bg-red-900/20 text-red-300' : 'border-secondary/40 bg-secondary/10 text-secondary' }}">
        <span id="otp-timer-label">{{ ($otpExpired ?? false) ? 'کد تأیید منقضی شده است.' : 'اعتبار کد:' }}</span>
        <strong id="otp-timer" dir="ltr" class="text-lg font-mono {{ ($otpExpired ?? false) ? 'hidden' : '' }}">{{ sprintf('%02d:%02d', intdiv($secondsLeft ?? 120, 60), ($secondsLeft ?? 120) % 60) }}</strong>
    </div>

    @if($testMode && $testCode)
        <div class="mb-4 p-3 rounded-lg border border-yellow-500/40 bg-yellow-500/10 text-yellow-200 text-sm text-center">
            حالت تست — کد تأیید: <strong dir="ltr" class="text-lg">{{ $testCode }}</strong>
        </div>
    @endif

    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg border border-green-700/50 bg-green-900/20 text-green-300 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 rounded-lg border border-red-700/50 bg-red-900/20 text-red-300 text-sm">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('password.reset', $token) }}" id="reset-form" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm mb-1 text-gray-400">شماره موبایل</label>
            <input type="text" value="{{ $maskedMobile }}" readonly dir="ltr"
                class="w-full bg-dark-900 border border-dark-600 rounded px-3 py-2 text-gray-400 cursor-not-allowed">
        </div>
        <div>
            <label class="block text-sm mb-1 text-gray-400">کد ۶ رقمی پیامک</label>
            <input type="text" name="code" id="otp-code-input" inputmode="numeric" pattern="[0-9]*" maxlength="6" required autofocus
                class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-center text-xl tracking-widest focus:border-secondary outline-none {{ ($otpExpired ?? false) ? 'opacity-50' : '' }}"
                placeholder="------" {{ ($otpExpired ?? false) ? 'disabled' : '' }}>
            @error('code')<p class="text-red-400 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm mb-1 text-gray-400">رمز عبور جدید</label>
            <input type="password" name="password" id="password-input" required minlength="6"
                class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 focus:border-secondary outline-none {{ ($otpExpired ?? false) ? 'opacity-50' : '' }}"
                {{ ($otpExpired ?? false) ? 'disabled' : '' }}>
        </div>
        <div>
            <label class="block text-sm mb-1 text-gray-400">تکرار رمز عبور</label>
            <input type="password" name="password_confirmation" id="password-confirm-input" required minlength="6"
                class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 focus:border-secondary outline-none {{ ($otpExpired ?? false) ? 'opacity-50' : '' }}"
                {{ ($otpExpired ?? false) ? 'disabled' : '' }}>
        </div>
        <button type="submit" id="submit-btn" class="w-full bg-success hover:opacity-90 text-white rounded py-2 font-bold shadow-glowsuccess disabled:opacity-50 disabled:cursor-not-allowed" {{ ($otpExpired ?? false) ? 'disabled' : '' }}>
            تغییر رمز عبور
        </button>
    </form>

    <form method="POST" action="{{ route('password.reset.resend', $token) }}" id="resend-form" class="mt-3">
        @csrf
        <button type="submit" id="resend-btn" class="w-full text-sm text-secondary hover:underline disabled:opacity-40 disabled:cursor-not-allowed disabled:no-underline" {{ ($otpExpired ?? false) ? '' : 'disabled' }}>
            ارسال مجدد کد
        </button>
    </form>

    <p class="text-sm text-center mt-4 text-gray-400">
        <a href="{{ route('password.request') }}" class="text-secondary">بازگشت</a>
        ·
        <a href="{{ route('login') }}" class="text-secondary">ورود</a>
    </p>
</div>

<script>
(function () {
    let secondsLeft = {{ (int) ($secondsLeft ?? 0) }};
    const timerEl = document.getElementById('otp-timer');
    const timerLabel = document.getElementById('otp-timer-label');
    const timerBox = document.getElementById('otp-timer-box');
    const submitBtn = document.getElementById('submit-btn');
    const resendBtn = document.getElementById('resend-btn');
    const codeInput = document.getElementById('otp-code-input');
    const passwordInput = document.getElementById('password-input');
    const passwordConfirmInput = document.getElementById('password-confirm-input');

    function formatTime(total) {
        const m = Math.floor(total / 60);
        const s = total % 60;
        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }

    function markExpired() {
        timerLabel.textContent = 'کد تأیید منقضی شده است.';
        timerEl.classList.add('hidden');
        timerBox.classList.remove('border-secondary/40', 'bg-secondary/10', 'text-secondary');
        timerBox.classList.add('border-red-700/50', 'bg-red-900/20', 'text-red-300');
        submitBtn.disabled = true;
        resendBtn.disabled = false;
        codeInput.disabled = true;
        passwordInput.disabled = true;
        passwordConfirmInput.disabled = true;
    }

    function tick() {
        if (secondsLeft <= 0) {
            markExpired();
            return;
        }
        timerEl.textContent = formatTime(secondsLeft);
        secondsLeft -= 1;
        window.setTimeout(tick, 1000);
    }

    if (secondsLeft > 0) {
        tick();
    } else {
        markExpired();
    }
})();
</script>
@endsection
