@extends('layouts.app')
@section('title', 'پیام همگانی | PlayNova')

@section('content')
<h1 class="text-2xl font-bold mb-6">ارسال پیام همگانی</h1>
@include('admin._nav')

<div class="bg-dark-800 border border-dark-600 rounded-xl p-6 max-w-xl">
    <form method="POST" action="{{ route('admin.broadcast.send') }}" class="space-y-3">
        @csrf
        <input type="text" name="title" placeholder="عنوان پیام" required class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary">
        <textarea name="message" rows="5" placeholder="متن پیام" required class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary"></textarea>
        <button class="bg-success hover:opacity-90 text-white rounded px-4 py-2 font-bold w-full">ارسال به همه کاربران</button>
    </form>
</div>
@endsection