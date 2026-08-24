@extends('layouts.app')
@section('title', 'ثبت‌نام | PlayNova')

@section('content')
<div class="max-w-md mx-auto bg-dark-800 border border-dark-600 rounded-xl p-6">
    <h1 class="text-2xl font-bold mb-6 text-center">ساخت حساب کاربری</h1>
    <form method="POST" action="{{ route('register.submit') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm mb-1 text-gray-400">نام کاربری</label>
            <input type="text" name="username" value="{{ old('username') }}" required
                class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 focus:border-secondary outline-none">
        </div>
        <div>
            <label class="block text-sm mb-1 text-gray-400">شماره موبایل</label>
            <input type="text" name="mobile" value="{{ old('mobile') }}" required inputmode="numeric" autocomplete="tel"
                class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 focus:border-secondary outline-none" placeholder="09123456789">
            @if(\App\Models\Setting::isSmsRegisterVerifyEnabled())
                <p class="text-xs text-gray-500 mt-1">پس از ثبت‌نام باید کد تأیید پیامکی را وارد کنید.</p>
            @endif
        </div>
        <div>
            <label class="block text-sm mb-1 text-gray-400">آیدی کالاف <span class="text-gray-500">(نام شما در بازی کالاف دیوتی)</span></label>
            <input type="text" name="cod_id" value="{{ old('cod_id') }}" required placeholder="نام داخل بازی"
                class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 focus:border-secondary outline-none">
        </div>
        <div>
            <label class="block text-sm mb-1 text-gray-400">رمز عبور</label>
            <input type="password" name="password" required autocomplete="new-password"
                class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 focus:border-secondary outline-none">
        </div>
        <div>
            <label class="block text-sm mb-1 text-gray-400">تکرار رمز عبور</label>
            <input type="password" name="password_confirmation" required autocomplete="new-password"
                class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 focus:border-secondary outline-none">
        </div>
        <div>
            <label class="block text-sm mb-1 text-gray-400">کد معرف (اختیاری)</label>
            <input type="text" name="referral_code" value="{{ old('referral_code') }}"
                class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 focus:border-secondary outline-none">
        </div>
        <label class="form-check">
            <input type="checkbox" name="accept_rules" value="1" required class="form-check__input">
            <span class="form-check__text">
                <a href="{{ route('rules') }}" target="_blank" rel="noopener" class="form-check__link">قوانین و مقررات</a>
                را مطالعه کرده و می‌پذیرم
            </span>
        </label>
        @include('components.captcha')
        <button class="w-full bg-success hover:opacity-90 text-white rounded py-2 font-bold shadow-glowsuccess">ثبت‌نام</button>
    </form>
    <p class="text-sm text-center mt-4 text-gray-400">
        قبلاً ثبت‌نام کرده‌اید؟ <a href="{{ route('login') }}" class="text-secondary">وارد شوید</a>
    </p>
</div>
@endsection
