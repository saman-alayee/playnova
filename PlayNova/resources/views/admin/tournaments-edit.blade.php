@extends('layouts.app')
@section('title', 'ویرایش مسابقه | PlayNova')

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold mb-6 text-primary">✏️ ویرایش مسابقه</h1>
    @include('admin._nav')

    <div class="bg-dark-800 border border-dark-600 rounded-xl p-6">
        <form method="POST" action="{{ route('admin.tournaments.update', $tournament->id) }}" class="grid sm:grid-cols-2 gap-3">
            @csrf
            @method('PUT')
            <input type="text" name="title" value="{{ $tournament->title }}" placeholder="عنوان مسابقه" required class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
            <input type="text" name="game" value="{{ $tournament->game }}" placeholder="نام بازی" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
            <select name="league" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
                <option value="beginner" @selected(($tournament->league ?? 'intermediate') === 'beginner')>مبتدی</option>
                <option value="intermediate" @selected(($tournament->league ?? 'intermediate') === 'intermediate')>متوسط</option>
                <option value="professional" @selected(($tournament->league ?? 'intermediate') === 'professional')>حرفه‌ای</option>
            </select>
            <input type="number" name="entry_fee" value="{{ $tournament->entry_fee }}" placeholder="مبلغ ورودی" min="0" required class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
            <input type="number" name="prize_pool" value="{{ $tournament->prize_pool }}" placeholder="جایزه کل" min="0" required class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
            <input type="number" name="capacity" value="{{ $tournament->capacity }}" placeholder="ظرفیت" min="1" required class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
            <select name="seat_mode" required class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
                <option value="1" @selected($tournament->seatMode() === 1)>چیدمان یک‌نفره (۱ نفر در هر تیم)</option>
                <option value="2" @selected($tournament->seatMode() === 2)>چیدمان دو‌نفره (۲ نفر در هر تیم)</option>
                <option value="4" @selected($tournament->seatMode() === 4)>چیدمان چهارنفره (۴ نفر در هر تیم)</option>
            </select>
            <input type="datetime-local" name="start_date" value="{{ $tournament->start_date->format('Y-m-d\TH:i') }}" required class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
            <input type="datetime-local" name="end_date" value="{{ $tournament->end_date?->format('Y-m-d\TH:i') }}" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
            <select name="status" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
                <option value="upcoming" @selected($tournament->status=='upcoming')>آینده</option>
                <option value="active" @selected($tournament->status=='active')>فعال</option>
                <option value="ongoing" @selected($tournament->status=='ongoing')>در حال برگزاری</option>
                <option value="ended" @selected($tournament->status=='ended')>پایان یافته</option>
                <option value="cancelled" @selected($tournament->status=='cancelled')>لغو شده</option>
            </select>

            <!-- انتخاب برنده از بین شرکت‌کنندگان -->
            <div class="sm:col-span-2">
                <label class="block text-gray-300 text-sm mb-1">برنده مسابقه</label>
                <select name="winner_id" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
                    <option value="">بدون برنده</option>
                    @foreach($registeredUsers as $user)
                        <option value="{{ $user->id }}" @selected($tournament->winner_id == $user->id)>
                            {{ $user->username }} ({{ $user->email }})
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">فقط کاربرانی که در این مسابقه ثبت‌نام کرده‌اند نمایش داده می‌شوند.</p>
                @if($tournament->winner_id)
                    @php
                        $prizePaid = \App\Models\Transaction::where('type', 'prize')
                            ->where('reference_id', 'prize_' . $tournament->id)
                            ->where('status', 'completed')
                            ->exists();
                    @endphp
                    @if($prizePaid)
                        <p class="text-xs text-success mt-2">✓ جایزه این مسابقه به کیف پول برنده واریز شده است.</p>
                    @else
                        <form method="POST" action="{{ route('admin.tournaments.pay-prize', $tournament) }}" class="mt-2" onsubmit="return confirm('جایزه {{ number_format($tournament->prize_pool) }} تومان به برنده واریز شود؟')">
                            @csrf
                            <button type="submit" class="text-xs bg-secondary hover:opacity-90 text-white px-3 py-1.5 rounded font-bold">💰 واریز جایزه ({{ number_format($tournament->prize_pool) }} تومان)</button>
                        </form>
                    @endif
                @endif
            </div>

            <textarea name="description" placeholder="توضیحات" class="sm:col-span-2 bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">{{ $tournament->description }}</textarea>
            <div class="sm:col-span-2">
                <label class="block text-gray-300 text-sm mb-1">اطلاعات ورود به مسابقه</label>
                <textarea name="game_login_info" rows="4" placeholder="اتاق، رمز، لینک و سایر جزئیات ورود به بازی" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">{{ $tournament->game_login_info }}</textarea>
                <p class="text-xs text-gray-500 mt-1">این متن پس از شروع مسابقه، در بخش «اطلاعات ورود به بازی» برای شرکت‌کنندگان نمایش داده می‌شود.</p>
            </div>
            <button class="sm:col-span-2 bg-success hover:opacity-90 text-white rounded py-2 font-bold">💾 ذخیره تغییرات</button>
        </form>
    </div>
</div>
@endsection