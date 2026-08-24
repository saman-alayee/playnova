@extends('layouts.app')
@section('title', 'ادمین جایگاه | PlayNova')

@section('content')
<h1 class="text-2xl font-bold mb-6">ادمین‌های مشاهده جایگاه</h1>
@include('admin._nav')

@if(session('admin_success'))
    <div class="mb-4 rounded-lg border border-green-700 bg-green-900/30 px-4 py-3 text-green-300 text-sm">{{ session('admin_success') }}</div>
@endif
@if(session('admin_error'))
    <div class="mb-4 rounded-lg border border-red-700 bg-red-900/30 px-4 py-3 text-red-300 text-sm">{{ session('admin_error') }}</div>
@endif

<div class="bg-dark-800 border border-dark-600 rounded-xl p-6 mb-6">
    <h2 class="font-bold mb-3">افزودن ادمین جایگاه</h2>
    <p class="text-xs text-gray-400 mb-3">این کاربر فقط به صفحه مشاهده جایگاه‌های هر مسابقه دسترسی دارد (بدون سایر بخش‌های ادمین).</p>
    <form method="POST" action="{{ route('admin.seat-admins.store') }}" class="flex flex-wrap gap-2">
        @csrf
        <input type="email" name="email" required placeholder="ایمیل کاربر"
            class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-white min-w-[240px]">
        <button class="bg-success text-white rounded px-4 py-2 font-bold">افزودن</button>
    </form>
</div>

<div class="bg-dark-800 border border-dark-600 rounded-xl p-4">
    <h2 class="font-bold mb-3">لیست ادمین‌های جایگاه</h2>
    @forelse($seatAdmins as $admin)
        <div class="flex items-center justify-between py-2 border-b border-dark-600 last:border-0">
            <span>{{ $admin->username }} <span class="text-gray-500 text-xs">({{ $admin->email }})</span></span>
            <form method="POST" action="{{ route('admin.seat-admins.remove', $admin) }}" onsubmit="return confirm('حذف دسترسی؟');">
                @csrf
                @method('DELETE')
                <button class="text-xs text-red-400 hover:text-red-300">حذف دسترسی</button>
            </form>
        </div>
    @empty
        <p class="text-gray-500 text-sm">ادمین جایگاهی ثبت نشده است.</p>
    @endforelse
</div>
@endsection
