@extends('layouts.app')
@section('title', 'تاریخچه مسابقات | PlayNova')

@section('content')
@if(session('game_login_info'))
    <div class="mb-4 bg-primary/20 border border-primary/40 rounded-xl p-4">
        <h3 class="font-bold mb-2">🎮 اطلاعات ورود — {{ session('game_login_title') }}</h3>
        <p class="text-sm whitespace-pre-line">{{ session('game_login_info') }}</p>
    </div>
@endif
<h1 class="text-2xl font-bold mb-6 text-center">🏆 تاریخچه مسابقات پایان‌یافته</h1>
@php $resultsChannels = \App\Models\Setting::resultsChannelItems(); @endphp
<div class="mb-6 p-4 bg-dark-800 border border-dark-600 rounded-xl text-sm text-gray-300">
    <p class="text-center mb-3">نتایج بازی در شبکه‌های اجتماعی نمایش داده می‌شود.</p>
    <div class="sidebar-social__grid">
        @foreach($resultsChannels as $item)
            <a href="{{ $item['url'] ?? '#' }}" target="_blank" rel="noopener noreferrer"
               class="sidebar-social__link {{ $item['url'] ? '' : 'is-disabled' }}"
               title="{{ $item['title'] }}" @if(!$item['url']) aria-disabled="true" @endif>
                <span class="sidebar-social__icon">
                    <img src="{{ $item['icon'] }}" alt="{{ $item['title'] }}" width="36" height="36" loading="lazy">
                </span>
            </a>
        @endforeach
    </div>
</div>

@if($finishedTournaments->isEmpty())
    <div class="text-center py-12 bg-dark-800/50 rounded-xl border border-dark-600">
        <p class="text-gray-500">هیچ مسابقه پایان‌یافته‌ای وجود ندارد.</p>
    </div>
@else
    <div class="space-y-6">
        @foreach($finishedTournaments as $tournament)
            <div class="bg-dark-800 border border-dark-600 rounded-xl p-6 card-tournament">
                <div class="flex flex-wrap justify-between items-start gap-4 mb-4">
                    <div>
                        <h2 class="text-xl font-bold text-primary">{{ $tournament->title }}</h2>
                        <p class="text-sm text-gray-400">{{ $tournament->game }}</p>
                    </div>
                    <span class="text-xs bg-success/20 text-success px-3 py-1 rounded-full">پایان‌یافته</span>
                </div>

                <div class="text-sm space-y-2">
                    <div class="flex justify-between gap-2">
                        <span class="text-gray-400">ورودی:</span>
                        <span class="font-bold text-white">{{ number_format($tournament->entry_fee) }} تومان</span>
                    </div>
                    <div class="flex justify-between gap-2">
                        <span class="text-gray-400">جایزه:</span>
                        <span class="font-bold text-secondary">{{ number_format($tournament->prize_pool) }} تومان</span>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-4 leading-6">جوایز این مسابقه به کیف پول برندگان واریز شده است.</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6">
        {{ $finishedTournaments->links() }}
    </div>
@endif
@endsection