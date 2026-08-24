@extends('layouts.app')
@section('title', 'کدهای تخفیف | PlayNova')

@section('content')
<h1 class="text-2xl font-bold mb-6">مدیریت کدهای تخفیف</h1>
@include('admin._nav')

<div class="bg-dark-800 border border-dark-600 rounded-xl p-6 mb-6">
    <h2 class="font-bold mb-4">ایجاد کد تخفیف جدید</h2>
    <form method="POST" action="{{ route('admin.discounts.store') }}" class="grid sm:grid-cols-2 gap-3">
        @csrf
        <input type="text" name="code" placeholder="کد تخفیف" required class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary">
        <select name="type" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary">
            <option value="percentage">درصدی</option>  {{-- اصلاح: percent -> percentage --}}
            <option value="fixed">مبلغ ثابت</option>
        </select>
        <input type="number" name="value" placeholder="مقدار" required class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary">
        <input type="number" name="usage_limit" placeholder="حداکثر تعداد استفاده (۰=نامحدود)" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary">
        <input type="date" name="expires_at" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary">
        <button class="bg-success hover:opacity-90 text-white rounded py-2 font-bold">ایجاد کد</button>  {{-- اصلاح: bg-secondary -> bg-success --}}
    </form>
</div>

<div class="space-y-2">
    @foreach($discounts as $d)
        <div class="bg-dark-800 border border-dark-600 rounded-xl p-4 flex justify-between items-center">
            <div>
                <span class="font-mono font-bold text-primary">{{ $d->code }}</span>
                <span class="text-xs text-gray-400 mr-2">{{ $d->type == 'percentage' ? $d->value.'%' : number_format($d->value).' تومان' }} — استفاده: {{ $d->used_count }}/{{ $d->usage_limit ?: '∞' }}</span>
            </div>
            <form method="POST" action="{{ route('admin.discounts.delete', $d) }}">
                @csrf
                @method('DELETE')
                <button class="text-xs text-red-400 hover:text-red-300">حذف</button>
            </form>
        </div>
    @endforeach
</div>
<div class="mt-4">{{ $discounts->links() }}</div>
@endsection