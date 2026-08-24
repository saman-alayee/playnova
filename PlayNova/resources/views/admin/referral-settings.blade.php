@extends('layouts.app')
@section('title', 'تنظیمات دعوت | پنل مدیریت')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold mb-6 text-primary">🔗 تنظیمات سیستم دعوت</h1>
    @include('admin._nav')

    <div class="bg-dark-800 border border-dark-600 rounded-xl p-6">
        <form method="POST" action="{{ route('admin.referral.settings.update') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-gray-300 text-sm mb-1">درصد پاداش معرف (از اولین شارژ کاربر معرف)</label>
                <input type="number" name="bonus_percent" value="{{ old('bonus_percent', $bonusPercent) }}" 
                       class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white"
                       min="0" max="100" step="0.5" required>
                <p class="text-xs text-gray-500 mt-1">مثلاً ۵ به معنای ۵٪ از مبلغ اولین شارژ کاربر معرف</p>
            </div>
            <button type="submit" class="w-full bg-success hover:opacity-90 text-white rounded py-2 font-bold">💾 ذخیره تنظیمات</button>
        </form>
        <div class="mt-4 text-center text-sm text-gray-500">
            <p>🔹 این درصد به کاربری که دوست خود را معرفی کرده، از اولین شارژ دوستش تعلق می‌گیرد.</p>
        </div>
    </div>
</div>
@endsection