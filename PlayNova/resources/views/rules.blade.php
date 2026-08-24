@extends('layouts.app')
@section('title', 'قوانین و مقررات | PlayNova')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-6 border border-gray-700">
        <h1 class="text-2xl font-bold text-center mb-6 text-primary">📜 قوانین و مقررات PlayNova</h1>

        @if($ruleSections->isEmpty())
            <p class="text-gray-500 text-center py-8">قوانین هنوز ثبت نشده است.</p>
        @else
            @foreach($ruleSections as $index => $section)
                <div class="border-b border-gray-700 pb-6 mb-6 last:border-0 last:mb-0">
                    <h2 class="font-bold text-lg mb-3 text-secondary">بخش {{ $index + 1 }}</h2>
                    <div class="text-gray-300 leading-8 text-sm whitespace-pre-line">
                        {{ $section->content }}
                    </div>
                </div>
            @endforeach
        @endif

        {{-- ویرایش قوانین (فقط برای ادمین) --}}
        @auth
            @if(auth()->user()->is_admin)
                <div class="mt-8 border-t border-gray-700 pt-6">
                    <h3 class="text-lg font-bold mb-4 text-primary">✏️ ویرایش قوانین</h3>
                    
                    {{-- ویرایش بخش‌های موجود --}}
                    @foreach($ruleSections as $index => $section)
                        <details class="mb-4 bg-gray-900/50 rounded-lg p-4 border border-gray-700">
                            <summary class="cursor-pointer text-sm font-bold text-secondary hover:text-primary transition">
                                ✏️ ویرایش بخش {{ $index + 1 }}
                            </summary>
                            <form method="POST" action="{{ route('rules.save') }}" class="mt-3 space-y-3">
                                @csrf
                                <input type="hidden" name="section_id" value="{{ $section->id }}">
                                <textarea name="content" rows="4" 
                                    class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white text-sm focus:outline-none focus:border-primary">{{ $section->content }}</textarea>
                                <button type="submit" class="bg-primary hover:opacity-90 text-white rounded-lg px-6 py-2 text-sm font-bold transition">
                                    💾 ذخیره این بخش
                                </button>
                            </form>
                        </details>
                    @endforeach

                    {{-- افزودن بخش جدید --}}
                    <details class="bg-gray-900/50 rounded-lg p-4 border border-gray-700">
                        <summary class="cursor-pointer text-sm font-bold text-green-400 hover:text-green-300 transition">
                            ➕ افزودن بخش جدید
                        </summary>
                        <form method="POST" action="{{ route('rules.save') }}" class="mt-3 space-y-3">
                            @csrf
                            <textarea name="content" rows="4" placeholder="متن بخش جدید را وارد کنید..." 
                                class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white text-sm focus:outline-none focus:border-primary"></textarea>
                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white rounded-lg px-6 py-2 text-sm font-bold transition">
                                ➕ افزودن بخش جدید
                            </button>
                        </form>
                    </details>
                </div>
            @endif
        @endauth
    </div>
</div>
@endsection