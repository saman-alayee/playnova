@extends('layouts.app')
@section('title', 'جدول رتبه‌بندی | PlayNova')

@section('content')
<h1 class="text-2xl font-bold mb-2 text-center">🏆 رتبه‌بندی لیگ حرفه‌ای</h1>
<p class="text-center text-sm text-gray-400 mb-6">بر اساس تعداد کیل بازیکنان لیگ حرفه‌ای</p>
<div class="bg-dark-800 border border-dark-600 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-dark-700 text-gray-400">
                <th class="py-3 px-4 text-right">رتبه</th>
                <th class="py-3 px-4 text-right">نام کاربری</th>
                <th class="py-3 px-4 text-right">تعداد کیل</th>
            </tr>
        </thead>
        <tbody>
            @forelse($topPlayers as $i => $p)
                <tr class="border-b border-dark-700 {{ $i < 3 ? 'bg-primary/5' : '' }}">
                    <td class="py-2 px-4 font-bold {{ $i == 0 ? 'text-yellow-400' : ($i == 1 ? 'text-gray-300' : ($i == 2 ? 'text-orange-400' : '')) }}">
                        {{ $i + 1 }}
                    </td>
                    <td class="py-2 px-4">{{ $p->username }}</td>
                    <td class="py-2 px-4 text-green-400 font-bold">{{ $p->kills ?? 0 }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="py-8 px-4 text-center text-gray-500">هنوز بازیکنی با کیل ثبت‌شده وجود ندارد.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
