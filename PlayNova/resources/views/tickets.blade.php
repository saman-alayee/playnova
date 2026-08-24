@extends('layouts.app')
@section('title', 'پشتیبانی | PlayNova')

@section('content')
<div class="grid md:grid-cols-2 gap-6">
    <div class="bg-dark-800 border border-dark-600 rounded-xl p-6">
        <h2 class="font-bold mb-4">ثبت تیکت جدید</h2>
        <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <input type="text" name="subject" placeholder="موضوع" required
                class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary">
            <select name="priority" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary">
                <option value="low">اولویت پایین</option>
                <option value="medium" selected>اولویت متوسط</option>
                <option value="high">اولویت بالا</option>
            </select>
            <textarea name="message" rows="5" placeholder="متن پیام" required
                class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary"></textarea>
            <input type="file" name="attachment" accept="image/*" class="text-sm">
            <button class="bg-success hover:opacity-90 text-white rounded px-4 py-2 font-bold w-full">ارسال تیکت</button>
        </form>
    </div>

    <div class="bg-dark-800 border border-dark-600 rounded-xl p-6">
        <h2 class="font-bold mb-4">تیکت‌های من</h2>
        <div class="space-y-3">
            @forelse($tickets as $ticket)
                @php
                    $statusLabel = ['open'=>'باز','in_progress'=>'در حال بررسی','resolved'=>'حل شده','closed'=>'بسته شده'];
                    $statusColor = ['open'=>'text-yellow-400','in_progress'=>'text-secondary','resolved'=>'text-green-400','closed'=>'text-gray-500'];
                @endphp
                <a href="{{ route('tickets.show', $ticket) }}" class="block border border-dark-700 rounded-lg p-3 hover:border-secondary/40 transition">
                    <div class="flex justify-between items-center mb-1">
                        <span class="font-bold text-sm">{{ $ticket->subject }}</span>
                        <span class="text-xs {{ $statusColor[$ticket->status] }}">{{ $statusLabel[$ticket->status] }}</span>
                    </div>
                    <p class="text-xs text-gray-400">{{ Str::limit($ticket->message, 100) }}</p>
                </a>
            @empty
                <p class="text-gray-500 text-sm">تیکتی ثبت نکرده‌اید.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
