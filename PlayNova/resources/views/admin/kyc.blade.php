@extends('layouts.app')
@section('title', 'احراز هویت | پنل مدیریت')

@section('content')
<h1 class="text-2xl font-bold mb-6">مدیریت احراز هویت (KYC)</h1>
@include('admin._nav')

@if (session('success'))
    <div class="mb-4 rounded-lg border border-green-700 bg-green-900/30 px-4 py-3 text-green-300 text-sm">{{ session('success') }}</div>
@endif

@php
    $statusLabels = [
        'pending' => 'در انتظار بررسی',
        'approved' => 'تأیید شده',
        'rejected' => 'رد شده',
    ];
@endphp

@if ($submissions->isEmpty())
    <div class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center">
        <p class="text-gray-300 text-lg mb-2">هنوز درخواست احراز هویتی ثبت نشده است.</p>
        <p class="text-sm text-gray-500">وقتی کاربران از صفحه <a href="{{ route('kyc.index') }}" class="text-secondary hover:underline">/kyc</a> مدارک را ارسال کنند، اینجا نمایش داده می‌شود.</p>
    </div>
@else
    <p class="text-sm text-gray-400 mb-4">تعداد: {{ $submissions->total() }} درخواست</p>
    <div class="space-y-4">
        @foreach($submissions as $s)
            <div class="bg-dark-800 border border-dark-600 rounded-xl p-4">
                <div class="flex flex-wrap justify-between gap-2 mb-3">
                    <div>
                        <h3 class="font-bold text-lg">{{ $s->user->username ?? '—' }}</h3>
                        <p class="text-xs text-gray-400 mt-1" dir="ltr">{{ $s->user->mobile ?? '—' }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ $s->created_at->format('Y-m-d H:i') }}</p>
                        @php $badge = match($s->status) {
                            'approved' => 'bg-green-900/40 text-green-300 border-green-800',
                            'rejected' => 'bg-red-900/40 text-red-300 border-red-800',
                            default => 'bg-yellow-900/40 text-yellow-200 border-yellow-800',
                        }; @endphp
                        <span class="inline-block mt-2 text-xs px-2 py-0.5 rounded border {{ $badge }}">
                            {{ $statusLabels[$s->status] ?? $s->status }}
                        </span>
                    </div>
                    <div class="flex gap-2 flex-wrap items-start">
                        @if($s->document_path)
                            <a href="{{ route('admin.kyc.document', [$s, 'document']) }}" target="_blank" class="text-xs bg-secondary/20 text-secondary px-3 py-1.5 rounded hover:bg-secondary/30">مشاهده تصویر مدارک</a>
                        @else
                            @if($s->card_front_path)
                                <a href="{{ route('admin.kyc.document', [$s, 'front']) }}" target="_blank" class="text-xs bg-secondary/20 text-secondary px-3 py-1.5 rounded">روی کارت</a>
                            @endif
                            @if($s->card_back_path)
                                <a href="{{ route('admin.kyc.document', [$s, 'back']) }}" target="_blank" class="text-xs bg-secondary/20 text-secondary px-3 py-1.5 rounded">پشت کارت</a>
                            @endif
                        @endif
                        @if(! $s->document_path && ! $s->card_front_path && ! $s->card_back_path)
                            <span class="text-xs text-red-400">فایل مدارک یافت نشد</span>
                        @endif
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.kyc.update', $s) }}" class="flex flex-wrap gap-2 items-end border-t border-dark-600 pt-3">
                    @csrf @method('PUT')
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">وضعیت</label>
                        <select name="status" class="bg-dark-700 border border-dark-600 rounded px-2 py-1.5 text-sm">
                            <option value="pending" @selected($s->status==='pending')>در انتظار</option>
                            <option value="approved" @selected($s->status==='approved')>تأیید</option>
                            <option value="rejected" @selected($s->status==='rejected')>رد</option>
                        </select>
                    </div>
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs text-gray-500 mb-1">یادداشت ادمین</label>
                        <input type="text" name="admin_note" value="{{ $s->admin_note }}" placeholder="اختیاری"
                            class="w-full bg-dark-700 border border-dark-600 rounded px-2 py-1.5 text-sm">
                    </div>
                    <button class="text-sm bg-success text-white px-4 py-1.5 rounded font-bold hover:opacity-90">ذخیره</button>
                </form>
            </div>
        @endforeach
    </div>
    <div class="mt-6">{{ $submissions->links() }}</div>
@endif
@endsection
