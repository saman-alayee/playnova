@extends('layouts.app')
@section('title', 'مدیریت قوانین | PlayNova')

@section('content')
<div class="max-w-6xl mx-auto">
    <h1 class="text-2xl font-bold mb-6 text-primary">📜 مدیریت قوانین و مقررات</h1>
    @include('admin._nav')

    <!-- فرم افزودن بخش جدید -->
    <div class="bg-dark-800 border border-dark-600 rounded-xl p-6 mb-6">
        <h2 class="font-bold text-lg mb-4 text-secondary">➕ افزودن بخش جدید</h2>
        <form method="POST" action="{{ route('admin.rules.store') }}" class="space-y-3">
            @csrf
            <div>
                <label class="block text-gray-300 text-sm mb-1">متن قوانین</label>
                <textarea name="content" rows="6" required class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary" placeholder="متن قوانین را وارد کنید..."></textarea>
            </div>
            <button type="submit" class="bg-success hover:bg-green-700 text-white rounded px-6 py-2 font-bold transition">➕ افزودن بخش</button>
        </form>
    </div>

    <!-- لیست بخش‌های موجود -->
    <div class="bg-dark-800 border border-dark-600 rounded-xl p-6">
        <h2 class="font-bold text-lg mb-4 text-primary">📋 بخش‌های موجود ({{ $rules->count() }})</h2>

        @if($rules->isEmpty())
            <p class="text-gray-500 text-center py-6">هیچ بخشی ثبت نشده است.</p>
        @else
            <div class="space-y-4">
                @foreach($rules as $index => $rule)
                    <div class="border border-gray-700 rounded-lg p-4 bg-dark-900/30">
                        <div class="flex flex-wrap justify-between items-start gap-3">
                            <div class="flex-1">
                                <p class="text-xs text-gray-500">بخش {{ $index + 1 }}</p>
                                <p class="text-sm text-gray-300 mt-1">{{ Str::limit($rule->content, 150) }}</p>
                            </div>
                            <div class="flex gap-2">
                                <!-- دکمه ویرایش -->
                                <a href="{{ route('admin.rules.edit', $rule->id) }}" class="text-xs bg-secondary/20 text-secondary px-3 py-1 rounded hover:bg-secondary/30 transition">✏️ ویرایش</a>
                                <!-- فرم حذف -->
                                <form method="POST" action="{{ route('admin.rules.delete', $rule->id) }}" onsubmit="return confirm('آیا از حذف این بخش مطمئن هستید؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs bg-danger/20 text-danger px-3 py-1 rounded hover:bg-danger/30 transition">🗑️ حذف</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection