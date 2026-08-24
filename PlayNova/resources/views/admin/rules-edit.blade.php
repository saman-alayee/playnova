@extends('layouts.app')
@section('title', 'ویرایش بخش قوانین | PlayNova')

@section('content')
<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold mb-6 text-primary">✏️ ویرایش بخش قوانین</h1>
    @include('admin._nav')

    <div class="bg-dark-800 border border-dark-600 rounded-xl p-6">
        <form method="POST" action="{{ route('admin.rules.update', $rule->id) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-gray-300 text-sm mb-1">متن قوانین</label>
                <textarea name="content" rows="15" required class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary">{{ $rule->content }}</textarea>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="bg-success hover:bg-green-700 text-white rounded px-6 py-2 font-bold transition">💾 ذخیره تغییرات</button>
                <a href="{{ route('admin.rules.manage') }}" class="bg-gray-600 hover:bg-gray-500 text-white rounded px-6 py-2 font-bold transition">بازگشت</a>
            </div>
        </form>
    </div>
</div>
@endsection