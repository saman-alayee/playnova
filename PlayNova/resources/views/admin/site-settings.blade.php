@extends('layouts.app')
@section('title', 'تنظیمات سایت | پنل مدیریت')

@section('content')
<h1 class="text-2xl font-bold mb-6">تنظیمات سایت</h1>
@include('admin._nav')

<form method="POST" action="{{ route('admin.site-settings.update') }}" class="bg-dark-800 border border-dark-600 rounded-xl p-6 space-y-4 max-w-3xl">
    @csrf @method('PUT')
    <div>
        <label class="text-sm text-gray-400">متن حریم خصوصی</label>
        <textarea name="privacy_content" rows="6" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 mt-1">{{ $privacyContent }}</textarea>
    </div>
    <div>
        <label class="text-sm text-gray-400">متن درباره ما</label>
        <textarea name="about_content" rows="6" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 mt-1">{{ $aboutContent }}</textarea>
    </div>
    <div class="grid sm:grid-cols-2 gap-3">
        <input type="email" name="contact_email" value="{{ $contactEmail }}" placeholder="ایمیل تماس" class="bg-dark-700 border border-dark-600 rounded px-3 py-2">
        <input type="text" name="contact_phone" value="{{ $contactPhone }}" placeholder="تلفن" class="bg-dark-700 border border-dark-600 rounded px-3 py-2">
    </div>
    <textarea name="contact_address" rows="2" placeholder="آدرس" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2">{{ $contactAddress }}</textarea>
    <h3 class="font-bold pt-2">شبکه‌های اجتماعی (منوی همبرگری)</h3>
    <div class="grid sm:grid-cols-3 gap-3">
        <input type="text" name="social_telegram" value="{{ $socialTelegram }}" placeholder="تلگرام (@channel)" class="bg-dark-700 border border-dark-600 rounded px-3 py-2">
        <input type="text" name="social_rubika" value="{{ $socialRubika }}" placeholder="روبیکا (@id)" class="bg-dark-700 border border-dark-600 rounded px-3 py-2">
        <input type="text" name="social_instagram" value="{{ $socialInstagram }}" placeholder="اینستاگرام" class="bg-dark-700 border border-dark-600 rounded px-3 py-2">
    </div>
    <h3 class="font-bold pt-2">کانال‌های اعلام نتایج</h3>
    <div class="grid sm:grid-cols-2 gap-3">
        <input type="text" name="results_telegram" value="{{ $resultsTelegram }}" placeholder="آیدی کانال تلگرام" class="bg-dark-700 border border-dark-600 rounded px-3 py-2">
        <input type="text" name="results_rubika" value="{{ $resultsRubika }}" placeholder="آیدی کانال روبیکا" class="bg-dark-700 border border-dark-600 rounded px-3 py-2">
    </div>
    <button class="bg-success text-white rounded px-4 py-2 font-bold">ذخیره تنظیمات</button>
</form>
@endsection
