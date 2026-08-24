@extends('layouts.app')
@section('title', 'مدیریت کاربران | PlayNova')

@section('content')
<h1 class="text-2xl font-bold mb-6">مدیریت کاربران</h1>
@include('admin._nav')

<form method="GET" class="mb-4">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="جستجوی نام کاربری، ایمیل، موبایل یا آیدی کالاف"
        class="bg-dark-700 border border-dark-600 rounded px-3 py-2 w-full max-w-md outline-none focus:border-secondary">
</form>

<div class="bg-dark-800 border border-dark-600 rounded-xl overflow-x-auto">
    <table class="w-full text-sm min-w-[960px]">
        <thead>
            <tr class="bg-dark-700 text-gray-400">
                <th class="py-2 px-3 text-right">آیدی</th>
                <th class="py-2 px-3 text-right">نام کاربری</th>
                <th class="py-2 px-3 text-right">ایمیل/موبایل</th>
                <th class="py-2 px-3 text-right">کیل</th>
                <th class="py-2 px-3 text-right">آیدی کالاف</th>
                <th class="py-2 px-3 text-right">جایگاه‌ها</th>
                <th class="py-2 px-3 text-right">کیف پول</th>
                <th class="py-2 px-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $u)
                <tr class="border-b border-dark-700 align-top">
                    <td class="py-2 px-3">{{ $u->id }}</td>
                    <td class="py-2 px-3">{{ $u->username }} @if($u->is_admin) <span class="text-primary text-xs">(ادمین)</span> @endif</td>
                    <td class="py-2 px-3">{{ $u->email ?? $u->mobile }}</td>
                    <td class="py-2 px-3">
                        <form method="POST" action="{{ route('admin.users.kills', $u) }}" class="flex gap-1 items-center">
                            @csrf @method('PUT')
                            <input type="number" name="kills" value="{{ $u->kills ?? 0 }}" min="0" class="w-16 bg-dark-700 border border-dark-600 rounded px-1 py-0.5 text-xs">
                            <button class="text-xs text-secondary">✓</button>
                        </form>
                    </td>
                    <td class="py-2 px-3">
                        <form method="POST" action="{{ route('admin.users.cod-id', $u) }}" class="space-y-1 min-w-[140px]">
                            @csrf @method('PUT')
                            <input type="text" name="cod_id" value="{{ $u->cod_id }}" dir="ltr" maxlength="100" required
                                class="w-full bg-dark-700 border border-dark-600 rounded px-2 py-1 text-xs font-mono outline-none focus:border-secondary">
                            <button type="submit" class="text-xs text-secondary hover:underline">ذخیره آیدی</button>
                        </form>
                        @if($u->cod_id_changed)
                            <p class="text-[10px] text-amber-400/90 mt-1">کاربر یک‌بار خودش تغییر داده</p>
                        @endif
                    </td>
                    <td class="py-2 px-3 max-w-[220px]">
                        @forelse($u->registrations as $reg)
                            @if($reg->tournament)
                                <div class="text-xs mb-1 leading-relaxed">
                                    <span class="text-gray-300">{{ Str::limit($reg->tournament->title, 28) }}</span>
                                    <span class="text-secondary font-bold"> — {{ $reg->tournament->seatDisplayLabel($reg->seat_number) }}</span>
                                </div>
                            @endif
                        @empty
                            <span class="text-gray-500 text-xs">—</span>
                        @endforelse
                    </td>
                    <td class="py-2 px-3 whitespace-nowrap">{{ number_format($u->wallet) }}</td>
                    <td class="py-2 px-3">
                        <form method="POST" action="{{ route('admin.users.wallet', $u) }}" class="flex flex-wrap gap-1 items-center mb-2">
                            @csrf @method('PUT')
                            <select name="action" class="bg-dark-700 border border-dark-600 rounded px-1 py-0.5 text-xs">
                                <option value="add">+ افزایش</option>
                                <option value="subtract">− کاهش</option>
                            </select>
                            <input type="number" name="amount" min="1" placeholder="مبلغ" required class="w-24 bg-dark-700 border border-dark-600 rounded px-1 py-0.5 text-xs">
                            <input type="text" name="description" placeholder="توضیح (اختیاری)" class="w-28 bg-dark-700 border border-dark-600 rounded px-1 py-0.5 text-xs">
                            <button class="text-xs text-success whitespace-nowrap">اعمال</button>
                        </form>
                        @unless($u->is_admin)
                        <form method="POST" action="{{ route('admin.users.delete', $u) }}" onsubmit="return confirm('آیا مطمئن هستید؟')">
                            @csrf
                            @method('DELETE')
                            <button class="text-xs text-red-400 hover:text-red-300">حذف</button>
                        </form>
                        @endunless
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $users->links() }}</div>
@endsection
