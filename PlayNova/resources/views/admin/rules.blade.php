@extends('layouts.app')

@section('title', 'ویرایش قوانین | پنل مدیریت')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-6 border border-gray-700">
        <h2 class="text-2xl font-bold text-center mb-6 text-primary">✏️ ویرایش قوانین و مقررات</h2>

        <form method="POST" action="{{ route('admin.rules.update') }}">
            @csrf
            @method('PUT')
            <div class="mb-4">
                <label class="block text-gray-300 text-sm mb-2">متن قوانین</label>
                <textarea name="content" rows="20" class="w-full bg-gray-900/50 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-red-500">{{ $rule->content ?? '' }}</textarea>
            </div>
            <button type="submit" class="w-full bg-success px-6 py-3 rounded-lg hover:opacity-90 transition font-bold">
                💾 ذخیره تغییرات
            </button>
        </form>
    </div>
</div>
@endsection