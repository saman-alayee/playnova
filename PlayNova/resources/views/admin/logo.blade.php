@extends('layouts.app')

@section('title', 'تغییر لوگو | پنل مدیریت')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-gray-700">
        <h2 class="text-2xl font-bold text-center mb-6 text-primary">🖼️ تغییر لوگوی سایت</h2>

        <!-- نمایش لوگوی فعلی -->
        <div class="text-center mb-6">
            <p class="text-gray-400 text-sm mb-2">لوگوی فعلی:</p>
            <div class="inline-block bg-dark-900/50 p-4 rounded-lg border border-gray-700">
                @php
                    $logoPath = \App\Models\Setting::get('logo');
                @endphp
                @if($logoPath && file_exists(public_path('storage/' . $logoPath)))
                    <img src="{{ asset('storage/' . $logoPath) }}" class="h-16 md:h-20 object-contain" alt="لوگو">
                @else
                    <div class="w-16 h-16 bg-gradient-to-br from-primary to-secondary rounded-xl flex items-center justify-center font-bold text-2xl text-white mx-auto">PN</div>
                @endif
            </div>
        </div>

        <!-- فرم آپلود لوگو -->
        <form method="POST" action="{{ route('admin.logo.update') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-gray-300 text-sm mb-2">آپلود لوگوی جدید (png, jpg, svg - حداکثر ۲ مگابایت)</label>
                <input type="file" name="logo" accept="image/*" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-primary" required>
                @error('logo')
                    <span class="text-danger text-sm">{{ $message }}</span>
                @enderror
            </div>

            <button type="submit" class="w-full btn-glow-success py-3 rounded-lg text-white font-bold">💾 ذخیره لوگو</button>
        </form>

        <!-- دکمه حذف لوگو و بازگشت به حالت پیش‌فرض -->
        <div class="mt-4 text-center">
            <form method="POST" action="{{ route('admin.logo.delete') }}" class="inline" onsubmit="return confirm('آیا از حذف لوگو و بازگشت به حالت پیش‌فرض مطمئن هستید؟')">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-danger hover:text-red-400 text-sm">🗑️ حذف لوگو و بازگشت به حالت پیش‌فرض</button>
            </form>
        </div>
    </div>
</div>
@endsection