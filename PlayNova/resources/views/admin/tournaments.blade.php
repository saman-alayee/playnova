@extends('layouts.app')
@section('title', 'مدیریت مسابقات | PlayNova')

@section('content')
<h1 class="text-2xl font-bold mb-6">مدیریت مسابقات</h1>
@include('admin._nav')

<div class="bg-dark-800 border border-dark-600 rounded-xl p-6 mb-6">
    <h2 class="font-bold mb-4">ایجاد مسابقه جدید</h2>
    <form method="POST" action="{{ route('admin.tournaments.store') }}" class="grid sm:grid-cols-2 gap-3">
        @csrf
        <input type="text" name="title" placeholder="عنوان مسابقه" required class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
        <input type="text" name="game" placeholder="نام بازی" value="Call of Duty Mobile" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
        <select name="league" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
            <option value="beginner">مبتدی</option>
            <option value="intermediate" selected>متوسط</option>
            <option value="professional">حرفه‌ای</option>
        </select>
        <input type="number" name="entry_fee" placeholder="مبلغ ورودی" min="0" required class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
        <input type="number" name="prize_pool" placeholder="جایزه کل" min="0" required class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
        <input type="number" name="capacity" placeholder="ظرفیت" min="1" required class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
        <select name="seat_mode" required class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
            <option value="1">چیدمان یک‌نفره (۱ نفر در هر تیم)</option>
            <option value="2" selected>چیدمان دو‌نفره (۲ نفر در هر تیم)</option>
            <option value="4">چیدمان چهارنفره (۴ نفر در هر تیم)</option>
        </select>
        <input type="datetime-local" name="start_date" required class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
        <textarea name="description" placeholder="توضیحات" class="sm:col-span-2 bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white"></textarea>
        <div class="sm:col-span-2">
            <label class="block text-gray-300 text-sm mb-1">اطلاعات ورود به مسابقه (اختیاری)</label>
            <textarea name="game_login_info" rows="3" placeholder="اتاق، رمز، لینک — پس از شروع مسابقه برای شرکت‌کنندگان نمایش داده می‌شود" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white"></textarea>
        </div>
        <button class="sm:col-span-2 bg-success hover:bg-green-700 text-white rounded py-2 font-bold">ایجاد مسابقه</button>
    </form>
</div>

<div class="space-y-4">
    @foreach($tournaments as $t)
        @php
            $prizePaid = $t->winner_id && \App\Models\Transaction::where('type', 'prize')
                ->where('reference_id', 'prize_' . $t->id)
                ->where('status', 'completed')
                ->exists();
        @endphp
        <div class="bg-dark-800 border border-dark-600 rounded-xl p-4">
            <div class="flex flex-wrap justify-between items-center gap-2 mb-2">
                <h3 class="font-bold text-white">{{ $t->title }}</h3>
                <span class="text-xs px-2 py-1 rounded bg-dark-700 text-gray-300">{{ $t->statusLabel() }}</span>
            </div>
            <p class="text-xs text-gray-400 mb-3">
                ظرفیت: {{ $t->registered_count }}/{{ $t->capacity }} — چیدمان: {{ $t->seatModeLabel() }} — جایزه: {{ number_format($t->prize_pool) }} تومان
                @if($t->winner)
                    — 🏆 برنده: <span class="text-secondary font-bold">{{ $t->winner->username }}</span>
                    @if($prizePaid)
                        — <span class="text-success">جایزه واریز شده</span>
                    @endif
                @endif
            </p>

            <div class="flex flex-wrap gap-2 items-center">
                <!-- فرم تغییر وضعیت -->
                <form method="POST" action="{{ route('admin.tournaments.status', $t) }}" class="flex gap-2 items-center">
                    @csrf
                    @method('PUT')
                    <select name="status" class="bg-dark-700 border border-dark-600 rounded px-2 py-1 text-xs text-white">
                        <option value="upcoming" @selected($t->status=='upcoming')>آینده</option>
                        <option value="active" @selected($t->status=='active')>فعال</option>
                        <option value="ongoing" @selected($t->status=='ongoing')>در حال برگزاری</option>
                        <option value="ended" @selected($t->status=='ended')>پایان یافته</option>
                        <option value="cancelled" @selected($t->status=='cancelled')>لغو شده</option>
                    </select>
                    <button class="text-xs bg-success hover:bg-green-700 text-white px-2 py-1 rounded font-bold">بروزرسانی وضعیت</button>
                </form>

                <a href="{{ route('admin.tournaments.seats', $t) }}" class="text-xs bg-secondary hover:opacity-90 text-white px-2 py-1 rounded font-bold">🗺️ نقشه جایگاه‌ها</a>

                <!-- فرم ثبت نتیجه (در صورت عدم پایان) -->
                @if($t->status != 'ended')
                <form method="POST" action="{{ route('admin.tournaments.result', $t) }}" class="flex gap-2 items-center">
                    @csrf
                    <select name="winner_id" required class="bg-dark-700 border border-dark-600 rounded px-2 py-1 text-xs w-40 text-white">
                        <option value="">انتخاب برنده...</option>
                        @php
                            $participants = App\Models\Registration::where('tournament_id', $t->id)
                                ->where('status', 'registered')
                                ->with('user')
                                ->get();
                        @endphp
                        @foreach($participants as $reg)
                            <option value="{{ $reg->user->id }}">{{ $reg->user->username }} ({{ $reg->user->email }})</option>
                        @endforeach
                    </select>
                    <button class="text-xs bg-success hover:bg-green-700 text-white px-2 py-1 rounded font-bold">ثبت نتیجه و برنده</button>
                </form>
                @endif

                @if($t->winner_id && !$prizePaid)
                    <form method="POST" action="{{ route('admin.tournaments.pay-prize', $t) }}" class="inline" onsubmit="return confirm('جایزه {{ number_format($t->prize_pool) }} تومان به {{ $t->winner->username }} واریز شود؟')">
                        @csrf
                        <button class="text-xs bg-secondary hover:opacity-90 text-white px-2 py-1 rounded font-bold">💰 واریز جایزه</button>
                    </form>
                @endif

                <a href="{{ route('admin.tournaments.edit', $t->id) }}" class="text-xs bg-secondary/20 text-secondary px-2 py-1 rounded font-bold hover:bg-secondary/30 transition">✏️ ویرایش</a>

                <form method="POST" action="{{ route('admin.tournaments.delete', $t->id) }}" class="inline" onsubmit="return confirm('آیا از حذف این مسابقه مطمئن هستید؟')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-xs bg-danger hover:bg-red-700 text-white px-2 py-1 rounded font-bold">🗑️ حذف</button>
                </form>
            </div>
        </div>
    @endforeach
</div>
<div class="mt-4">{{ $tournaments->links() }}</div>
@endsection