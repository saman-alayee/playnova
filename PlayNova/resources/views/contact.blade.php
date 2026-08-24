@extends('layouts.app')
@section('title', 'ارتباط با ما | PlayNova')

@section('content')
<div class="max-w-2xl mx-auto bg-dark-800 border border-dark-600 rounded-xl p-6 space-y-4">
    <h1 class="text-2xl font-bold mb-2">ارتباط با ما</h1>
    <p class="text-sm text-gray-400">برای پاسخ سوالات رایج، ابتدا بخش سوالات متداول را ببینید.</p>
    <div class="space-y-2 text-sm">
        @if($email)<p><span class="text-gray-500">ایمیل:</span> <a href="mailto:{{ $email }}" class="text-secondary">{{ $email }}</a></p>@endif
        @if($phone)<p><span class="text-gray-500">تلفن:</span> <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" dir="ltr" class="text-secondary">{{ $phone }}</a></p>@endif
    </div>
    <a href="{{ route('tickets.index') }}" class="inline-block mt-4 btn-glow-primary text-sm px-4 py-2 rounded-xl">مشاهده سوالات متداول</a>
</div>
@endsection
