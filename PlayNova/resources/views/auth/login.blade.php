@extends('layouts.app')
@section('title', 'ورود | PlayNova')

@section('content')
<div class="max-w-md mx-auto bg-dark-800 border border-dark-600 rounded-xl p-6">
    <h1 class="text-2xl font-bold mb-6 text-center">ورود به حساب کاربری</h1>
    <form method="POST" action="{{ route('login.submit') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm mb-1 text-gray-400">شماره موبایل</label>
            <input type="text" name="mobile" value="{{ old('mobile') }}" required inputmode="numeric" autocomplete="tel"
                class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 focus:border-secondary outline-none" placeholder="09123456789">
        </div>
        <div>
            <label class="block text-sm mb-1 text-gray-400">رمز عبور</label>
            <input type="password" name="password" required autocomplete="current-password"
                class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 focus:border-secondary outline-none">
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <label class="inline-flex items-center gap-2 text-sm text-gray-400 w-fit max-w-full">
                <input type="checkbox" name="remember" class="shrink-0">
                <span class="whitespace-nowrap">مرا به خاطر بسپار</span>
            </label>
            <a href="{{ route('password.request') }}" class="text-red-500 hover:text-red-400 font-bold text-base whitespace-nowrap">فراموشی رمز عبور</a>
        </div>
        @include('components.captcha')
        <button class="w-full bg-success hover:opacity-90 text-white rounded py-2 font-bold shadow-glowsuccess">ورود</button>
    </form>
    <p class="text-sm text-center mt-4 text-gray-400">
        حساب کاربری ندارید؟ <a href="{{ route('register') }}" class="text-secondary">ثبت‌نام کنید</a>
    </p>
</div>
@endsection
