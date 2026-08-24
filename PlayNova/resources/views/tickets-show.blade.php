@extends('layouts.app')
@section('title', 'تیکت | PlayNova')

@section('content')
<div class="max-w-3xl mx-auto space-y-4">
    <a href="{{ route('tickets.index') }}" class="text-sm text-secondary">← بازگشت به تیکت‌ها</a>
    <div class="bg-dark-800 border border-dark-600 rounded-xl p-5">
        <h1 class="font-bold text-lg">{{ $ticket->subject }}</h1>
        <p class="text-xs text-gray-500 mt-1">وضعیت: {{ $ticket->status }}</p>
    </div>

    <div class="space-y-3">
        @forelse($ticket->messages as $msg)
            <div class="rounded-xl p-4 {{ $msg->is_admin ? 'bg-primary/10 border border-primary/30' : 'bg-dark-800 border border-dark-600' }}">
                <div class="flex justify-between text-xs text-gray-500 mb-2">
                    <span>{{ $msg->is_admin ? 'پشتیبانی' : ($msg->user->username ?? 'کاربر') }}</span>
                    <span>{{ $msg->created_at->format('Y-m-d H:i') }}</span>
                </div>
                <p class="text-sm whitespace-pre-line">{{ $msg->body }}</p>
                @if($msg->attachment_path)
                    <a href="{{ route('tickets.attachment', $msg) }}" target="_blank" class="inline-block mt-2 text-xs text-secondary">📎 مشاهده پیوست</a>
                @endif
            </div>
        @empty
            <div class="rounded-xl p-4 bg-dark-800 border border-dark-600">
                <p class="text-sm whitespace-pre-line">{{ $ticket->message }}</p>
            </div>
        @endforelse
    </div>

    @if($ticket->status !== 'closed')
        <form method="POST" action="{{ route('tickets.reply', $ticket) }}" enctype="multipart/form-data" class="bg-dark-800 border border-dark-600 rounded-xl p-4 space-y-3">
            @csrf
            <textarea name="body" rows="4" placeholder="پاسخ شما..." required class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2"></textarea>
            <input type="file" name="attachment" accept="image/*" class="text-sm">
            <button class="bg-success text-white rounded px-4 py-2 font-bold">ارسال پاسخ</button>
        </form>
    @endif
</div>
@endsection
