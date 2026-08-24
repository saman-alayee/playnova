@extends('layouts.app')
@section('title', 'جایگاه‌های مسابقات | PlayNova')

@section('content')
<h1 class="text-2xl font-bold mb-6">مشاهده جایگاه‌های مسابقات</h1>

@if(auth()->user()->isAdmin())
    @include('admin._nav')
@else
    <p class="text-sm text-gray-400 mb-4">فقط مشاهده جایگاه‌های هر مسابقه</p>
@endif

<div class="space-y-3">
    @forelse($tournaments as $t)
        <div class="bg-dark-800 border border-dark-600 rounded-xl p-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="font-bold text-white">{{ $t->title }}</h3>
                <p class="text-xs text-gray-400 mt-1">{{ $t->statusLabel() }} — {{ $t->registered_count }}/{{ $t->capacity }} — {{ $t->seatModeLabel() }}</p>
            </div>
            <a href="{{ route('admin.tournaments.seats', $t) }}" class="bg-secondary hover:opacity-90 text-white rounded px-4 py-2 text-sm font-bold">مشاهده نقشه جایگاه‌ها</a>
        </div>
    @empty
        <p class="text-gray-500">مسابقه‌ای برای نمایش وجود ندارد.</p>
    @endforelse
</div>
@endsection
