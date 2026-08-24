@extends('layouts.app')
@section('title', 'مدیریت تیکت‌ها | PlayNova')

@section('content')
<h1 class="text-2xl font-bold mb-6">مدیریت تیکت‌های پشتیبانی</h1>
@include('admin._nav')

<div class="space-y-4">
    @foreach($tickets as $ticket)
        <div class="bg-dark-800 border border-dark-600 rounded-xl p-4">
            <div class="flex justify-between items-start mb-2">
                <div>
                    <h3 class="font-bold">{{ $ticket->subject }}</h3>
                    <p class="text-xs text-gray-400">از طرف: {{ $ticket->user->username ?? '—' }} — اولویت: {{ $ticket->priority }}</p>
                </div>
                <form method="POST" action="{{ route('admin.tickets.status', $ticket) }}" class="flex gap-2 items-center">
                    @csrf @method('PUT')
                    <select name="status" class="bg-dark-700 border border-dark-600 rounded px-2 py-1 text-xs text-white">
                        <option value="open" @selected($ticket->status=='open')>باز</option>
                        <option value="in_progress" @selected($ticket->status=='in_progress')>در حال بررسی</option>
                        <option value="resolved" @selected($ticket->status=='resolved')>حل شده</option>
                        <option value="closed" @selected($ticket->status=='closed')>بسته شده</option>
                    </select>
                    <button class="text-xs bg-success text-white px-2 py-1 rounded font-bold">بروزرسانی</button>
                </form>
            </div>
            <p class="text-sm text-gray-300 mb-3">{{ $ticket->message }}</p>
            <a href="{{ route('tickets.show', $ticket) }}" class="text-xs text-secondary">مشاهده گفتگو و پاسخ →</a>
        </div>
    @endforeach
</div>
<div class="mt-4">{{ $tickets->links() }}</div>
@endsection
