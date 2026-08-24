@extends('layouts.app')
@section('title', 'فراموشی رمز عبور | PlayNova')

@section('content')
<div class="max-w-md mx-auto bg-dark-800 border border-dark-600 rounded-xl p-6 space-y-6">
    <div>
        <h1 class="text-xl font-bold mb-2">بازیابی رمز عبور</h1>
        <p class="text-sm text-gray-400 mb-4">شماره موبایل ثبت‌شده در حساب خود را وارد کنید. کد تأیید برای شما ارسال می‌شود.</p>
        <form method="POST" action="{{ route('password.send-code') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm mb-1 text-gray-400">شماره موبایل</label>
                <input type="text" name="mobile" value="{{ old('mobile') }}" required
                    class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 focus:border-secondary outline-none"
                    placeholder="09123456789" dir="ltr">
            </div>
            <button class="w-full bg-secondary hover:opacity-90 text-white rounded py-2 font-bold">ارسال کد تأیید</button>
        </form>
    </div>
    <p class="text-sm text-center text-gray-400">
        <a href="{{ route('login') }}" class="text-secondary">بازگشت به ورود</a>
    </p>
</div>
@endsection
