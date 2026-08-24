@extends('layouts.app')
@section('title', 'تأیید موبایل | PlayNova')

@section('content')
<div class="max-w-md mx-auto bg-dark-800 border border-dark-600 rounded-xl p-6">
    <h1 class="text-2xl font-bold mb-2 text-center">تأیید شماره موبایل</h1>
    <p class="text-sm text-gray-400 text-center mb-6">کد ارسال‌شده به <span class="text-secondary font-bold" dir="ltr">{{ $maskedMobile }}</span> را وارد کنید.</p>

    @if($testMode && $testCode)
        <div class="mb-4 p-3 rounded-lg border border-yellow-500/40 bg-yellow-500/10 text-yellow-200 text-sm text-center">
            حالت تست — کد تأیید: <strong dir="ltr" class="text-lg">{{ $testCode }}</strong>
        </div>
    @endif

    <form method="POST" action="{{ route('register.verify.submit', $token) }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm mb-1 text-gray-400">کد ۶ رقمی</label>
            <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" required autofocus
                class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 text-center text-xl tracking-widest focus:border-secondary outline-none"
                placeholder="------">
        </div>
        <button class="w-full bg-success hover:opacity-90 text-white rounded py-2 font-bold shadow-glowsuccess">تأیید و تکمیل ثبت‌نام</button>
    </form>

    <form method="POST" action="{{ route('register.verify.resend', $token) }}" class="mt-3">
        @csrf
        <button type="submit" class="w-full text-sm text-secondary hover:underline">ارسال مجدد کد</button>
    </form>

    <p class="text-sm text-center mt-4 text-gray-400">
        <a href="{{ route('register') }}" class="text-secondary">بازگشت به ثبت‌نام</a>
    </p>
</div>
@endsection
